<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Controller;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\LoginStartRequest;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final class LoginController extends BaseController
{
    public function start(): void
    {
        $app = $this->siteApplication();
        $returnUrl = $this->input->getString('return', '/');

        try {
            $request = new LoginStartRequest($returnUrl);
            $redirect = $this->component()->getLoginService()->begin($request);

            $app->redirect($redirect->url, 302);
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
            OidcErrorCode::ConfigurationError,
            OidcErrorCode::InternalError => 'COM_OIDCCLIENT_LOGIN_ERROR_UNAVAILABLE',
            default => 'COM_OIDCCLIENT_LOGIN_START_ERROR',
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
            throw new \RuntimeException('OIDC login is frontend-only.');
        }

        return $this->app;
    }

    private function reportFailure(\Throwable $exception): string
    {
        $correlationId = bin2hex(random_bytes(8));
        $errorCode = $exception instanceof OidcException
            ? $exception->errorCode
            : OidcErrorCode::InvalidRequest;

        Log::add(
            \sprintf(
                'OIDC failure correlation_id=%s step=login_start error_code=%s',
                $correlationId,
                $errorCode->value,
            ),
            Log::WARNING,
            'com_oidcclient',
        );

        return $correlationId;
    }
}
