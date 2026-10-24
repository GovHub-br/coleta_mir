<?php

declare(strict_types=1);

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;
use JoomlaOidc\Component\OidcClient\Site\Contract\ClockInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ConfigurationResolverInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\HttpClientInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\JwksCacheInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginTransactionStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\ProviderProfileInterface;
use JoomlaOidc\Component\OidcClient\Site\Identity\IdentityRepositoryInterface;
use JoomlaOidc\Component\OidcClient\Site\Identity\JoomlaIdentityRepository;
use JoomlaOidc\Component\OidcClient\Site\Identity\JoomlaUserResolver;
use JoomlaOidc\Component\OidcClient\Site\Identity\UserResolverInterface;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\EnvConfigurationResolver;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\EnvProviderProfileResolver;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\JoomlaHttpClient;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\JoomlaJwksCache;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\SessionLoginGrantStore;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\SessionLoginTransactionStore;
use JoomlaOidc\Component\OidcClient\Site\Infrastructure\SystemClock;
use JoomlaOidc\Component\OidcClient\Site\Protocol\CryptoRandom;
use JoomlaOidc\Component\OidcClient\Site\Protocol\IdTokenValidator;
use JoomlaOidc\Component\OidcClient\Site\Protocol\AccessTokenValidator;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProviderClient;
use JoomlaOidc\Component\OidcClient\Site\Protocol\GovBrProfileRegistry;
use JoomlaOidc\Component\OidcClient\Site\Protocol\JwksLoader;
use JoomlaOidc\Component\OidcClient\Site\Service\GovBrLoginService;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\JoomlaOidc\\Component\\OidcClient'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\JoomlaOidc\\Component\\OidcClient'));

        Log::addLogger(
            ['text_file' => 'com_oidcclient.php', 'text_entry_format' => '{DATETIME}	{PRIORITY}	{MESSAGE}'],
            Log::WARNING | Log::ERROR | Log::CRITICAL | Log::ALERT | Log::EMERGENCY,
            ['com_oidcclient'],
        );

        $container->set(
            ComponentInterface::class,
            function (Container $c): ComponentInterface {
                $enabled = (bool) ComponentHelper::getParams('com_oidcclient')->get('enabled', true);
                $component = new OidcclientComponent(
                    $c->get(ComponentDispatcherFactoryInterface::class),
                    static fn(): GovBrLoginService => $c->get(GovBrLoginService::class),
                    $c->get(ConfigurationResolverInterface::class),
                    static fn(): LoginGrantStoreInterface => $c->get(LoginGrantStoreInterface::class),
                    $enabled,
                );
                $component->setMVCFactory($c->get(MVCFactoryInterface::class));

                return $component;
            },
        );

        $container->set(
            GovBrProfileRegistry::class,
            static fn(): GovBrProfileRegistry => new GovBrProfileRegistry(),
        );

        $container->set(
            ProviderProfileInterface::class,
            static fn(Container $c): ProviderProfileInterface => (new EnvProviderProfileResolver(
                $c->get(GovBrProfileRegistry::class),
            ))->resolve(),
        );

        $container->set(
            ClockInterface::class,
            static fn(): ClockInterface => new SystemClock(),
        );

        $container->set(
            ConfigurationResolverInterface::class,
            static fn(): ConfigurationResolverInterface => new EnvConfigurationResolver(),
        );

        $container->set(
            HttpClientInterface::class,
            static function (Container $c): HttpClientInterface {
                return new JoomlaHttpClient(
                    $c->get(ProviderProfileInterface::class),
                    new HttpFactory(),
                );
            },
        );

        $container->set(
            JwksCacheInterface::class,
            static function (Container $c): JwksCacheInterface {
                $cache = $c->get(CacheControllerFactoryInterface::class)->createCacheController(
                    'output',
                    ['defaultgroup' => 'com_oidcclient'],
                );

                return new JoomlaJwksCache($cache);
            },
        );

        $container->set(
            LoginTransactionStoreInterface::class,
            static function (Container $c): LoginTransactionStoreInterface {
                $app = Factory::getApplication();

                if (!$app instanceof SiteApplication) {
                    throw new \RuntimeException('OIDC login transactions are frontend-only.');
                }

                return new SessionLoginTransactionStore(
                    $app->getSession(),
                    $c->get(ClockInterface::class),
                );
            },
        );

        $container->set(
            LoginGrantStoreInterface::class,
            static function (Container $c): LoginGrantStoreInterface {
                $app = Factory::getApplication();

                if (!$app instanceof SiteApplication) {
                    throw new \RuntimeException('OIDC login grants are frontend-only.');
                }

                return new SessionLoginGrantStore(
                    $app->getSession(),
                    $c->get(ClockInterface::class),
                );
            },
        );

        $container->set(
            IdentityRepositoryInterface::class,
            static function (Container $c): IdentityRepositoryInterface {
                return new JoomlaIdentityRepository($c->get(DatabaseInterface::class));
            },
        );

        $container->set(
            UserResolverInterface::class,
            static function (Container $c): UserResolverInterface {
                $params = ComponentHelper::getParams('com_oidcclient');
                $identityRepo = $c->get(IdentityRepositoryInterface::class);
                $userFactory = $c->get(UserFactoryInterface::class);

                return new JoomlaUserResolver(
                    $identityRepo,
                    $userFactory,
                    (bool) ($params->get('allow_exact_username_link', false)),
                );
            },
        );

        $container->set(
            GovBrLoginService::class,
            static function (Container $c): GovBrLoginService {
                $params = ComponentHelper::getParams('com_oidcclient');
                $profile = $c->get(ProviderProfileInterface::class);
                $clientConfig = $c->get(ConfigurationResolverInterface::class)->resolve();
                $httpClient = $c->get(HttpClientInterface::class);

                return new GovBrLoginService(
                    profile: $profile,
                    clientConfig: $clientConfig,
                    transactionStore: $c->get(LoginTransactionStoreInterface::class),
                    clock: $c->get(ClockInterface::class),
                    cryptoRandom: new CryptoRandom(),
                    providerClient: new GovBrProviderClient($profile, $clientConfig, $httpClient),
                    jwksLoader: new JwksLoader(
                        $httpClient,
                        $c->get(JwksCacheInterface::class),
                    ),
                    idTokenValidator: new IdTokenValidator(),
                    accessTokenValidator: new AccessTokenValidator(),
                    userResolver: $c->get(UserResolverInterface::class),
                    loginGrantStore: $c->get(LoginGrantStoreInterface::class),
                    identityRepository: $c->get(IdentityRepositoryInterface::class),
                    enabled: (bool) $params->get('enabled', true),
                );
            },
        );
    }
};
