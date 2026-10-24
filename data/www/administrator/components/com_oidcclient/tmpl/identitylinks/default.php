<?php

declare(strict_types=1);

/**
 * @var \JoomlaOidc\Component\OidcClient\Administrator\View\Identitylinks\HtmlView $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$items      = $this->get('Items');
$pagination = $this->get('Pagination');
?>

<form action="<?php echo Route::_('index.php?option=com_oidcclient&view=identitylinks'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if (count($items) === 0): ?>
        <div class="alert alert-info">
            <?php echo Text::_('COM_OIDCCLIENT_NO_IDENTITY_LINKS'); ?>
        </div>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_ID'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_PROVIDER_KEY'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_ISSUER'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_SUBJECT'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_USER_ID'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_CREATED_AT'); ?></th>
                    <th><?php echo Text::_('COM_OIDCCLIENT_ID_LINK_LAST_LOGIN_AT'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo (int) $item->id; ?></td>
                        <td><?php echo $this->escape($item->provider_key); ?></td>
                        <td><?php echo $this->escape($item->issuer); ?></td>
                        <td><?php
                            $sub = $item->subject ?? '';
                            echo $this->escape($sub !== '' ? '****' . substr($sub, -4) : Text::_('COM_OIDCCLIENT_ID_LINK_SUBJECT_MASKED'));
                        ?></td>
                        <td><?php echo (int) $item->user_id; ?></td>
                        <td><?php echo $this->escape($item->created_at ?? '-'); ?></td>
                        <td><?php echo $this->escape($item->last_login_at ?? '-'); ?></td>
                        <td>
                            <a href="<?php echo Route::_('index.php?option=com_oidcclient&task=identitylink.edit&id=' . (int) $item->id); ?>" class="btn btn-sm btn-primary">
                                <?php echo Text::_('COM_OIDCCLIENT_EDIT'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $pagination->getListFooter(); ?>
    <?php endif; ?>

    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
</form>
