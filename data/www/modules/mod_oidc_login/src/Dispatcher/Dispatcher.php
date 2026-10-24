<?php

declare(strict_types=1);

namespace JoomlaOidc\Module\OidcLogin\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Router\Route;
use JoomlaOidc\Component\OidcClient\Administrator\Extension\OidcclientComponent;

final class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * @return array<string, mixed>|false
     */
    protected function getLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        if ($data === false) {
            return false;
        }

        $component = $data['app']->bootComponent('com_oidcclient');

        if (
            !$component instanceof OidcclientComponent
            || !$component->isEnabled()
            || !$component->isConfigured()
        ) {
            return false;
        }

        $data['buttonText'] = (string) $data['params']->get('button_text', 'Entrar com GOV.BR');
        $data['loginUrl'] = Route::_(
            'index.php?option=com_oidcclient&task=login.start&return=' . rawurlencode('/'),
            false,
        );
        $data['logoutUrl'] = Route::_('index.php?option=com_oidcclient&task=logout.start', false);
        $data['user'] = $data['app']->getIdentity();
        $webAssetManager = $data['app']->getDocument()->getWebAssetManager();
        $webAssetManager->getRegistry()->addExtensionRegistryFile('mod_oidc_login');
        $webAssetManager->useStyle('mod_oidc_login.style');

        return $data;
    }
}
