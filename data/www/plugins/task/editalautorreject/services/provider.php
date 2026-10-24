<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.Editalautorreject
 *
 * @copyright   2026 SINAPIR
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\Editalautorreject\Extension\Editalautorreject;
use Comedital\Component\Edital\Site\Service\AdesaoEmailService;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            AdesaoEmailService::class,
            function (Container $container) {
                return new AdesaoEmailService();
            }
        );

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Editalautorreject(
                    (array) PluginHelper::getPlugin('task', 'editalautorreject')
                );
                $plugin->setDatabase($container->get(DatabaseInterface::class));

                return $plugin;
            }
        );
    }
};