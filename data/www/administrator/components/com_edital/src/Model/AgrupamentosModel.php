<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Model;

defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

/**
 * Methods supporting a list of Agrupamentos records.
 *
 * @since  1.0.0
 */
class AgrupamentosModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @since   1.6
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
				'no_agrupamento', 'a.no_agrupamento',
				'ds_agrupamento', 'a.ds_agrupamento',
			);
		}

		parent::__construct($config);
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @param   string  $ordering   Elements order
	 * @param   string  $direction  Order direction
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		parent::populateState('a.ordering', 'ASC');

		$context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $context);

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
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string A store id.
	 *
	 * @since   1.0.0
	 */
	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.state');

		return parent::getStoreId($id);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$query->select(
			$this->getState('list.select', 'DISTINCT a.*')
		);
		$query->from('`#__tb_agrupamento` AS a');

		// Join over the users for the checked out user
		$query->select('uc.name AS uEditor');
		$query->join('LEFT', '#__users AS uc ON uc.id = a.checked_out');

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
				$query->where('( a.no_agrupamento LIKE ' . $search . ' )');
			}
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', 'a.ordering');
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

		if (empty($items))
		{
			return $items;
		}

		$ids = [];

		foreach ($items as $item)
		{
			if (empty($item->co_editais))
			{
				continue;
			}

			foreach (explode(',', $item->co_editais) as $id)
			{
				$id = (int) $id;

				if ($id > 0)
				{
					$ids[$id] = $id;
				}
			}
		}

		$nomes = [];

		if ($ids)
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->select($db->quoteName(['id', 'no_edital']))
				->from($db->quoteName('#__tb_edital'))
				->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
			$db->setQuery($query);
			$nomes = $db->loadObjectList('id') ?: [];
		}

		foreach ($items as $item)
		{
			if (empty($item->co_editais))
			{
				$item->editais = Text::_('COM_EDITAL_AGRUPAMENTOS_CO_EDITAIS_TODOS');
				continue;
			}

			$labels = [];

			foreach (explode(',', $item->co_editais) as $id)
			{
				$id = (int) $id;

				if (isset($nomes[$id]))
				{
					$labels[] = $nomes[$id]->no_edital;
				}
			}

			$item->editais = implode(', ', $labels);
		}

		return $items;
	}
}
