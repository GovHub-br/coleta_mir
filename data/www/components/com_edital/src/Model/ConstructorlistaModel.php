<?php

namespace Comedital\Component\Edital\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class ConstructorlistaModel extends ListModel
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'no_edital', 'a.no_edital',
				'ds_edital', 'a.ds_edital',
				'dt_abertura', 'a.dt_abertura',
				'dt_encerramento', 'a.dt_encerramento',
				'state', 'a.state',
				'tp_edital', 'a.tp_edital',
			);
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.dt_abertura', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);
		$this->setState('filter.search', $this->getUserStateFromRequest(
			$this->context . '.filter.search',
			'filter_search',
			''
		));
	}

	protected function getListQuery()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('a.*')
			->from($db->quoteName('#__tb_edital', 'a'))
			->where($db->quoteName('a.tp_edital') . ' = ' . $db->quote('formulario'))
			->where($db->quoteName('a.state') . ' <> -2');

		$search = trim((string) $this->getState('filter.search'));
		if ($search !== '')
		{
			$term = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where('(' . $db->quoteName('a.no_edital') . ' LIKE ' . $term
				. ' OR ' . $db->quoteName('a.ds_edital') . ' LIKE ' . $term . ')');
		}

		$order = $this->getState('list.ordering', 'a.dt_abertura');
		$direction = strtoupper($this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$allowedOrder = array('a.id', 'a.no_edital', 'a.dt_abertura', 'a.dt_encerramento', 'a.state');
		$query->order((in_array($order, $allowedOrder, true) ? $order : 'a.dt_abertura') . ' ' . $direction);

		return $query;
	}

	public function getTamanhoFormulario($idEdital)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__tb_pergunta'))
			->where($db->quoteName('co_edital') . ' = ' . (int) $idEdital)
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('tp_pergunta') . ' <> ' . $db->quote('informativo'));
		$db->setQuery($query);
		$questions = (int) $db->loadResult();

		$query->clear('select')->select('COUNT(DISTINCT ' . $db->quoteName('co_agrupamento') . ')');
		$db->setQuery($query);

		return array('qtPerguntas' => $questions, 'qtAgrupamentos' => (int) $db->loadResult());
	}
}
