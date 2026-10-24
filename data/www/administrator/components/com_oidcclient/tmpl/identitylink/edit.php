<?php

declare(strict_types=1);

/**
 * @var \JoomlaOidc\Component\OidcClient\Administrator\View\Identitylink\HtmlView $this
 */

defined('_JEXEC') or die;

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>

<form action="<?php echo Route::_('index.php?option=com_oidcclient&view=identitylink&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
      method="post" name="adminForm" id="identitylink-form" class="form-validate">

    <?php echo $this->form->renderFieldset('default'); ?>

    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="task" value="">
    <input type="hidden" name="id" value="<?php echo (int) ($this->item->id ?? 0); ?>">
</form>
