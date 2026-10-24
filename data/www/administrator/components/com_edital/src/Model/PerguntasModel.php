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
 * Methods supporting a list of Perguntas records.
 *
 * @since  1.0.0
 */
class PerguntasModel extends ListModel
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
				'ds_titulo', 'a.ds_titulo',
				'co_edital', 'a.co_edital',
				'ds_descricao', 'a.ds_descricao',
				'tp_pergunta', 'a.tp_pergunta',
				'ds_opcoes', 'a.ds_opcoes',
				'tp_requerida', 'a.tp_requerida',
				'ds_informacao_complementar', 'a.ds_informacao_complementar',
				'ds_subtitulo', 'a.ds_subtitulo',
				'tp_etapa', 'a.tp_etapa',
				'ds_informacao_complementar2', 'a.ds_informacao_complementar2',
				'co_agrupamento', 'a.co_agrupamento',
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
		parent::populateState("a.id", "ASC");

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
		$id .= ':' . $this->serializeFilterState('filter.co_edital');
		$id .= ':' . $this->serializeFilterState('filter.co_agrupamento');
		$id .= ':' . $this->serializeFilterState('filter.tp_pergunta');
		$id .= ':' . $this->serializeFilterState('filter.tp_requerida');
		$id .= ':' . $this->serializeFilterState('filter.tp_etapa');

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
		$query->from('`#__tb_pergunta` AS a');
		
		// Join over the users for the checked out user
		$query->select("uc.name AS uEditor");
		$query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
		// Join over the foreign key 'co_edital'
		$query->select('`#__tb_edital_4309963`.`no_edital` AS editais_fk_value_4309963');
		$query->join('LEFT', '#__tb_edital AS #__tb_edital_4309963 ON #__tb_edital_4309963.`id` = a.`co_edital`');
		// Join over the foreign key 'co_agrupamento'
		$query->select('agrupamento_fk.`no_agrupamento` AS agrupamento_fk_value');
		$query->join('LEFT', '#__tb_agrupamento AS agrupamento_fk ON agrupamento_fk.`id` = a.`co_agrupamento`');
		

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
				$query->where('( a.ds_titulo LIKE ' . $search . ' )');
			}
		}
		

		// Filtering co_edital
		$filter_co_edital = $this->state->get("filter.co_edital");

		if ($filter_co_edital !== null && !empty($filter_co_edital))
		{
			if ($filter_co_edital !== null && !empty($filter_co_edital)) {
				if (is_array($filter_co_edital) && !empty($filter_co_edital)) {
					$multiConditions = [];
					foreach ($filter_co_edital as $val) {
						$multiConditions[] = "FIND_IN_SET(" . $db->quote($val) . ", a.co_edital)";
					}
					if (!empty($multiConditions)) {
						$query->where("(" . implode(" OR ", $multiConditions) . ")");
					}
				} else {
					$query->where("FIND_IN_SET(" . $db->quote($filter_co_edital) . ", a.co_edital)");
				}
			}
		}

		$this->applyListFilter($query, $db, 'a.co_agrupamento', $this->state->get('filter.co_agrupamento'));
		$this->applyListFilter($query, $db, 'a.tp_pergunta', $this->state->get('filter.tp_pergunta'));
		$this->applyListFilter($query, $db, 'a.tp_requerida', $this->state->get('filter.tp_requerida'));
		$this->applyListFilter($query, $db, 'a.tp_etapa', $this->state->get('filter.tp_etapa'));
		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', "a.id");
		$orderDirn = $this->state->get('list.direction', "ASC");

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

			if (isset($oneItem->co_edital))
			{
				$values    = explode(',', $oneItem->co_edital);
				$textValue = array();

				foreach ($values as $value)
				{
					$db    = $this->getDbo();
					$query = $db->getQuery(true);
					$query
						->select('`#__tb_edital_4309963`.`no_edital`')
						->from($db->quoteName('#__tb_edital', '#__tb_edital_4309963'))
						->where($db->quoteName('#__tb_edital_4309963.id') . ' = '. $db->quote($db->escape($value)));

					$db->setQuery($query);
					$results = $db->loadObject();

					if ($results)
					{
						$textValue[] = $results->no_edital;
					}
				}

				$oneItem->co_edital = !empty($textValue) ? implode(', ', $textValue) : $oneItem->co_edital;
			}
					$oneItem->tp_pergunta = !empty($oneItem->tp_pergunta) ? Text::_('COM_EDITAL_PERGUNTAS_TP_PERGUNTA_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->tp_pergunta)))) : '';
					$oneItem->tp_requerida_valor = (string) ($oneItem->tp_requerida ?? '');
					$oneItem->tp_requerida = $oneItem->tp_requerida_valor !== '' ? Text::_('COM_EDITAL_PERGUNTAS_TP_REQUERIDA_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->tp_requerida_valor)))) : '';
					$oneItem->tp_etapa = !empty($oneItem->tp_etapa) ? Text::_('COM_EDITAL_PERGUNTAS_TP_ETAPA_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->tp_etapa)))) : '';
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
	 * @param   \Joomla\Database\DatabaseQuery  $query    Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db    Database driver.
	 * @param   string  $column  Quoted later via quoteName.
	 * @param   mixed   $value   Selected filter value(s).
	 *
	 * @return  void
	 */
	protected function applyListFilter($query, $db, $column, $value)
	{
		if (is_array($value)) {
			$value = array_values(array_filter($value, static function ($item) {
				return $item !== '' && $item !== null;
			}));
		} elseif ($value !== null && $value !== '') {
			$value = [$value];
		} else {
			$value = [];
		}

		if ($value === []) {
			return;
		}

		if (count($value) === 1) {
			$query->where($db->quoteName($column) . ' = ' . $db->quote(reset($value)));

			return;
		}

		$query->where($db->quoteName($column) . ' IN (' . implode(',', array_map([$db, 'quote'], $value)) . ')');
	}
}
