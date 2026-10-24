<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate')
	->useStyle('com_edital.admin')
	->useScript('com_edital.cadastro');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
	action="<?php echo Route::_('index.php?option=com_edital&layout=edit&id=' . (int) $this->item->id); ?>"
	method="post" enctype="multipart/form-data" name="adminForm" id="pergunta-form" class="form-validate form-horizontal">

	
	<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'pergunta')); ?>
	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'pergunta', Text::_('COM_EDITAL_TAB_PERGUNTA', true)); ?>
	<div class="row-fluid">
		<div class="col-md-12 form-horizontal">
			<fieldset class="adminform">
				<?php echo $this->form->renderField('ds_titulo'); ?>
				<?php echo $this->form->renderField('co_edital'); ?>
				<?php echo $this->form->renderField('co_agrupamento'); ?>
				<?php echo $this->form->renderField('tp_requerida'); ?>
				<?php echo $this->form->renderField('tp_pergunta'); ?>
				<?php echo $this->form->renderField('ds_opcoes'); ?>
				<?php if ($this->state->params->get('save_history', 1)) : ?>
					<div class="control-group">
						<div class="control-label"><?php echo $this->form->getLabel('version_note'); ?></div>
						<div class="controls"><?php echo $this->form->getInput('version_note'); ?></div>
					</div>
				<?php endif; ?>
			</fieldset>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>
	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'complementos', Text::_('COM_EDITAL_TAB_COMPLEMENTOS', true)); ?>
	<div class="row-fluid">
		<div class="col-md-12 form-horizontal">
			<fieldset class="adminform">
				<?php echo $this->form->renderField('ds_subtitulo'); ?>
				<?php echo $this->form->renderField('ds_descricao'); ?>
				<?php echo $this->form->renderField('ds_informacao_complementar'); ?>
				<?php echo $this->form->renderField('ds_informacao_complementar2'); ?>
			</fieldset>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>
	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'configuracoes', Text::_('COM_EDITAL_TAB_CONFIGURACOES', true)); ?>
	<div class="row-fluid">
		<div class="col-md-12 form-horizontal">
			<fieldset class="adminform">
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
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>
	<input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

	<input type="hidden" name="jform[state]" value="<?php echo isset($this->item->state) ? $this->item->state : ''; ?>" />

	<?php echo $this->form->renderField('created_by'); ?>
	<?php echo $this->form->renderField('modified_by'); ?>

	
	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<input type="hidden" name="task" value=""/>
	<?php echo HTMLHelper::_('form.token'); ?>

</form>
