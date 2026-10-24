<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Victor Gonçalves Bento <vgb.info@gmail.com>
 * @copyright  2026 Victor Gonçalves Bento
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Model\AdesoespreenchimentoModel;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Controller for adesões still being filled.
 *
 * @since  1.0.0
 */
class AdesoespreenchimentoController extends BaseController
{
	public function buscar()
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('respostas.manage', 'com_edital')) {
			header('Content-Type: application/json');
			echo json_encode([
				'success' => false,
				'message' => Text::_('JERROR_ALERTNOAUTHOR'),
			]);
			$app->close();

			return;
		}

		$list = $this->loadFilteredList();

		header('Content-Type: application/json');
		echo json_encode(['items' => $list['items'], 'total' => $list['total']]);
		$app->close();
	}

	public function exportarpdf()
	{
		$this->checkToken('GET');

		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('respostas.manage', 'com_edital')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
		}

		@set_time_limit(120);

		$list = $this->loadFilteredList(0, 0);
		$itemsHtml = '';

		foreach ($list['items'] as $item) {
			$itemsHtml .= AdesaoHelper::renderPreenchimentoPdfItem($item);
		}

		if ($itemsHtml === '') {
			$itemsHtml = '<p>Nenhuma adesão em preenchimento encontrada.</p>';
		}

		$title = Text::_('COM_EDITAL_TITLE_VIEW_ADESOESPREENCHIMENTO');
		$html  = AdesaoHelper::renderListReportHtml(
			$title,
			Text::sprintf('COM_EDITAL_RELATORIO_TOTAL', $list['total']),
			$itemsHtml,
			AdesaoHelper::renderPreenchimentoStatsHtml((new AdesoespreenchimentoModel())->getEstatisticas())
		);

		AdesaoHelper::downloadHtmlPdf(
			$title,
			$html,
			'Relatorio_Adesoes_Preenchimento_' . date('Ymd_His') . '.pdf'
		);
	}

	private function loadFilteredList(?int $limit = null, ?int $start = null): array
	{
		$app        = Factory::getApplication();
		$input      = $app->input;
		$searchTerm = $input->get('busca', '', 'STRING');
		$ente       = $input->get('ente', '', 'STRING');
		$movimento  = $input->get('movimento', '', 'CMD');
		$ordem      = $input->get('ordem', '', 'STRING');

		if ($limit === null) {
			$limit = AdesaoHelper::storeListLimit(
				'com_edital.adesoespreenchimento.list.limit',
				$input->getInt('limit', 10)
			);
			$start = $limit > 0 ? max(0, $input->getInt('start', 0)) : 0;
		}

		switch ($ordem) {
			case 'dt_criacaoasc':
				$campoOrdem = 'dt_criacao';
				$direcao    = 'ASC';
				break;
			case 'preenchimento_desc':
				$campoOrdem = 'preenchimento';
				$direcao    = 'DESC';
				break;
			case 'preenchimento_asc':
				$campoOrdem = 'preenchimento';
				$direcao    = 'ASC';
				break;
			case 'no_oficial':
				$campoOrdem = 'no_oficial';
				$direcao    = 'ASC';
				break;
			default:
				$campoOrdem = 'dt_atualizacao';
				$direcao    = 'DESC';
				break;
		}

		$model  = new AdesoespreenchimentoModel();
		$total  = $model->count($searchTerm, $ente, $movimento);
		$result = $model->buscar($searchTerm, $ente, $campoOrdem, $direcao, $limit, $start, $movimento);

		foreach ($result as $item) {
			$item->dt_criacao_formatado     = $item->dt_criacao ? HTMLHelper::_('date', $item->dt_criacao, 'd/m/Y') : '';
			$item->dt_atualizacao_formatado = !empty($item->dt_atualizacao)
				? HTMLHelper::_('date', $item->dt_atualizacao, 'd/m/Y')
				: $item->dt_criacao_formatado;
			$item->nr_protocolo = AdesaoHelper::getProtocoloAdesao($item->id);
			$item->municipios   = AdesaoHelper::getMunicipiosAdesao($item->ds_municipios);
		}

		return ['items' => $result, 'total' => $total];
	}
}
