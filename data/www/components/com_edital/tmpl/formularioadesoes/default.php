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

// Mantém o item de menu atual (Gestão de Formulários) para a tela de respostas ter título e breadcrumb
$activeMenu = Factory::getApplication()->getMenu()->getActive();
$baseUrlRespostas = Route::_('index.php?option=com_edital&view=formulariosrespostas' . ($activeMenu ? '&Itemid=' . (int) $activeMenu->id : ''), false);
$listLimit = AdesaoHelper::getStoredListLimit('com_edital.adesaogestao.list.limit');
$listLimitLabel = $listLimit === 0 ? Text::_('JALL') : Text::_('J' . $listLimit);

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css', ['version' => 'auto']);
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularioadesoes.css', ['version' => 'auto']);

// add custom js
$this->document->addScript(Uri::root() . 'media/com_edital/js/formularioadesoes.js', ['version' => 'auto']);

// add inicializacao de js
$this->document->addScriptDeclaration("
	document.addEventListener('DOMContentLoaded', function() {
		Formularioadesoes.baseUrl = '" . Uri::root() . "';
		Formularioadesoes.baseUrlRespostas = '" . $baseUrlRespostas . "';
		Formularioadesoes.exportPdfUrl = '" . Route::_('index.php?option=com_edital&task=formularioadesoes.exportarpdf&' . Session::getFormToken() . '=1', false) . "';
		Formularioadesoes.init();
	});
");

include __DIR__ . '/../parts/_menu_formularios_gestao.php';

?>
<a href="<?php echo Route::_('index.php?view=formulariogestao'); ?>" class="btn-voltar">
	<i class="fas fa-arrow-left"></i> Voltar
</a>

<div class="edital-dashboard">


	<form id="formBuscaAdesao">
		<input type="hidden" name="<?php echo Session::getFormToken(); ?>" value="1" />
		<input type="hidden" name="id_edital" value="<?php echo $this->item->id; ?>" />
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
						<option value="pendente">Enviado</option>
						<option value="rascunho">Em Preenchimento</option>
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
		</div><!-- /.controls-row -->
	</form>

</div><!-- /.edital-dashboard -->


<div id="adesaoList">

</div>
<br/>
<br/>
