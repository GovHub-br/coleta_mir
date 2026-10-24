<?php

declare(strict_types=1);

namespace JoomlaOidc\Component\OidcClient\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use JoomlaOidc\Component\OidcClient\Administrator\Model\IdentitylinkModel;

final class IdentitylinkController extends FormController
{
    protected $text_prefix = 'COM_OIDCCLIENT';
    protected $view_list = 'identitylinks';

    /**
     * @param array<string, mixed> $data
     */
    protected function allowEdit($data = [], $key = 'id'): bool
    {
        return $this->app->getIdentity()?->authorise('core.admin', 'com_oidcclient') ?? false;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function allowAdd($data = []): bool
    {
        return $this->app->getIdentity()?->authorise('core.admin', 'com_oidcclient') ?? false;
    }

    public function delete(?string $key = null): bool
    {
        $this->checkToken();
        $this->setRedirect(
            Route::_('index.php?option=com_oidcclient&view=identitylinks', false),
        );

        $model = $this->getModel();

        if (!$model instanceof IdentitylinkModel) {
            throw new \RuntimeException('Could not load the identity link model.');
        }

        $key = $key ?: $model->getTable()->getKeyName();
        $recordId = $this->input->getInt($key);

        if ($recordId <= 0 || !$this->allowEdit([$key => $recordId], $key)) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 'error');

            return false;
        }

        $recordIds = [$recordId];

        if (!$model->delete($recordIds)) {
            $this->setMessage(Text::_('COM_OIDCCLIENT_DELETE_FAILED'), 'error');

            return false;
        }

        $this->setMessage(Text::_('COM_OIDCCLIENT_DELETE_SUCCESS'));

        return true;
    }

    public function cancel($key = null): bool
    {
        $cancelled = parent::cancel($key);

        $this->setRedirect(
            Route::_('index.php?option=com_oidcclient&view=identitylinks', false),
        );

        return $cancelled;
    }
}
