<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Model;
// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use Comedital\Component\Edital\Administrator\Helper\EditalHelper;

/**
 * Methods supporting a list of Respostas records.
 *
 * @since  1.0.0
 */
class RespostasModel extends ListModel
{
	/**
	* Constructor.
	*
	* @param   array  $config  An optional associative array of configuration settings.
	*
	* @see        JController
	* @since      1.6
	*/
	public function __construct($config = array())
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = array(
				'id', 'a.id',
				'state', 'a.state',
				'ordering', 'a.ordering',
				'created_by', 'a.created_by',
				'modified_by', 'a.modified_by',
				'co_pergunta', 'a.co_pergunta',
				'co_adesao', 'a.co_adesao',
				'ds_resposta', 'a.ds_resposta',
				'dt_criacao', 'a.dt_criacao',
				'dt_alterado', 'a.dt_alterado',
				'ds_comentario_avaliador', 'a.ds_comentario_avaliador',
				'st_situacao', 'a.st_situacao',
				'st_adesao',
				'co_edital',
				'no_edital', 'edital_fk.no_edital',
				'co_agrupamento',
				'info_complementares',
			);
		}

		parent::__construct($config);
	}


	

	

	

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   Elements order
	 * @param   string  $direction  Order direction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// List state information.
		parent::populateState('', 'ASC');

		$context = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search');
		$this->setState('filter.search', $context);

		// Split context into component and optional section
		if (!empty($context))
		{
			$parts = FieldsHelper::extract($context);

			if ($parts)
			{
				$this->setState('filter.component', $parts[0]);
				$this->setState('filter.section', $parts[1]);
			}
		}
	}

	/**
	 * Method to get a store id based on model configuration state.
	 *
	 * This is necessary because the model is used by the component and
	 * different modules that might need different sets of data or different
	 * ordering requirements.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string A store id.
	 *
	 * @since   1.0.0
	 */
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');
		$id .= ':' . $this->serializeFilterState('filter.co_pergunta');
		$id .= ':' . $this->serializeFilterState('filter.co_adesao');
		$id .= ':' . $this->serializeFilterState('filter.st_situacao');
		$id .= ':' . $this->serializeFilterState('filter.st_adesao');
		$id .= ':' . $this->serializeFilterState('filter.co_edital');
		$id .= ':' . $this->serializeFilterState('filter.co_agrupamento');
		$id .= ':' . $this->serializeFilterState('filter.info_complementares');

		return parent::getStoreId($id);
		
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$this->getState(
				'list.select', 'DISTINCT a.*'
			)
		);
		$query->from('`#__tb_resposta` AS a');
		
		// Join over the users for the checked out user
		$query->select("uc.name AS uEditor");
		$query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
		// Join over the foreign key 'co_pergunta'
		$query->select('`#__tb_pergunta_4309998`.`ds_titulo` AS perguntas_fk_value_4309998');
		$query->join('LEFT', '#__tb_pergunta AS #__tb_pergunta_4309998 ON #__tb_pergunta_4309998.`id` = a.`co_pergunta`');
		// Join over the foreign key 'co_adesao'
		$query->select('`#__tb_adesao_4309999`.`no_oficial` AS adesoes_fk_value_4309999');
		$query->join('LEFT', '#__tb_adesao AS #__tb_adesao_4309999 ON #__tb_adesao_4309999.`id` = a.`co_adesao`');
		$query->select('edital_fk.`no_edital` AS no_edital');
		$query->join('LEFT', '#__tb_edital AS edital_fk ON edital_fk.`id` = `#__tb_adesao_4309999`.`co_edital`');
		

		// Filter by published state
		$published = $this->getState('filter.state');

		if (is_numeric($published))
		{
			$query->where('a.state = ' . (int) $published);
		}
		elseif (empty($published))
		{
			$query->where('(a.state IN (0, 1))');
		}

		// Filter by search in title
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->Quote('%' . $db->escape($search, true) . '%');
				$query->where('(`#__tb_adesao_4309999`.`no_oficial` LIKE ' . $search . ' )');
			}
		}
		

		// Filtering co_pergunta
		$filter_co_pergunta = $this->state->get("filter.co_pergunta");

		if ($filter_co_pergunta !== null && !empty($filter_co_pergunta))
		{
			if ($filter_co_pergunta !== null && !empty($filter_co_pergunta)) {
				if (is_array($filter_co_pergunta) && !empty($filter_co_pergunta)) {
					$multiConditions = [];
					foreach ($filter_co_pergunta as $val) {
						$multiConditions[] = "FIND_IN_SET(" . $db->quote($val) . ", a.co_pergunta)";
					}
					if (!empty($multiConditions)) {
						$query->where("(" . implode(" OR ", $multiConditions) . ")");
					}
				} else {
					$query->where("FIND_IN_SET(" . $db->quote($filter_co_pergunta) . ", a.co_pergunta)");
				}
			}
		}

		// Filtering co_adesao
		$filter_co_adesao = $this->state->get("filter.co_adesao");

		if ($filter_co_adesao !== null && !empty($filter_co_adesao))
		{
			if ($filter_co_adesao !== null && !empty($filter_co_adesao)) {
				if (is_array($filter_co_adesao) && !empty($filter_co_adesao)) {
					$multiConditions = [];
					foreach ($filter_co_adesao as $val) {
						$multiConditions[] = "FIND_IN_SET(" . $db->quote($val) . ", a.co_adesao)";
					}
					if (!empty($multiConditions)) {
						$query->where("(" . implode(" OR ", $multiConditions) . ")");
					}
				} else {
					$query->where("FIND_IN_SET(" . $db->quote($filter_co_adesao) . ", a.co_adesao)");
				}
			}
		}

		$this->applyListFilter($query, $db, 'a.st_situacao', $this->state->get('filter.st_situacao'));
		$this->applyListFilter($query, $db, '#__tb_adesao_4309999.st_adesao', $this->state->get('filter.st_adesao'));
		$this->applyListFilter($query, $db, '#__tb_adesao_4309999.co_edital', $this->state->get('filter.co_edital'));
		$this->applyListFilter($query, $db, '#__tb_pergunta_4309998.co_agrupamento', $this->state->get('filter.co_agrupamento'));
		$this->applyInfoComplementaresFilter($query, $db, $this->state->get('filter.info_complementares'));

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', '');
		$orderDirn = $this->state->get('list.direction', 'ASC');

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		return $query;
	}

	/**
	 * Get an array of data items
	 *
	 * @return mixed Array of data items on success, false on failure.
	 */
	public function getItems()
	{
		$items = parent::getItems();
		
		foreach ($items as $oneItem)
		{

			if (isset($oneItem->co_pergunta))
			{
				$values    = explode(',', $oneItem->co_pergunta);
				$textValue = array();

				foreach ($values as $value)
				{
					$db    = $this->getDbo();
					$query = $db->getQuery(true);
					$query
						->select('`#__tb_pergunta_4309998`.`ds_titulo` AS `fk_value`')
						->from($db->quoteName('#__tb_pergunta', '#__tb_pergunta_4309998'))
						->where($db->quoteName('#__tb_pergunta_4309998.id') . ' = '. $db->quote($db->escape($value)));

					$db->setQuery($query);
					$results = $db->loadObject();

					if ($results)
					{
						$textValue[] = $results->fk_value;
					}
				}

				$oneItem->co_pergunta = !empty($textValue) ? implode(', ', $textValue) : $oneItem->co_pergunta;
			}

			if (isset($oneItem->co_adesao))
			{
				$values    = explode(',', $oneItem->co_adesao);
				$textValue = array();

				foreach ($values as $value)
				{
					$db    = $this->getDbo();
					$query = $db->getQuery(true);
					$query
						->select('`#__tb_adesao_4309999`.`no_oficial` AS `fk_value`')
						->from($db->quoteName('#__tb_adesao', '#__tb_adesao_4309999'))
						->where($db->quoteName('#__tb_adesao_4309999.id') . ' = '. $db->quote($db->escape($value)));

					$db->setQuery($query);
					$results = $db->loadObject();

					if ($results)
					{
						$textValue[] = $results->fk_value;
					}
				}

				$oneItem->co_adesao = !empty($textValue) ? implode(', ', $textValue) : $oneItem->co_adesao;
			}
					$oneItem->st_situacao = !empty($oneItem->st_situacao) ? Text::_('COM_EDITAL_RESPOSTAS_ST_SITUACAO_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->st_situacao)))) : '';
		}

		return $items;
	}

	/**
	 * Normalize a filter value stored in the model state for cache keys.
	 *
	 * @param   string  $stateKey  Filter state key.
	 *
	 * @return  string
	 */
	protected function serializeFilterState($stateKey)
	{
		$value = $this->getState($stateKey);

		if (is_array($value)) {
			$value = array_values(array_filter($value, static function ($item) {
				return $item !== '' && $item !== null;
			}));
			sort($value);

			return implode(',', $value);
		}

		return (string) $value;
	}

	/**
	 * Apply a single or multiple list filter to the query.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query   Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db      Database driver.
	 * @param   string                              $column  Column name.
	 * @param   mixed                               $value   Selected filter value(s).
	 *
	 * @return  void
	 */
	protected function applyListFilter($query, $db, $column, $value)
	{
		$values = $this->normalizeFilterValues($value);

		if ($values === []) {
			return;
		}

		if (count($values) === 1) {
			$query->where($db->quoteName($column) . ' = ' . $db->quote(reset($values)));

			return;
		}

		$query->where($db->quoteName($column) . ' IN (' . implode(',', array_map([$db, 'quote'], $values)) . ')');
	}

	/**
	 * Filter respostas by whether the adesão has an origin adesão.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query  Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db     Database driver.
	 * @param   mixed                               $value  1 = yes, 0 = no.
	 *
	 * @return  void
	 */
	protected function applyInfoComplementaresFilter($query, $db, $value)
	{
		if ($value === null || $value === '') {
			return;
		}

		$column = $db->quoteName('#__tb_adesao_4309999.co_adesao_origem');
		$emptyCondition = '(' . $column . ' IS NULL OR ' . $column . ' = ' . $db->quote('') . ' OR ' . $column . ' = 0)';

		if ((string) $value === '1') {
			$query->where('NOT ' . $emptyCondition);

			return;
		}

		$query->where($emptyCondition);
	}

	/**
	 * Normalize filter values coming from the request.
	 *
	 * @param   mixed  $value  Selected filter value(s).
	 *
	 * @return  array
	 */
	protected function normalizeFilterValues($value)
	{
		if (is_array($value)) {
			return array_values(array_filter($value, static function ($item) {
				return $item !== '' && $item !== null;
			}));
		}

		if ($value !== null && $value !== '') {
			return [$value];
		}

		return [];
	}
}
