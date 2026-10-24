<?php

declare(strict_types=1);

namespace JoomlaOidc\Plugin\System\OidcClient\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Event\User\AuthorisationEvent;
use Joomla\CMS\Event\User\LogoutEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Session\SessionInterface;

final class OidcClientSystemPlugin extends CMSPlugin implements SubscriberInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        private readonly CMSApplicationInterface $app,
        private readonly ?SessionInterface $session,
        private readonly UserFactoryInterface $userFactory,
        private readonly int $enteGroupId,
        private readonly bool $oidcEnabled,
    ) {
        if ($this->enteGroupId <= 0) {
            throw new \InvalidArgumentException('Ente group ID must be positive.');
        }

        parent::__construct($config);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserAuthorisation' => 'onUserAuthorisation',
            'onUserLogout' => 'onUserLogout',
        ];
    }

    public function onUserAuthorisation(AuthorisationEvent $event): void
    {
        if (!$this->app->isClient('site') || !$this->oidcEnabled) {
            return;
        }

        $response = $event->getAuthenticationResponse();

        if ($response->type === 'OIDC') {
            return;
        }

        $user = $this->userFactory->loadUserByUsername($response->username);

        if (
            (int) $user->get('id') > 0
            && \in_array($this->enteGroupId, $user->getAuthorisedGroups(), true)
        ) {
            return;
        }

        $denied = new AuthenticationResponse();
        $denied->status = (string) Authentication::STATUS_DENIED;
        $denied->type = $response->type;
        $denied->username = $response->username;
        $denied->error_message = 'Frontend password login is restricted to Ente accounts.';
        $event->addResult($denied);
    }

    public function onUserLogout(LogoutEvent $event): void
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        $this->session?->remove('com_oidcclient.transactions');
        $this->session?->remove('com_oidcclient.login_grants');
        $this->session?->remove('com_oidcclient.authenticated_provider');
    }
}
