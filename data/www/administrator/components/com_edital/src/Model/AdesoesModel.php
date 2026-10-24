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
 * Methods supporting a list of Adesoes records.
 *
 * @since  1.0.0
 */
class AdesoesModel extends ListModel
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
				'tp_esfera_governamental', 'a.tp_esfera_governamental',
				'co_edital', 'a.co_edital',
				'ds_cnpj', 'a.ds_cnpj',
				'no_oficial', 'a.no_oficial',
				'ds_email_responsavel', 'a.ds_email_responsavel',
				'dt_criacao', 'a.dt_criacao',
				'sg_estado', 'a.sg_estado',
				'ds_municipios', 'a.ds_municipios',
				'st_adesao', 'a.st_adesao',
				'ds_observacao_interna', 'a.ds_observacao_interna',
				'ds_resposta', 'a.ds_resposta',
				'dt_limite_situacao', 'a.dt_limite_situacao',
				'ds_emails_alternativos', 'a.ds_emails_alternativos',
				'ds_modalidade', 'a.ds_modalidade',
				'co_adesao_origem', 'a.co_adesao_origem',
				'no_consorcio',
				'vinculada',
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
		$id .= ':' . $this->serializeFilterState('filter.tp_esfera_governamental');
		$id .= ':' . $this->serializeFilterState('filter.co_edital');
		$id .= ':' . $this->serializeFilterState('filter.st_adesao');
		$id .= ':' . $this->serializeFilterState('filter.ds_modalidade');
		$id .= ':' . $this->serializeFilterState('filter.vinculada');
		$id .= ':' . $this->serializeFilterState('filter.sg_estado');
		$id .= ':' . $this->serializeFilterState('filter.ds_municipios');
		$id .= ':' . $this->serializeFilterState('filter.no_consorcio');
		$id .= ':' . $this->getState('filter.dt_criacao_de');
		$id .= ':' . $this->getState('filter.dt_criacao_ate');

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
		$query->from('`#__tb_adesao` AS a');
		
		// Join over the users for the checked out user
		$query->select("uc.name AS uEditor");
		$query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
		// Join over the foreign key 'co_edital'
		$query->select('`#__tb_edital_4309979`.`no_edital` AS editais_fk_value_4309979');
		$query->join('LEFT', '#__tb_edital AS #__tb_edital_4309979 ON #__tb_edital_4309979.`id` = a.`co_edital`');
		$query->select(
			'CASE WHEN a.tp_esfera_governamental = ' . $db->quote('consorcio')
			. ' THEN a.no_oficial ELSE origem.no_oficial END AS no_consorcio'
		);
		$query->join('LEFT', '#__tb_adesao AS origem ON origem.id = a.co_adesao_origem');
		

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
				$query->where('( a.ds_cnpj LIKE ' . $search . '  OR  a.no_oficial LIKE ' . $search . '  OR  a.ds_email_responsavel LIKE ' . $search . ' )');
			}
		}
		

		$this->applyListFilter($query, $db, 'a.tp_esfera_governamental', $this->state->get('filter.tp_esfera_governamental'));

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

		$this->applyListFilter($query, $db, 'a.st_adesao', $this->state->get('filter.st_adesao'));
		$this->applyModalidadeFilter($query, $db, $this->state->get('filter.ds_modalidade'));
		$this->applyVinculadaFilter($query, $db, $this->state->get('filter.vinculada'));
		$this->applyCsvFilter($query, $db, 'a.sg_estado', $this->state->get('filter.sg_estado'));
		$this->applyCsvFilter($query, $db, 'a.ds_municipios', $this->state->get('filter.ds_municipios'));
		$this->applyConsorcioFilter($query, $db, $this->state->get('filter.no_consorcio'));
		$this->applyDateRangeFilter(
			$query,
			$db,
			'a.dt_criacao',
			$this->state->get('filter.dt_criacao_de'),
			$this->state->get('filter.dt_criacao_ate')
		);

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

		$estadoLabels    = $this->loadEstadoLabels($items);
		$municipioLabels = $this->loadMunicipioLabels($items);

		foreach ($items as $oneItem)
		{
			$oneItem->estados_labels = array();

			foreach ($this->splitCsvValue($oneItem->sg_estado ?? '') as $sigla)
			{
				$oneItem->estados_labels[] = $estadoLabels[$sigla] ?? $sigla;
			}

			$oneItem->municipios_labels = array();

			foreach ($this->splitCsvValue($oneItem->ds_municipios ?? '') as $coIbge)
			{
				$oneItem->municipios_labels[] = $municipioLabels[(int) $coIbge] ?? $coIbge;
			}

					$oneItem->tp_esfera_governamental = !empty($oneItem->tp_esfera_governamental) ? Text::_('COM_EDITAL_ADESOES_TP_ESFERA_GOVERNAMENTAL_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->tp_esfera_governamental)))) : '';

			if (isset($oneItem->co_edital))
			{
				$values    = explode(',', $oneItem->co_edital);
				$textValue = array();

				foreach ($values as $value)
				{
					$db    = $this->getDbo();
					$query = $db->getQuery(true);
					$query
						->select('`#__tb_edital_4309979`.`no_edital`')
						->from($db->quoteName('#__tb_edital', '#__tb_edital_4309979'))
						->where($db->quoteName('#__tb_edital_4309979.id') . ' = '. $db->quote($db->escape($value)));

					$db->setQuery($query);
					$results = $db->loadObject();

					if ($results)
					{
						$textValue[] = $results->no_edital;
					}
				}

				$oneItem->co_edital = !empty($textValue) ? implode(', ', $textValue) : $oneItem->co_edital;
			}
					$oneItem->st_adesao = !empty($oneItem->st_adesao) ? Text::_('COM_EDITAL_ADESOES_ST_ADESAO_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->st_adesao)))) : '';
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
	 * Filter adesões by modalidade, including empty values.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query  Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db     Database driver.
	 * @param   mixed                               $value  Selected filter value(s).
	 *
	 * @return  void
	 */
	protected function applyModalidadeFilter($query, $db, $value)
	{
		$values = $this->normalizeFilterValues($value);

		if ($values === []) {
			return;
		}

		$includeEmpty = in_array('__empty__', $values, true);
		$values = array_values(array_filter($values, static function ($item) {
			return $item !== '__empty__';
		}));

		$conditions = [];

		if ($includeEmpty) {
			$conditions[] = '(' . $db->quoteName('a.ds_modalidade') . ' IS NULL OR '
				. $db->quoteName('a.ds_modalidade') . ' = ' . $db->quote('') . ')';
		}

		if (count($values) === 1) {
			$conditions[] = $db->quoteName('a.ds_modalidade') . ' = ' . $db->quote(reset($values));
		} elseif (count($values) > 1) {
			$conditions[] = $db->quoteName('a.ds_modalidade') . ' IN (' . implode(',', array_map([$db, 'quote'], $values)) . ')';
		}

		if ($conditions !== []) {
			$query->where('(' . implode(' OR ', $conditions) . ')');
		}
	}

	/**
	 * Filter adesões by whether they are linked to another adesão.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query  Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db     Database driver.
	 * @param   mixed                               $value  1 = yes, 0 = no.
	 *
	 * @return  void
	 */
	protected function applyVinculadaFilter($query, $db, $value)
	{
		if ($value === null || $value === '') {
			return;
		}

		$column = $db->quoteName('a.co_adesao_origem');
		$emptyCondition = '(' . $column . ' IS NULL OR ' . $column . ' = ' . $db->quote('') . ' OR ' . $column . ' = 0)';

		if ((string) $value === '1') {
			$query->where('NOT ' . $emptyCondition);

			return;
		}

		$query->where($emptyCondition);
	}

	/**
	 * Filter adesões by consortium name: every consortium with that name
	 * and the municipalities linked to any of them.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query  Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db     Database driver.
	 * @param   mixed                               $value  Selected consortium name(s).
	 *
	 * @return  void
	 */
	protected function applyConsorcioFilter($query, $db, $value)
	{
		$values = $this->normalizeFilterValues($value);

		if ($values === []) {
			return;
		}

		$quoted = implode(',', array_map([$db, 'quote'], $values));

		$query->where(
			'('
			. '(' . $db->quoteName('a.tp_esfera_governamental') . ' = ' . $db->quote('consorcio')
			. ' AND ' . $db->quoteName('a.no_oficial') . ' IN (' . $quoted . '))'
			. ' OR ' . $db->quoteName('origem.no_oficial') . ' IN (' . $quoted . ')'
			. ')'
		);
	}

	/**
	 * Apply a filter over a column that stores a comma separated list of values.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query   Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db      Database driver.
	 * @param   string                              $column  Column name.
	 * @param   mixed                               $value   Selected filter value(s).
	 *
	 * @return  void
	 */
	protected function applyCsvFilter($query, $db, $column, $value)
	{
		$values = $this->normalizeFilterValues($value);

		if ($values === []) {
			return;
		}

		$conditions = array();

		foreach ($values as $item) {
			$conditions[] = 'FIND_IN_SET(' . $db->quote($item) . ', ' . $db->quoteName($column) . ')';
		}

		$query->where('(' . implode(' OR ', $conditions) . ')');
	}

	/**
	 * Restrict the list to a date range.
	 *
	 * @param   \Joomla\Database\DatabaseQuery      $query   Query object.
	 * @param   \Joomla\Database\DatabaseInterface  $db      Database driver.
	 * @param   string                              $column  Column name.
	 * @param   mixed                               $from    Start date.
	 * @param   mixed                               $to      End date.
	 *
	 * @return  void
	 */
	protected function applyDateRangeFilter($query, $db, $column, $from, $to)
	{
		$column = $db->quoteName($column);
		$from   = $this->normalizeFilterDate($from, '00:00:00');
		$to     = $this->normalizeFilterDate($to, '23:59:59');

		if ($from !== null) {
			$query->where($column . ' >= ' . $db->quote($from));
		}

		if ($to !== null) {
			$query->where($column . ' <= ' . $db->quote($to));
		}
	}

	/**
	 * Convert a date informed by the user into an UTC SQL datetime, as stored in the database.
	 *
	 * @param   mixed   $value  Date coming from the filter form.
	 * @param   string  $time   Time to append to the date.
	 *
	 * @return  string|null
	 */
	protected function normalizeFilterDate($value, $time)
	{
		if (!is_string($value)) {
			return null;
		}

		$value = substr(trim($value), 0, 10);

		if ($value === '') {
			return null;
		}

		try {
			$timezone = Factory::getApplication()->getIdentity()->getTimezone();
		} catch (\Throwable $e) {
			$timezone = 'UTC';
		}

		// The calendar field may submit the date in the localized format the user typed in.
		foreach (array('Y-m-d', 'd-m-Y', 'd/m/Y') as $format) {
			$parsed = \DateTime::createFromFormat($format . '|', $value, new \DateTimeZone('UTC'));

			if ($parsed === false) {
				continue;
			}

			$errors = \DateTime::getLastErrors();

			if (is_array($errors) && (!empty($errors['error_count']) || !empty($errors['warning_count']))) {
				continue;
			}

			return Factory::getDate($parsed->format('Y-m-d') . ' ' . $time, $timezone)->toSql();
		}

		return null;
	}

	/**
	 * Resolve the UF siglas of the given items into their full names.
	 *
	 * @param   array  $items  List items.
	 *
	 * @return  array  Map of sigla => name.
	 */
	protected function loadEstadoLabels($items)
	{
		$siglas = array();

		foreach ((array) $items as $item) {
			foreach ($this->splitCsvValue($item->sg_estado ?? '') as $sigla) {
				$siglas[$sigla] = $sigla;
			}
		}

		if ($siglas === []) {
			return array();
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('sg_estado', 'no_estado')))
			->from($db->quoteName('#__tb_estado'))
			->whereIn($db->quoteName('sg_estado'), array_values($siglas), ParameterType::STRING);

		$db->setQuery($query);

		$labels = array();

		foreach ((array) $db->loadObjectList() as $row) {
			$labels[$row->sg_estado] = $row->no_estado;
		}

		return $labels;
	}

	/**
	 * Resolve the IBGE codes of the given items into municipality names.
	 *
	 * @param   array  $items  List items.
	 *
	 * @return  array  Map of co_ibge => name.
	 */
	protected function loadMunicipioLabels($items)
	{
		$codes = array();

		foreach ((array) $items as $item) {
			foreach ($this->splitCsvValue($item->ds_municipios ?? '') as $code) {
				if (is_numeric($code)) {
					$codes[(int) $code] = (int) $code;
				}
			}
		}

		if ($codes === []) {
			return array();
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('m.co_ibge', 'm.no_municipio', 'e.sg_estado')))
			->from($db->quoteName('#__tb_municipio', 'm'))
			->innerJoin(
				$db->quoteName('#__tb_estado', 'e')
				. ' ON ' . $db->quoteName('e.co_estado') . ' = ' . $db->quoteName('m.co_estado')
			)
			->whereIn($db->quoteName('m.co_ibge'), array_values($codes))
			->order($db->quoteName('m.no_municipio') . ' ASC');

		$db->setQuery($query);

		$labels = array();

		foreach ((array) $db->loadObjectList() as $row) {
			$labels[(int) $row->co_ibge] = $row->no_municipio . ' - ' . $row->sg_estado;
		}

		return $labels;
	}

	/**
	 * Split a comma separated column value into a clean list of values.
	 *
	 * @param   mixed  $value  Raw column value.
	 *
	 * @return  array
	 */
	protected function splitCsvValue($value)
	{
		$parts = is_array($value) ? $value : explode(',', (string) $value);
		$parts = array_map('trim', $parts);

		return array_values(array_filter($parts, static function ($item) {
			return $item !== '';
		}));
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
