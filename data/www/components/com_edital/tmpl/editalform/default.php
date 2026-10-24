<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate')
	->useStyle('com_edital.cadastro')
	->useScript('com_edital.cadastro');

HTMLHelper::_('bootstrap.tooltip');

$id         = (int) ($this->item->id ?? 0);
$layoutPath = JPATH_SITE . '/components/com_edital/layouts';
$action     = CadastroHelper::getFormUrl($this->formController, $this->listView, $id);
?>
<div class="com-edital-cadastro">
	<h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>

	<form action="<?php echo Route::_($action); ?>"
		  method="post" enctype="multipart/form-data" name="adminForm" id="adminForm"
		  class="form-validate com-edital-cadastro-form">

		<?php echo LayoutHelper::render('cadastro.form_buttons', [
			'canDo'          => $this->canDo,
			'formController' => $this->formController,
			'checkedOut'     => $this->checkedOut,
		], $layoutPath); ?>

		<fieldset>
			<legend><?php echo Text::_('COM_EDITAL_FIELDSET_EDITAL'); ?></legend>
			<?php echo $this->form->renderField('no_edital'); ?>
			<?php echo $this->form->renderField('ds_edital'); ?>
			<?php echo $this->form->renderField('dt_abertura'); ?>
			<?php echo $this->form->renderField('dt_encerramento'); ?>
			<?php echo $this->form->renderField('dt_publicacao'); ?>
			<?php echo $this->form->renderField('co_edital_municipio_consorcio'); ?>
			<?php echo $this->form->renderField('co_edital_adesao'); ?>
			<?php echo $this->form->renderField('tp_edital'); ?>
			<?php echo $this->form->renderField('tp_esfera_solicitante'); ?>
			<?php echo $this->form->renderField('state'); ?>
		</fieldset>

		<?php echo $this->form->renderField('id'); ?>
		<?php echo $this->form->renderField('created_by'); ?>
		<?php echo $this->form->renderField('modified_by'); ?>

		<input type="hidden" name="task" value=""/>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
