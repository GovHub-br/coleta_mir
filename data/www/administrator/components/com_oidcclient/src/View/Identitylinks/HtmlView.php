<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\View\Identitylinks;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_OIDCCLIENT_HEADER_IDENTITY_LINKS'), 'link');

        ToolbarHelper::addNew('identitylink.add');

        ToolbarHelper::link(
            'index.php?option=com_oidcclient&view=dashboard',
            Text::_('COM_OIDCCLIENT_DASHBOARD'),
        );
    }
}
