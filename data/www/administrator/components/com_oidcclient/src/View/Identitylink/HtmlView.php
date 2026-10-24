<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\View\Identitylink;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    protected \Joomla\CMS\Form\Form $form;
    protected \stdClass $item;

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        $isNew = ((int) ($this->item->id ?? 0)) === 0;

        ToolbarHelper::title(
            Text::_($isNew ? 'COM_OIDCCLIENT_HEADER_NEW_LINK' : 'COM_OIDCCLIENT_HEADER_EDIT_LINK'),
            'link',
        );

        ToolbarHelper::save('identitylink.save');

        if (!$isNew) {
            ToolbarHelper::deleteList('', 'identitylink.delete');
        }

        ToolbarHelper::cancel('identitylink.cancel');
    }
}
