<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Victor Gonçalves Bento <vgb.info@gmail.com>
 * @copyright  2026 Victor Gonçalves Bento
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\DatabaseQuery;
use stdClass;

/**
 * Model for adesões still being filled (st_adesao = rascunho).
 *
 * @since  1.0.0
 */
class AdesoespreenchimentoModel extends ItemModel
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
	 * Counts and groups draft adesões for the dashboard cards.
	 *
	 * @return  object
	 */
	public function getEstatisticas(): object
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select([
				$db->quoteName('tp_esfera_governamental'),
				'COUNT(*) AS total',
			])
			->from($db->quoteName('#__tb_adesao'))
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('st_adesao') . ' = ' . $db->quote('rascunho'))
			->group($db->quoteName('tp_esfera_governamental'));

		$db->setQuery($query);
		$results = $db->loadObjectList();

		$estatisticas = new stdClass();
		$estatisticas->total      = 0;
		$estatisticas->municipal  = 0;
		$estatisticas->estadual   = 0;
		$estatisticas->consorcio  = 0;
		$estatisticas->recentes   = 0;
		$estatisticas->paradas    = 0;

		foreach ($results as $result) {
			$esfera = (string) $result->tp_esfera_governamental;
			$total  = (int) $result->total;
			$estatisticas->total += $total;

			if (property_exists($estatisticas, $esfera)) {
				$estatisticas->{$esfera} = $total;
			}
		}

		$ultimaAtualizacao = 'COALESCE(' . $db->quoteName('dt_alterado') . ', ' . $db->quoteName('dt_criacao') . ')';

		$query = $db->getQuery(true);
		$query->select([
				'SUM(CASE WHEN ' . $ultimaAtualizacao . ' >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recentes',
				'SUM(CASE WHEN ' . $ultimaAtualizacao . ' < DATE_SUB(NOW(), INTERVAL 7 DAY) OR ' . $ultimaAtualizacao . ' IS NULL THEN 1 ELSE 0 END) AS paradas',
			])
			->from($db->quoteName('#__tb_adesao'))
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('st_adesao') . ' = ' . $db->quote('rascunho'));

		$db->setQuery($query);
		$movimento = $db->loadObject();

		$estatisticas->recentes = (int) ($movimento->recentes ?? 0);
		$estatisticas->paradas  = (int) ($movimento->paradas ?? 0);

		return $estatisticas;
	}

	/**
	 * Builds the WHERE conditions shared between buscar() and count().
	 */
	private function _buildWhere(DatabaseQuery $query, string $searchTerm, string $ente, string $movimento = ''): void
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
			' OR ' . $db->quoteName('ds_email_responsavel') . ' LIKE ' . $db->quote('%' . $searchTerm . '%') .
			' OR ' . $db->quoteName('id') . ' = ' . $db->quote($endprotocolo) .
			' OR ' . $municipioSubquery .
			' OR ' . $estadoSubquery . ')'
		)
		->where($db->quoteName('state') . ' = 1')
		->where($db->quoteName('st_adesao') . ' = ' . $db->quote('rascunho'));

		$ultimaAtualizacao = 'COALESCE(' . $db->quoteName('dt_alterado') . ', ' . $db->quoteName('dt_criacao') . ')';
		if ($movimento === 'recentes') {
			$query->where($ultimaAtualizacao . ' >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
		} elseif ($movimento === 'paradas') {
			$query->where('(' . $ultimaAtualizacao . ' < DATE_SUB(NOW(), INTERVAL 7 DAY) OR ' . $ultimaAtualizacao . ' IS NULL)');
		}

		if ($ente) {
			$query->where($db->quoteName('tp_esfera_governamental') . ' = ' . $db->quote($ente));
		}
	}

	/**
	 * Subquery: total fillable questions of the adesão edital (etapa adesão).
	 */
	private function _perguntasSubquery(): string
	{
		$db = Factory::getDbo();

		return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__tb_pergunta', 'p') .
			' WHERE ' . $db->quoteName('p.co_edital') . ' = ' . $db->quoteName('#__tb_adesao.co_edital') .
			' AND ' . $db->quoteName('p.state') . ' = 1' .
			' AND ' . $db->quoteName('p.tp_etapa') . ' = ' . $db->quote('adesao') .
			' AND ' . $db->quoteName('p.tp_pergunta') . ' <> ' . $db->quote('informativo') . ')';
	}

	/**
	 * Subquery: answered questions of the adesão (etapa adesão).
	 */
	private function _preenchidasSubquery(): string
	{
		$db = Factory::getDbo();

		return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__tb_resposta', 'r') .
			' INNER JOIN ' . $db->quoteName('#__tb_pergunta', 'p') .
			' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('r.co_pergunta') .
			' WHERE ' . $db->quoteName('r.co_adesao') . ' = ' . $db->quoteName('#__tb_adesao.id') .
			' AND ' . $db->quoteName('r.state') . ' = 1' .
			' AND ' . $db->quoteName('p.tp_etapa') . ' = ' . $db->quote('adesao') .
			' AND ' . $db->quoteName('p.tp_pergunta') . ' <> ' . $db->quote('informativo') .
			' AND ' . $db->quoteName('r.ds_resposta') . ' IS NOT NULL' .
			' AND TRIM(' . $db->quoteName('r.ds_resposta') . ') <> ' . $db->quote('') . ')';
	}

	public function count(string $searchTerm, string $ente, string $movimento = ''): int
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select('COUNT(*)')
			->from($db->quoteName('#__tb_adesao'));

		$this->_buildWhere($query, $searchTerm, $ente, $movimento);

		$db->setQuery($query);

		return (int) $db->loadResult();
	}

	public function buscar(
		string $searchTerm,
		string $ente,
		string $ordem = 'dt_atualizacao',
		string $direcao = 'DESC',
		int $limit = 10,
		int $start = 0,
		string $movimento = ''
	): array {
		$db    = Factory::getDbo();
		$query = $db->getQuery(true);

		$perguntasSub   = $this->_perguntasSubquery();
		$preenchidasSub = $this->_preenchidasSubquery();
		$dtAtualizacao  = 'COALESCE(' . $db->quoteName('dt_alterado') . ', ' . $db->quoteName('dt_criacao') . ')';

		$query->select([
				$db->quoteName('#__tb_adesao') . '.*',
				$perguntasSub . ' AS qt_perguntas',
				$preenchidasSub . ' AS qt_preenchidas',
				$dtAtualizacao . ' AS dt_atualizacao',
			])
			->from($db->quoteName('#__tb_adesao'));

		$this->_buildWhere($query, $searchTerm, $ente, $movimento);

		$orderMap = [
			'dt_atualizacao' => $dtAtualizacao,
			'dt_criacao'     => $db->quoteName('dt_criacao'),
			'preenchimento'  => $preenchidasSub . ' / GREATEST(' . $perguntasSub . ', 1)',
			'no_oficial'     => $db->quoteName('no_oficial'),
		];

		$orderExpr = $orderMap[$ordem] ?? $dtAtualizacao;
		$direcao   = strtoupper($direcao) === 'ASC' ? 'ASC' : 'DESC';

		$query->order($orderExpr . ' ' . $direcao);

		$db->setQuery($query, $start, $limit);
		$results = $db->loadObjectList() ?: [];

		foreach ($results as $item) {
			$item->qt_perguntas    = (int) $item->qt_perguntas;
			$item->qt_preenchidas  = (int) $item->qt_preenchidas;
			$item->pc_preenchimento = $item->qt_perguntas > 0
				? (int) round(($item->qt_preenchidas / $item->qt_perguntas) * 100)
				: 0;
		}

		return $results;
	}
}
