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
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

$wa = $this->document->getWebAssetManager();
$wa->useScript('core')
	->useScript('multiselect')
	->useScript('list-view')
	->useStyle('com_edital.cadastro')
	->useScript('com_edital.cadastro');

$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$saveOrder = $listOrder == 'a.ordering';
$canEdit   = $this->canDo->get('core.edit');
$canChange = $this->canDo->get('core.edit.state');
$layoutPath = JPATH_SITE . '/components/com_edital/layouts';

if ($saveOrder)
{
	$saveOrderingUrl = 'index.php?option=com_edital&task=' . $this->listView . '.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
	HTMLHelper::_('draggablelist.draggable');
}
?>
<div class="com-edital-cadastro">
	<?php if ($this->params->get('show_page_heading', 1)) : ?>
		<h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
	<?php endif; ?>

	<form action="<?php echo Route::_(CadastroHelper::getViewUrl($this->listView)); ?>"
		  method="post" name="adminForm" id="adminForm"
		  data-confirm-delete="<?php echo $this->escape(Text::_('COM_EDITAL_DELETE_MESSAGE')); ?>">

		<?php echo LayoutHelper::render('cadastro.toolbar', [
			'canDo'          => $this->canDo,
			'listController' => $this->listView,
			'formController' => $this->formController,
			'filterState'    => $this->state->get('filter.state'),
		], $layoutPath); ?>

		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

		<?php if (empty($this->items)) : ?>
			<div class="alert alert-info">
				<?php echo Text::_('COM_EDITAL_NO_ITEMS'); ?>
				<?php if ($this->canDo->get('core.create')) : ?>
					<a href="<?php echo Route::_(CadastroHelper::getFormUrl($this->formController, $this->listView)); ?>" class="btn btn-sm btn-primary ms-2">
						<?php echo Text::_('JTOOLBAR_NEW'); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="table-responsive">
				<table class="table table-striped" id="perguntaList">
					<thead>
					<tr>
						<th class="w-1 text-center">
							<input type="checkbox" autocomplete="off" class="form-check-input js-grid-item-check-all" name="checkall-toggle" value=""
								   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"/>
						</th>
						<?php if (isset($this->items[0]->ordering)) : ?>
							<th scope="col" class="w-1 text-center d-none d-md-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
							</th>
						<?php endif; ?>
						<th scope="col" class="w-1 text-center">
							<?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
						</th>
						<th><?php echo HTMLHelper::_('searchtools.sort', 'COM_EDITAL_PERGUNTAS_CO_EDITAL', 'a.co_edital', $listDirn, $listOrder); ?></th>
						<th><?php echo Text::_('COM_EDITAL_PERGUNTAS_CO_AGRUPAMENTO'); ?></th>
						<th><?php echo HTMLHelper::_('searchtools.sort', 'COM_EDITAL_PERGUNTAS_DS_TITULO', 'a.ds_titulo', $listDirn, $listOrder); ?></th>
						<th><?php echo HTMLHelper::_('searchtools.sort', 'COM_EDITAL_PERGUNTAS_TP_PERGUNTA', 'a.tp_pergunta', $listDirn, $listOrder); ?></th>
						<th><?php echo HTMLHelper::_('searchtools.sort', 'COM_EDITAL_PERGUNTAS_TP_REQUERIDA', 'a.tp_requerida', $listDirn, $listOrder); ?></th>
						<th><?php echo HTMLHelper::_('searchtools.sort', 'COM_EDITAL_PERGUNTAS_TP_ETAPA', 'a.tp_etapa', $listDirn, $listOrder); ?></th>
					</tr>
					</thead>
					<tbody <?php echo $saveOrder ? 'class="js-draggable" data-url="' . $saveOrderingUrl . '" data-direction="' . strtolower($listDirn) . '"' : ''; ?>>
					<?php foreach ($this->items as $i => $item) : ?>
						<tr class="row<?php echo $i % 2; ?>" data-draggable-group="1">
							<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
							<?php if (isset($this->items[0]->ordering)) : ?>
								<td class="text-center d-none d-md-table-cell">
									<span class="sortable-handler<?php echo $canChange && $saveOrder ? '' : ' inactive'; ?>">
										<span class="icon-ellipsis-v" aria-hidden="true"></span>
									</span>
									<?php if ($canChange && $saveOrder) : ?>
										<input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden">
									<?php endif; ?>
								</td>
							<?php endif; ?>
							<td class="text-center">
								<?php echo HTMLHelper::_('jgrid.published', $item->state, $i, $this->listView . '.', $canChange, 'cb'); ?>
							</td>
							<td><?php echo $this->escape($item->co_edital); ?></td>
							<td><?php echo $this->escape($item->agrupamento_fk_value ?? ''); ?></td>
							<td>
								<?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
									<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, $this->listView . '.', $this->canDo->get('core.manage')); ?>
								<?php endif; ?>
								<?php if ($canEdit) : ?>
									<a href="<?php echo Route::_(CadastroHelper::getFormUrl($this->formController, $this->listView, (int) $item->id)); ?>">
										<?php echo $this->escape($item->ds_titulo); ?>
									</a>
								<?php else : ?>
									<?php echo $this->escape($item->ds_titulo); ?>
								<?php endif; ?>
							</td>
							<td><?php echo $this->escape($item->tp_pergunta); ?></td>
							<td><?php echo $this->escape($item->tp_requerida); ?></td>
							<td><?php echo $this->escape($item->tp_etapa); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php echo $this->pagination->getListFooter(); ?>
		<?php endif; ?>

		<input type="hidden" name="task" value=""/>
		<input type="hidden" name="boxchecked" value="0"/>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
