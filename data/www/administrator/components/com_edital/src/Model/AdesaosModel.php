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
 * Methods supporting a list of Adesaos records.
 *
 * @since  1.0.0
 */
class AdesaosModel extends ListModel
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
		parent::populateState('id', 'ASC');

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
		$query->select('`#__tb_edital_4302557`.`no_edital` AS editais_fk_value_4302557');
		$query->join('LEFT', '#__tb_edital AS #__tb_edital_4302557 ON #__tb_edital_4302557.`id` = a.`co_edital`');
		

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
		

		// Filtering tp_esfera_governamental
		$filter_tp_esfera_governamental = $this->state->get("filter.tp_esfera_governamental");
		if (is_array($filter_tp_esfera_governamental) && count($filter_tp_esfera_governamental) === 1) {
			$filter_tp_esfera_governamental = reset($filter_tp_esfera_governamental);
		}

		if (!empty($filter_tp_esfera_governamental) && !(is_array($filter_tp_esfera_governamental) && count(array_filter($filter_tp_esfera_governamental, function($v){return $v !== "" && $v !== null;})) === 0))
		{
			if (!is_array($filter_tp_esfera_governamental)) {
				$query->where($db->quoteName("a.tp_esfera_governamental") . " = " . $db->quote($filter_tp_esfera_governamental));
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

		// Filtering sg_estado
		$filter_sg_estado = $this->state->get("filter.sg_estado");
		if (is_array($filter_sg_estado) && count($filter_sg_estado) === 1) {
			$filter_sg_estado = reset($filter_sg_estado);
		}

		if (!empty($filter_sg_estado) && !(is_array($filter_sg_estado) && count(array_filter($filter_sg_estado, function($v){return $v !== "" && $v !== null;})) === 0))
		{
			if (is_array($filter_sg_estado)) {
				$validVals = array_filter($filter_sg_estado, function($v) { return $v !== "" && $v !== null; });
				if (!empty($validVals)) {
					$multiConditions = [];
					foreach ($validVals as $val) {
						$multiConditions[] = "FIND_IN_SET(" . $db->quote($val) . ", " . $db->quoteName("a.sg_estado") . ")";
					}
					$query->where("(" . implode(" OR ", $multiConditions) . ")");
				}
			} else {
				$query->where("FIND_IN_SET(" . $db->quote($filter_sg_estado) . ", " . $db->quoteName("a.sg_estado") . ")");
			}
		}
		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', 'id');
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
					$oneItem->tp_esfera_governamental = !empty($oneItem->tp_esfera_governamental) ? Text::_('COM_EDITAL_ADESAOS_TP_ESFERA_GOVERNAMENTAL_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->tp_esfera_governamental)))) : '';

			if (isset($oneItem->co_edital))
			{
				$values    = explode(',', $oneItem->co_edital);
				$textValue = array();

				foreach ($values as $value)
				{
					$db    = $this->getDbo();
					$query = $db->getQuery(true);
					$query
						->select('`#__tb_edital_4302557`.`no_edital`')
						->from($db->quoteName('#__tb_edital', '#__tb_edital_4302557'))
						->where($db->quoteName('#__tb_edital_4302557.id') . ' = '. $db->quote($db->escape($value)));

					$db->setQuery($query);
					$results = $db->loadObject();

					if ($results)
					{
						$textValue[] = $results->no_edital;
					}
				}

				$oneItem->co_edital = !empty($textValue) ? implode(', ', $textValue) : $oneItem->co_edital;
			}

			// Get the title of every option selected.

			$options      = explode(',', $oneItem->sg_estado);

			$options_text = array();

			foreach ((array) $options as $option)
			{

				if ($option !== "")
				{
					$options_text[] = Text::_('COM_EDITAL_ADESAOS_SG_ESTADO_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$option))));
				}
			}

			$oneItem->sg_estado = !empty($options_text) ? implode(', ', $options_text) : $oneItem->sg_estado;
					$oneItem->st_adesao = !empty($oneItem->st_adesao) ? Text::_('COM_EDITAL_ADESAOS_ST_ADESAO_OPTION_' . preg_replace('/[^A-Za-z0-9\_-]/', '',strtoupper(str_replace(' ', '_',$oneItem->st_adesao)))) : '';
		}

		return $items;
	}
}
