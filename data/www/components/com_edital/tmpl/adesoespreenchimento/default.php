<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Victor Gonçalves Bento <vgb.info@gmail.com>
 * @copyright  2026 Victor Gonçalves Bento
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css', ['version' => 'auto']);
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesoespreenchimento.css');
$this->document->addScript(Uri::root() . 'media/com_edital/js/adesoespreenchimento.js', ['version' => 'auto']);

$this->document->addScriptDeclaration("
	document.addEventListener('DOMContentLoaded', function() {
		Adesoespreenchimento.exportPdfUrl = '" . Route::_('index.php?option=com_edital&task=adesoespreenchimento.exportarpdf&' . Session::getFormToken() . '=1', false) . "';
		Adesoespreenchimento.init();
	});
");

$stats = $this->estatisticas;
$listLimit = AdesaoHelper::getStoredListLimit('com_edital.adesoespreenchimento.list.limit');
$listLimitLabel = $listLimit === 0 ? Text::_('JALL') : Text::_('J' . $listLimit);
?>

<div class="edital-dashboard adesoes-preenchimento">

	<p class="preenchimento-intro">
		<?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_INTRO'); ?>
	</p>

	<div class="stats-row">

		<button type="button" class="stat-card" data-total="true" aria-pressed="true" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_TOTAL'); ?></span>
				<span class="stat-number num-dark"><?php echo (int) ($stats->total ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-gray">
				<i aria-hidden="true" class="far fa-edit"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-ente="municipal" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_MUNICIPIOS'); ?></span>
				<span class="stat-number num-orange"><?php echo (int) ($stats->municipal ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-peach">
				<i aria-hidden="true" class="fas fa-city"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-ente="estadual" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_ESTADOS'); ?></span>
				<span class="stat-number num-orange"><?php echo (int) ($stats->estadual ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-orange">
				<i aria-hidden="true" class="fas fa-map-marker-alt"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-ente="consorcio" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_CONSORCIOS'); ?></span>
				<span class="stat-number num-dark"><?php echo (int) ($stats->consorcio ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-gray">
				<i aria-hidden="true" class="fas fa-handshake"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-movimento="recentes" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_RECENTES'); ?></span>
				<span class="stat-number num-green"><?php echo (int) ($stats->recentes ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-green">
				<i aria-hidden="true" class="far fa-clock"></i>
			</span>
		</button>

		<button type="button" class="stat-card" data-movimento="paradas" aria-pressed="false" aria-controls="adesaoList">
			<span class="stat-info">
				<span class="stat-label"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_PARADAS'); ?></span>
				<span class="stat-number num-red"><?php echo (int) ($stats->paradas ?? 0); ?></span>
			</span>
			<span class="stat-icon icon-red">
				<i aria-hidden="true" class="far fa-pause-circle"></i>
			</span>
		</button>

	</div>

	<form id="formBuscaAdesao">
		<div class="search-filter-row">
			<div class="search-wrapper">
				<i class="fas fa-search search-icon"></i>
				<input type="text" id="inputBuscaAdesao" name="busca" class="search-input" placeholder="<?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_BUSCA_PLACEHOLDER'); ?>" />
			</div>
		</div>

		<div class="controls-row">
            <div class="control-dropdown-wrapper">
                <div class="control-dropdown">
                    <span><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_MOVIMENTO'); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <select class="control-dropdown-select" name="movimento" id="selectMovimento" aria-label="<?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_MOVIMENTO'); ?>">
                    <option value=""><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_TODOS'); ?></option>
                    <option value="recentes"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_RECENTES'); ?></option>
                    <option value="paradas"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_STAT_PARADAS'); ?></option>
                </select>
            </div>
			<div class="control-dropdown-wrapper">
				<div class="control-dropdown">
					<span><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_ENTES'); ?></span>
					<i class="fas fa-chevron-down"></i>
				</div>
				<select class="control-dropdown-select" name="ente" id="selectEnte">
					<option value=""><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_TODOS'); ?></option>
					<option value="municipal"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_MUNICIPIOS'); ?></option>
					<option value="estadual"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_ESTADOS'); ?></option>
					<option value="consorcio"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_FILTRO_CONSORCIOS'); ?></option>
				</select>
			</div>
			<div class="control-dropdown-wrapper">
				<div class="control-dropdown">
					<span><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDENAR'); ?></span>
					<i class="fas fa-chevron-down"></i>
				</div>
				<select class="control-dropdown-select" name="ordem" id="selectOrdem">
					<option value=""><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDEM_RECENTES'); ?></option>
					<option value="dt_criacaoasc"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDEM_ANTIGOS'); ?></option>
					<option value="preenchimento_desc"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDEM_MAIOR'); ?></option>
					<option value="preenchimento_asc"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDEM_MENOR'); ?></option>
					<option value="no_oficial"><?php echo Text::_('COM_EDITAL_ADESOESPREENCHIMENTO_ORDEM_NOME'); ?></option>
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
		</div>
	</form>

</div>

<div id="adesaoList"></div>
<br/>
<br/>
