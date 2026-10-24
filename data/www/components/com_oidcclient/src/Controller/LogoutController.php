<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Site\Controller;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\MVC\Controller\BaseController;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;
use JoomlaOidc\Component\OidcClient\Site\Exception\OidcException;
use JoomlaOidc\Component\OidcClient\Site\Value\OidcErrorCode;

final class LogoutController extends BaseController
{
    public function completed(): void
    {
        $this->siteApplication()->redirect('index.php', 302);
    }

    public function start(): void
    {
        $app = $this->siteApplication();
        $logoutAttempted = false;

        if (!Session::checkToken('post')) {
            $correlationId = $this->reportFailure(OidcErrorCode::InvalidRequest);
            $app->enqueueMessage('Logout could not be completed. Reference: ' . $correlationId, 'warning');
            $app->redirect('index.php', 302);
            return;
        }

        $user = $app->getIdentity();

        if ($user === null || $user->get('id') === 0) {
            $app->redirect('index.php', 302);
            return;
        }

        try {
            $service = $this->component()->getLoginService();
            $federated = $app->getSession()->get('com_oidcclient.authenticated_provider') === $service->providerKey();
            $logoutUrl = $service->redirectUrl($federated);
            $logoutAttempted = true;

            if (!$app->logout()) {
                throw new \RuntimeException('Joomla logout failed.');
            }

            $app->redirect($logoutUrl, 302);
        } catch (OidcException | \RuntimeException $exception) {
            $errorCode = $exception instanceof OidcException
                ? $exception->errorCode
                : OidcErrorCode::LogoutError;
            $correlationId = $this->reportFailure($errorCode);
            $app->enqueueMessage('Logout could not be completed. Reference: ' . $correlationId, 'warning');

            if (!$logoutAttempted) {
                $app->logout();
            }

            $app->redirect('index.php', 302);
        }
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
            throw new \RuntimeException('OIDC logout is frontend-only.');
        }

        return $this->app;
    }

    private function reportFailure(OidcErrorCode $errorCode): string
    {
        $correlationId = bin2hex(random_bytes(8));

        Log::add(
            \sprintf(
                'OIDC failure correlation_id=%s step=logout error_code=%s',
                $correlationId,
                $errorCode->value,
            ),
            Log::WARNING,
            'com_oidcclient',
        );

        return $correlationId;
    }
}
