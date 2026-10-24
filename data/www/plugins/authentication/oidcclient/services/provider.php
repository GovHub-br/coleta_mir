<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Plugin\Authentication\OidcClient\Extension\OidcClientPlugin;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(OidcClientPlugin::class, function (Container $container) {
                $app = Factory::getApplication();

                $plugin = new OidcClientPlugin(
                    (array) PluginHelper::getPlugin('authentication', 'oidcclient'),
                    static function () use ($app): LoginGrantStoreInterface {
                        $component = $app->bootComponent('com_oidcclient');

                        if (!$component instanceof OidcclientComponent) {
                            throw new \RuntimeException('OIDC client component failed to boot.');
                        }

                        return $component->getLoginGrantStore();
                    },
                );

                $plugin->setApplication($app);

                return $plugin;
            }),
        );
    }
};
