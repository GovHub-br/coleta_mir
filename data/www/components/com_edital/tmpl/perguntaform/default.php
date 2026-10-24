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

	<?php if (!empty($this->editalTemAdesoes)) : ?>
		<div class="alert alert-warning">
			<?php echo Text::_('COM_EDITAL_WARNING_EDITAL_HAS_ADESOES'); ?>
		</div>
	<?php endif; ?>

	<form action="<?php echo Route::_($action); ?>"
		  method="post" enctype="multipart/form-data" name="adminForm" id="adminForm"
		  class="form-validate com-edital-cadastro-form">

		<?php echo LayoutHelper::render('cadastro.form_buttons', [
			'canDo'          => $this->canDo,
			'formController' => $this->formController,
			'checkedOut'     => $this->checkedOut,
		], $layoutPath); ?>

		<?php echo HTMLHelper::_('uitab.startTabSet', 'perguntaTab', ['active' => 'dados']); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'perguntaTab', 'dados', Text::_('COM_EDITAL_CADASTRO_TAB_DADOS')); ?>
		<fieldset>
			<?php echo $this->form->renderField('ds_titulo'); ?>
			<?php echo $this->form->renderField('co_edital'); ?>
			<?php echo $this->form->renderField('co_agrupamento'); ?>
			<?php echo $this->form->renderField('tp_requerida'); ?>
			<?php echo $this->form->renderField('tp_pergunta'); ?>
			<?php echo $this->form->renderField('state'); ?>
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'perguntaTab', 'opcoes', Text::_('COM_EDITAL_CADASTRO_TAB_OPCOES')); ?>
		<fieldset>
			<?php echo $this->form->renderField('ds_opcoes'); ?>
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'perguntaTab', 'complementos', Text::_('COM_EDITAL_CADASTRO_TAB_COMPLEMENTOS')); ?>
		<fieldset>
			<?php echo $this->form->renderField('ds_subtitulo'); ?>
			<?php echo $this->form->renderField('ds_descricao'); ?>
			<?php echo $this->form->renderField('ds_informacao_complementar'); ?>
			<?php echo $this->form->renderField('ds_informacao_complementar2'); ?>
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'perguntaTab', 'configuracoes', Text::_('COM_EDITAL_CADASTRO_TAB_CONFIGURACOES')); ?>
		<fieldset>
			<?php echo $this->form->renderField('depende_de'); ?>
			<?php echo $this->form->renderField('depende_de_valor'); ?>
			<hr class="pergunta-config-separador">
			<?php echo $this->form->renderField('depende_de_ocultar'); ?>
			<?php echo $this->form->renderField('depende_de_ocultar_valor'); ?>
			<hr class="pergunta-config-separador">
			<?php echo $this->form->renderField('tp_etapa'); ?>
			<hr class="pergunta-config-separador">
			<?php echo $this->form->renderField('qt_dias_nao_aplica'); ?>
			<?php echo $this->form->renderField('qt_dias_complementacao'); ?>
		</fieldset>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

		<?php echo $this->form->renderField('id'); ?>
		<?php echo $this->form->renderField('created_by'); ?>
		<?php echo $this->form->renderField('modified_by'); ?>

		<input type="hidden" name="task" value=""/>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
