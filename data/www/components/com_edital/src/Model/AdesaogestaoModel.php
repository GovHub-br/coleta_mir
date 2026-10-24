<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;
// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\ItemModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\User\UserFactoryInterface;
use \Comedital\Component\Edital\Site\Helper\EditalHelper;

/**
 * Edital model.
 *
 * @since  1.0.0
 */
class AdesaogestaoModel extends ItemModel
{
	public $_item;

	public function getItem($pk = null)
	{
		if ($this->_item === null) {
			$this->_item = new \Joomla\CMS\Object\CMSObject();
		}

		return $this->_item;
	}



	/**
	 * Builds the WHERE conditions shared between buscar() and count().
	 */
	private function _buildWhere(\Joomla\Database\DatabaseQuery $query, $searchTerm, $situacao, $ente, $tipo = ''): void
	{
		$db = Factory::getDbo();

		$protocolParts = explode('-', $searchTerm);
		$endprotocolo  = end($protocolParts);

		$municipioSubquery = 'EXISTS (' .
			'SELECT 1 FROM ' . $db->quoteName('#__tb_municipio') .
			' WHERE ' . $db->quoteName('no_municipio') . ' LIKE ' . $db->quote('%' . $searchTerm . '%') .
			' AND FIND_IN_SET(' . $db->quoteName('co_ibge') . ', `#__tb_adesao`.`ds_municipios`) > 0' .
		')';

		$estadoSubquery = 'EXISTS (' .
			'SELECT 1 FROM ' . $db->quoteName('#__tb_estado') .
			' WHERE (' .
				$db->quoteName('no_estado') . ' LIKE ' . $db->quote('%' . $searchTerm . '%') .
				' OR ' . $db->quoteName('sg_estado') . ' LIKE ' . $db->quote('%' . $searchTerm . '%') .
			')' .
			' AND ' . $db->quoteName('sg_estado') . ' = `#__tb_adesao`.`sg_estado`' .
		')';

		$query->where(
			'(' . $db->quoteName('no_oficial') . ' LIKE ' . $db->quote('%' . $searchTerm . '%') .
			' OR ' . $db->quoteName('id') . ' = ' . $db->quote($endprotocolo) .
			' OR ' . $municipioSubquery .
			' OR ' . $estadoSubquery . ')'
		)
		->where($db->quoteName('state') . ' = 1')
		->where($db->quoteName('st_adesao') . ' <> ' . $db->quote('rascunho'))
		//trazer apenas adesões que não são originadas de outra adesão (adesão principal)
		->where($db->quoteName('co_adesao_origem') . ' is null');

		if ($situacao) {
			$query->where($db->quoteName('st_adesao') . ' = ' . $db->quote($situacao));
		}

		if ($ente) {
			$query->where($db->quoteName('tp_esfera_governamental') . ' = ' . $db->quote($ente));
		}

		if (in_array($tipo, ['migracao', 'nova_adesao'], true)) {
			$query->where($db->quoteName('tp_solicitacao') . ' = ' . $db->quote($tipo));
		}
	}

	public function count($searchTerm, $situacao, $ente, $tipo = ''): int
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select('COUNT(*)')
			->from($db->quoteName('#__tb_adesao'));

		$this->_buildWhere($query, $searchTerm, $situacao, $ente, $tipo);

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	public function buscar($searchTerm, $situacao, $ente, $ordem = 'dt_criacao', $direcao = 'DESC', $limit = 10, $start = 0, $tipo = '')
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);
		$adesaoModel = new AdesaoModel();

		$query->select('*')
			->from($db->quoteName('#__tb_adesao'));

		$this->_buildWhere($query, $searchTerm, $situacao, $ente, $tipo);

		$query->order($db->quoteName($ordem) . ' ' . $direcao);

		$db->setQuery($query, (int) $start, (int) $limit);
		$results = $db->loadObjectList();

		foreach ($results as $item) {
			$item->finalizacao = $adesaoModel->getResumoFinalizacao((int) $item->id);
			$item->qtRespostaPendente = $item->finalizacao->pendentes;
			$item->qtRespostaAprovada = $item->finalizacao->aprovados;
			$item->qtRespostaRejeitada = $item->finalizacao->rejeitados;
			$item->qtRespostaComplementacao = $item->finalizacao->complementacoes;
			$item->podeFinalizar = $item->finalizacao->podeFinalizar;
			$item->motivoBloqueio = $item->finalizacao->motivoBloqueio;
		}

		return $results;
	}


	public function getQtRespostaPorSituacao($idAdesao, $situacao)
	{
		$db = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select('COUNT(*)')
			->from($db->quoteName('#__tb_resposta'))
			->where($db->quoteName('co_adesao') . ' = ' . $db->quote($idAdesao))
			->where($db->quoteName('st_situacao') . ' = ' . $db->quote($situacao));

		$db->setQuery($query);
		return $db->loadResult();
	}
}
