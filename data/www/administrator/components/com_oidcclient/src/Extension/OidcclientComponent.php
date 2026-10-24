<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Extension;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\MVCComponent;
use JoomlaOidc\Component\OidcClient\Site\Contract\ConfigurationResolverInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Service\GovBrLoginService;

final class OidcclientComponent extends MVCComponent
{
    private ?GovBrLoginService $loginService = null;
    private ?LoginGrantStoreInterface $loginGrantStore = null;

    /**
     * @param \Closure(): GovBrLoginService $loginServiceFactory
     * @param \Closure(): LoginGrantStoreInterface $loginGrantStoreFactory
     */
    public function __construct(
        ComponentDispatcherFactoryInterface $dispatcherFactory,
        private readonly \Closure $loginServiceFactory,
        private readonly ConfigurationResolverInterface $configResolver,
        private readonly \Closure $loginGrantStoreFactory,
        private readonly bool $enabled,
    ) {
        parent::__construct($dispatcherFactory);
    }

    public function getLoginService(): GovBrLoginService
    {
        return $this->loginService ??= ($this->loginServiceFactory)();
    }

    public function getLoginGrantStore(): LoginGrantStoreInterface
    {
        return $this->loginGrantStore ??= ($this->loginGrantStoreFactory)();
    }

    public function isConfigured(): bool
    {
        try {
            $this->configResolver->resolve();

            return true;
        } catch (OidcException) {
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
