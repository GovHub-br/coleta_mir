<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use JoomlaOidc\Plugin\System\OidcClient\Extension\OidcClientSystemPlugin;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(OidcClientSystemPlugin::class, function (Container $container) {
                $params = ComponentHelper::getParams('com_oidcclient');
                $application = Factory::getApplication();
                $plugin = new OidcClientSystemPlugin(
                    (array) PluginHelper::getPlugin('system', 'oidcclient'),
                    $application,
                    $application instanceof SiteApplication ? $application->getSession() : null,
                    $container->get(UserFactoryInterface::class),
                    (int) $params->get('ente_group_id', 11),
                    (bool) $params->get('enabled', true),
                );

                $plugin->setApplication($application);

                return $plugin;
            }),
        );
    }
};
