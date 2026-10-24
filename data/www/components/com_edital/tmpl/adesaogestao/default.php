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

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

$canEdit = Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_edital.' . $this->item->id);

if (!$canEdit && Factory::getApplication()->getIdentity()->authorise('core.edit.own', 'com_edital' . $this->item->id)) {
	$canEdit = Factory::getApplication()->getIdentity()->id == $this->item->created_by;
}

$baseUrlRespostas = Route::_('index.php?option=com_edital&view=adesaorespostas&Itemid=' . $this->respostaMenuID);
$listLimit = AdesaoHelper::getStoredListLimit('com_edital.adesaogestao.list.limit');
$listLimitLabel = $listLimit === 0 ? Text::_('JALL') : Text::_('J' . $listLimit);

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css', ['version' => 'auto']);

// add custom js
$this->document->addScript(Uri::root() . 'media/com_edital/js/adesaogestao.js', ['version' => 'auto']);

// add inicializacao de js
$this->document->addScriptDeclaration("
	document.addEventListener('DOMContentLoaded', function() {
		Adesaogestao.baseUrlRespostas = '" . $baseUrlRespostas . "';
		Adesaogestao.exportPdfUrl = '" . Route::_('index.php?option=com_edital&task=adesaogestao.exportarpdf&' . Session::getFormToken() . '=1', false) . "';
		Adesaogestao.init();
	});
");

// include __DIR__ . '/../parts/_menu_formularios_gestao.php';

?>

<div class="edital-dashboard">

	<div class="row">
		<div class="col d-flex justify-content-end mb-4">
			<a href="<?php echo Route::_('index.php?option=com_edital&view=formulariogestao'); ?>" class="btn btn-primary">
				Gestão de Formulários <i class="fas fa-arrow-right"></i>
			</a>
		</div>
	</div>

	<!-- Stats Row -->
	<div class="stats-row">

		<button type="button" class="stat-card" data-situacao="" aria-pressed="true" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Total</span>
				<span class="stat-number num-dark"><?php echo $this->estatisticas->total ?></span>
			</span>
			<span class="stat-icon icon-gray">
				<i aria-hidden="true" class="fas fa-chart-bar"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-situacao="pendente" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Em análise</span>
				<span class="stat-number num-orange"><?php echo $this->estatisticas->pendente ?? 0 ?></span>
			</span>
			<span class="stat-icon icon-peach">
				<i aria-hidden="true" class="far fa-clock"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-situacao="complementacao" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Em Complementação</span>
				<span class="stat-number num-orange"><?php echo $this->estatisticas->complementacao ?? 0 ?></span>
			</span>
			<span class="stat-icon icon-orange">
				<i aria-hidden="true" class="far fa-comment-alt"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-situacao="deferido" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Deferidos</span>
				<span class="stat-number num-green"><?php echo $this->estatisticas->deferido ?? 0 ?></span>
			</span>
			<span class="stat-icon icon-green">
				<i aria-hidden="true" class="far fa-check-circle"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-situacao="indeferido" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Indeferidos</span>
				<span class="stat-number num-red"><?php echo $this->estatisticas->indeferido ?? 0 ?></span>
			</span>
			<span class="stat-icon icon-red">
				<i aria-hidden="true" class="far fa-times-circle"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-situacao="recurso" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label">Recursos</span>
				<span class="stat-number num-orange"><?php echo $this->estatisticas->recurso ?? 0 ?></span>
			</span>
			<span class="stat-icon icon-orange">
				<i aria-hidden="true" class="fas fa-gavel"></i>
			</span>
		</button>

		<!-- <div class="stat-card">
			<div class="stat-info">
				<span class="stat-label">Tempo médio</span>
				<span class="stat-number num-dark"><?php echo $this->estatisticas->tempo_medio ?? 0 ?></span>
			</div>
			<div class="stat-icon icon-gray">
				<i class="far fa-clock"></i>
				<span class="stat-badge">?</span>
			</div>
		</div> -->

	</div><!-- /.stats-row -->

	<form id="formBuscaAdesao">
		<!-- Search + Filter Row -->
		<div class="search-filter-row">
			<div class="search-wrapper">
				<i class="fas fa-search search-icon"></i>
				<input type="text" id="inputBuscaAdesao" name="busca" class="search-input" placeholder="Buscar por nome ou protocolo... (resultado imediato ao digitar)" />
			</div>
			<div class="filter-btn">
				<!-- <i class="fas fa-filter filter-icon"></i>
      <span class="filter-label">Todas as situações</span>
      <i class="fas fa-chevron-up filter-arrow"></i> -->
				<div class="control-dropdown-wrapper">
					<div class="control-dropdown">
						<i class="fas fa-filter filter-icon"></i>
						<span>Todas as situações</span>
						<i class="fas fa-chevron-down"></i>
					</div>
					<select class="control-dropdown-select" name="situacao" id="selectSituacao">
						<option value="">Todos</option>
						<option value="pendente">Em Análise</option>
						<option value="complementacao">Em Complementação</option>
						<option value="assinatura">Aguardando Assinatura</option>
						<option value="deferido">Deferido</option>
						<option value="indeferido">Indeferido</option>
						<option value="recurso">Recurso</option>
					</select>
				</div>
			</div>
		</div><!-- /.search-filter-row -->

		<!-- Controls Row -->
		<div class="controls-row">
			<div class="control-dropdown-wrapper">
				<div class="control-dropdown">
					<span>Exibir Entes</span>
					<i class="fas fa-chevron-down"></i>
				</div>
				<select class="control-dropdown-select" name="ente" id="selectEnte">
					<option value="">Todos</option>
					<option value="municipal">Municípios</option>
					<option value="estadual">Estados</option>
					<option value="consorcio">Consórcios/Associações</option>
				</select>
			</div>
			<div class="control-dropdown-wrapper">
				<div class="control-dropdown">
					<span>Tipo de solicitação</span>
					<i class="fas fa-chevron-down"></i>
				</div>
				<select class="control-dropdown-select" name="tipo" id="selectTipo">
					<option value="">Todas</option>
					<option value="migracao">Migração</option>
					<option value="nova_adesao">Nova adesão</option>
				</select>
			</div>
			<div class="control-dropdown-wrapper">
				<div class="control-dropdown">
					<span>Organizar por</span>
					<i class="fas fa-chevron-down"></i>
				</div>
				<select class="control-dropdown-select" name="ordem" id="selectOrdem">
					<option value="">Mais Recentes</option>
					<option value="dt_criacaoasc">Mais Antigos</option>
				</select>
			</div>
			<div class="control-limit-group">
				<div class="control-dropdown-wrapper">
					<div class="control-dropdown">
						<?php echo Text::_('COM_EDITAL_LIST_LIMIT'); ?>:
						<span><?php echo $listLimitLabel; ?></span>
						<i class="fas fa-chevron-down"></i>
					</div>
					<select class="control-dropdown-select" name="limit" id="selectLimit">
						<?php foreach (AdesaoHelper::getListLimits() as $limitOption) : ?>
							<option value="<?php echo (int) $limitOption; ?>"<?php echo $limitOption === $listLimit ? ' selected' : ''; ?>><?php echo $limitOption === 0 ? Text::_('JALL') : Text::_('J' . $limitOption); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<span id="limitLoader" class="spinner-border spinner-border-sm limit-loader" hidden role="status"></span>
			</div>
			<button type="button" id="btnExportarPdf" class="btn-exportar-pdf">
				<i class="fas fa-file-pdf"></i> <?php echo Text::_('COM_EDITAL_EXPORTAR_PDF'); ?>
			</button>
		</div><!-- /.controls-row -->
	</form>

</div><!-- /.edital-dashboard -->


<div id="adesaoList">

</div>
<br/>
<br/>

<?php
// Incluir modal de indeferimento
include JPATH_COMPONENT . '/tmpl/_modals/indeferir.php';

// Adicionar script do modal
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-indeferir.js');

// Incluir modal de deferimento
include JPATH_COMPONENT . '/tmpl/_modals/deferir.php';
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-deferir.js');

// Incluir modal de complementação
include JPATH_COMPONENT . '/tmpl/_modals/complementacao.php';
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-complementacao.js');

// Incluir modal de modalidade
include JPATH_COMPONENT . '/tmpl/_modals/modalidade.php';
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-modalidade.js');
?>