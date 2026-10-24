<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\View\Dashboard;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use JoomlaOidc\Component\OidcClient\Administrator\Model\DashboardModel;

final class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    protected array $dashboardData = [];
    protected bool $canConfigure = false;

    public function display($tpl = null): void
    {
        $model = $this->getModel();

        if (!$model instanceof DashboardModel) {
            throw new \RuntimeException('Dashboard model is unavailable.');
        }

        $this->dashboardData = $model->getDashboardData();
        $this->canConfigure = $this->getCurrentUser()->authorise('core.admin', 'com_oidcclient');

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_OIDCCLIENT_HEADER_DASHBOARD'), 'dashboard');

        ToolbarHelper::link(
            'index.php?option=com_oidcclient&view=identitylinks',
            Text::_('COM_OIDCCLIENT_IDENTITY_LINKS'),
        );

        if ($this->canConfigure) {
            ToolbarHelper::preferences('com_oidcclient');
        }
    }
}
