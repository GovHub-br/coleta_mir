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
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Layout\FileLayout;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use \Comedital\Component\Edital\Site\Helper\EditalHelper;


/**
 * Methods supporting a list of Edital records.
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
	 * @see    JController
	 * @since  1.0.0
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
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// List state information.
		parent::populateState('a.', 'ASC');

		$app = Factory::getApplication();
		$list = $app->getUserState($this->context . '.list');

		$value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', 25));
		$list['limit'] = $value;
		
		$this->setState('list.limit', $value);

		$value = $app->input->get('limitstart', 0, 'uint');
		$this->setState('list.start', $value);

		$ordering  = $this->getUserStateFromRequest($this->context .'.filter_order', 'filter_order', 'a.');
		$direction = strtoupper($this->getUserStateFromRequest($this->context .'.filter_order_Dir', 'filter_order_Dir', 'ASC'));
		
		if(!empty($ordering) || !empty($direction))
		{
			$list['fullordering'] = $ordering . ' ' . $direction;
		}

		$app->setUserState($this->context . '.list', $list);

		

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
			
		// Join over the users for the checked out user.
		$query->select('uc.name AS uEditor');
		$query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');
		// Join over the foreign key 'co_edital'
		$query->select('`#__tb_edital_4309979`.`no_edital` AS editais_fk_value_4309979');
		$query->join('LEFT', '#__tb_edital AS #__tb_edital_4309979 ON #__tb_edital_4309979.`id` = a.`co_edital`');
			
		if (!Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_edital'))
		{
			$query->where('a.state = 1');
		}
		else
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
			

		// Filtering tp_esfera_governamental
		$filter_tp_esfera_governamental = $this->state->get("filter.tp_esfera_governamental");
		if (is_array($filter_tp_esfera_governamental)) {
			$filter_tp_esfera_governamental = array_values(array_filter($filter_tp_esfera_governamental, function($v){ return $v !== "" && $v !== null; }));
		}
		if ((is_array($filter_tp_esfera_governamental) && count($filter_tp_esfera_governamental) > 0) || (!is_array($filter_tp_esfera_governamental) && $filter_tp_esfera_governamental !== "" && $filter_tp_esfera_governamental !== null)) {
			if (is_array($filter_tp_esfera_governamental)) {
				$__vals = array_map([$db, "quote"], $filter_tp_esfera_governamental);
				$query->where($db->quoteName("a.tp_esfera_governamental") . " IN (" . implode(",", $__vals) . ")");
			} else {
				$query->where($db->quoteName("a.tp_esfera_governamental") . " = " . $db->quote($filter_tp_esfera_governamental));
			}
		}

		// Filtering co_edital
		$filter_co_edital = $this->state->get("filter.co_edital");

		if ($filter_co_edital)
		{
			if (!is_array($filter_co_edital)) {
				$query->where($db->quoteName("a.co_edital") . " = " . $db->quote($filter_co_edital));
			}
		}

			
			
			// Add the list ordering clause.
			$orderCol  = $this->state->get('list.ordering', 'a.');
			$orderDirn = $this->state->get('list.direction', 'ASC');

			if ($orderCol && $orderDirn)
			{
				$query->order($db->escape($orderCol . ' ' . $orderDirn));
			}

			return $query;
	}

	/**
	 * Method to get an array of data items
	 *
	 * @return  mixed An array of data on success, false on failure.
	 */
	public function getItems()
	{
		$items = parent::getItems();
		
		foreach ($items as $item)
		{

				if (!empty($item->tp_esfera_governamental))
					{
						$item->tp_esfera_governamental = Text::_('COM_EDITAL_ADESOES_TP_ESFERA_GOVERNAMENTAL_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$item->tp_esfera_governamental))));
					}

			if (isset($item->co_edital))
			{

				$values    = explode(',', $item->co_edital);
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

				$item->co_edital = !empty($textValue) ? implode(', ', $textValue) : $item->co_edital;
			}


			if (isset($item->sg_estado))
			{
				$values    = explode(',', $item->sg_estado);
				$textValue = array();

				foreach ($values as $value)
				{
					if (!empty($value))
					{
						$db    = $this->getDbo();
						$query = "select sg_estado as value, no_estado key_field from #__tb_estado WHERE value = '$value' ";

						$db->setQuery($query);
						$results = $db->loadObject();

						if ($results)
						{
							$textValue[] = $results->key_field;
						}
					}
				}

				$item->sg_estado = !empty($textValue) ? implode(', ', $textValue) : $item->sg_estado;
			}

			if (isset($item->ds_municipios))
			{
				$values    = explode(',', $item->ds_municipios);
				$textValue = array();

				foreach ($values as $value)
				{
					if (!empty($value))
					{
						$db    = $this->getDbo();
						$query = "select co_ibge value, concat(no_municipio,' - ', sg_estado) key_field from #__tb_municipio m inner join #__tb_estado e on e.co_estado = m.co_estado AND #__tb_municipio.value = '$value' ";

						$db->setQuery($query);
						$results = $db->loadObject();

						if ($results)
						{
							$textValue[] = $results->key_field;
						}
					}
				}

				$item->ds_municipios = !empty($textValue) ? implode(', ', $textValue) : $item->ds_municipios;
			}

				if (!empty($item->st_adesao))
					{
						$item->st_adesao = Text::_('COM_EDITAL_ADESOES_ST_ADESAO_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$item->st_adesao))));
					}
		}

		return $items;
	}

	/**
	 * Overrides the default function to check Date fields format, identified by
	 * "_dateformat" suffix, and erases the field if it's not correct.
	 *
	 * @return void
	 */
	protected function loadFormData()
	{
		$app              = Factory::getApplication();
		$filters          = $app->getUserState($this->context . '.filter', array());
		$error_dateformat = false;

		foreach ($filters as $key => $value)
		{
			if (strpos($key, '_dateformat') && !empty($value) && $this->isValidDate($value) == null)
			{
				$filters[$key]    = '';
				$error_dateformat = true;
			}
		}

		if ($error_dateformat)
		{
			$app->enqueueMessage(Text::_("COM_EDITAL_SEARCH_FILTER_DATE_FORMAT"), "warning");
			$app->setUserState($this->context . '.filter', $filters);
		}

		return parent::loadFormData();
	}

	/**
	 * Checks if a given date is valid and in a specified format (YYYY-MM-DD)
	 *
	 * @param   string  $date  Date to be checked
	 *
	 * @return bool
	 */
	private function isValidDate($date)
	{
		$date = str_replace('/', '-', $date);
		return (date_create($date)) ? Factory::getDate($date)->format("Y-m-d") : null;
	}
}
