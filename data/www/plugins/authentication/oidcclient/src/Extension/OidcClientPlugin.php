<?php

declare(strict_types=1);

namespace JoomlaOidc\Plugin\Authentication\OidcClient\Extension;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Event\User\AuthenticationEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use JoomlaOidc\Component\OidcClient\Site\Contract\LoginGrantStoreInterface;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;

final class OidcClientPlugin extends CMSPlugin implements SubscriberInterface
{
    private const GRANT_CREDENTIAL_KEY = 'oidcclient.login_grant';
    private const AUTHENTICATION_TYPE = 'OIDC';

    /**
     * @param array<string, mixed> $config
     * @param \Closure(): LoginGrantStoreInterface $grantStoreFactory
     */
    public function __construct(
        array $config,
        private readonly \Closure $grantStoreFactory,
    ) {
        parent::__construct($config);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAuthenticate' => 'onUserAuthenticate',
        ];
    }

    public function onUserAuthenticate(AuthenticationEvent $event): void
    {
        $credentials = $event->getCredentials();
        $response = $event->getAuthenticationResponse();

        if (!isset($credentials[self::GRANT_CREDENTIAL_KEY])) {
            return;
        }

        $rawGrant = $credentials[self::GRANT_CREDENTIAL_KEY];

        if (!\is_string($rawGrant) || $rawGrant === '') {
            $response->status = Authentication::STATUS_FAILURE;
            $response->error_message = 'Invalid login grant.';

            return;
        }

        try {
            $localUser = ($this->grantStoreFactory)()->consume($rawGrant);
        } catch (OidcException) {
            $response->status = Authentication::STATUS_FAILURE;
            $response->error_message = 'Invalid or expired login grant.';

            return;
        } finally {
            \sodium_memzero($rawGrant);
        }

        $response->status = Authentication::STATUS_SUCCESS;
        $response->type = self::AUTHENTICATION_TYPE;
        $response->username = $localUser->username;
        $response->fullname = $localUser->displayName;
        $response->email = $localUser->email ?? '';
        $event->stopPropagation();
    }
}
