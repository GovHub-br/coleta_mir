<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Controller;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\CallbackRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\LocalUser;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcFailureCategory;

final class CallbackController extends BaseController
{
    public function complete(): void
    {
        $app = $this->siteApplication();
        $state = $this->input->getString('state', '');
        $code = $this->input->getString('code', null);
        $error = $this->input->getString('error', null);

        try {
            $service = $this->component()->getLoginService();
            $callbackRequest = new CallbackRequest($state, $code, $error);

            $login = $service->complete($callbackRequest);

            $this->dispatchBeforeLocalLogin($login->principal, $login->localUser);

            $grant = $service->issueGrant($login);

            try {
                $loggedIn = $app->login(
                    ['oidcclient.login_grant' => $grant],
                    ['action' => 'core.login.site', 'authentication_type' => 'OIDC'],
                );
            } finally {
                \sodium_memzero($grant);
            }

            if (!$loggedIn) {
                throw new OidcException(
                    \JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode::InternalError,
                    'Joomla rejected the validated local login.',
                    failureCategory: OidcFailureCategory::JoomlaLoginRejected,
                );
            }

            $app->getSession()->set('com_oidcclient.authenticated_provider', $login->principal->providerKey);

            try {
                $service->recordLogin($login);
            } catch (OidcException $exception) {
                $correlationId = $this->reportFailure($exception);
                $app->enqueueMessage(
                    Text::sprintf('COM_OIDCCLIENT_LOGIN_AUDIT_WARNING', $correlationId),
                    'warning',
                );
            }

            $app->redirect($login->returnUrl, 302);
        } catch (OidcException | \InvalidArgumentException $exception) {
            $correlationId = $this->reportFailure($exception);
            $app->enqueueMessage(
                $this->publicFailureMessage($exception, $correlationId),
                'warning',
            );
            $app->redirect('index.php', 302);
        }
    }

    private function publicFailureMessage(\Throwable $exception, string $correlationId): string
    {
        $errorCode = $exception instanceof OidcException
            ? $exception->errorCode
            : OidcErrorCode::InvalidRequest;

        $messageKey = match ($errorCode) {
            OidcErrorCode::InvalidRequest,
            OidcErrorCode::InvalidGrant,
            OidcErrorCode::ExpiredGrant,
            OidcErrorCode::InvalidState,
            OidcErrorCode::ExpiredTransaction => 'COM_OIDCCLIENT_LOGIN_ERROR_EXPIRED',
            OidcErrorCode::ProviderError => 'COM_OIDCCLIENT_LOGIN_ERROR_PROVIDER_DENIED',
            OidcErrorCode::TokenExchangeFailed,
            OidcErrorCode::JwksUnavailable,
            OidcErrorCode::HttpRequestFailed => 'COM_OIDCCLIENT_LOGIN_ERROR_PROVIDER_UNAVAILABLE',
            OidcErrorCode::InvalidToken => 'COM_OIDCCLIENT_LOGIN_ERROR_VALIDATION',
            OidcErrorCode::IdentityNotLinked,
            OidcErrorCode::IdentityConflict,
            OidcErrorCode::UserNotAllowed => 'COM_OIDCCLIENT_LOGIN_ERROR_NOT_AUTHORIZED',
            OidcErrorCode::ConfigurationError,
            OidcErrorCode::LogoutError,
            OidcErrorCode::InternalError => 'COM_OIDCCLIENT_LOGIN_ERROR_UNAVAILABLE',
        };

        return Text::sprintf($messageKey, $correlationId);
    }

    private function component(): OidcclientComponent
    {
        $component = $this->siteApplication()->bootComponent('com_oidcclient');

        if (!$component instanceof OidcclientComponent) {
            throw new \RuntimeException('OIDC client component failed to boot.');
        }

        return $component;
    }

    private function siteApplication(): SiteApplication
    {
        if (!$this->app instanceof SiteApplication) {
            throw new \RuntimeException('OIDC callback is frontend-only.');
        }

        return $this->app;
    }

    private function dispatchBeforeLocalLogin(
        \JoomlaOidc\Component\OidcClient\Site\Value\ValidatedPrincipal $principal,
        LocalUser $localUser,
    ): void {
        $event = AbstractEvent::create(
            'onOidcBeforeLocalLogin',
            [
                'subject' => $localUser,
                'validatedPrincipal' => $principal,
                'localUser' => $localUser,
                'providerKey' => $principal->providerKey,
            ],
        );

        $this->siteApplication()->getDispatcher()->dispatch('onOidcBeforeLocalLogin', $event);

        if ($event->isStopped()) {
            throw new OidcException(
                \JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode::UserNotAllowed,
                'Login vetoed by policy listener.',
                failureCategory: OidcFailureCategory::LoginPolicyVeto,
            );
        }
    }

    private function reportFailure(\Throwable $exception): string
    {
        $correlationId = bin2hex(random_bytes(8));
        $errorCode = $exception instanceof OidcException
            ? $exception->errorCode
            : OidcErrorCode::InvalidRequest;

        $message = \sprintf(
            'OIDC failure correlation_id=%s step=callback error_code=%s',
            $correlationId,
            $errorCode->value,
        );

        if ($exception instanceof OidcException && $exception->failureCategory !== null) {
            $message .= \sprintf(' result_category=%s', $exception->failureCategory->value);
        }

        Log::add(
            $message,
            Log::WARNING,
            'com_oidcclient',
        );

        return $correlationId;
    }
}
