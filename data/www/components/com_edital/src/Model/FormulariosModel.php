<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Model;

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
 * Methods supporting a list of Formulario records (tp_edital = 'formulario').
 *
 * @since  1.0.0
 */
class FormulariosModel extends ListModel
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
				'no_edital', 'a.no_edital',
				'ds_edital', 'a.ds_edital',
				'dt_abertura', 'a.dt_abertura',
				'dt_encerramento', 'a.dt_encerramento',
				'dt_publicacao', 'a.dt_publicacao',
				'tp_edital', 'a.tp_edital',
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
		parent::populateState("a.dt_abertura", "DESC");

		$app = Factory::getApplication();
		$list = $app->getUserState($this->context . '.list');

		$value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', 25));
		$list['limit'] = $value;
		
		$this->setState('list.limit', $value);

		$value = $app->input->get('limitstart', 0, 'uint');
		$this->setState('list.start', $value);

		$ordering  = $this->getUserStateFromRequest($this->context .'.filter_order', 'filter_order', "a.dt_abertura");
		$direction = strtoupper($this->getUserStateFromRequest($this->context .'.filter_order_Dir', 'filter_order_Dir', "DESC"));
		
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

		$query->from('`#__tb_edital` AS a');
		
		// Join over the users for the checked out user.
		$query->select('uc.name AS uEditor');
		$query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');

		// Filter by tp_edital = 'formulario'
		$query->where('a.tp_edital = ' . $db->quote('formulario'));
		
		// Filter by published state
		$query->where('a.state = 1');
		$query->where('a.dt_publicacao <= ' . $db->quote(Factory::getDate()->toSql()));

		$this->applyFiltroEsferaSolicitante($query, $db);

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
				$query->where('(a.no_edital LIKE ' . $search . ' OR a.ds_edital LIKE ' . $search . ')');
			}
		}

		// Add the list ordering clause.
		$orderCol  = $this->state->get('list.ordering', "a.dt_abertura");
		$orderDirn = $this->state->get('list.direction', "DESC");

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
		
		return $items;
	}

	/**
	 * Check if a form is available (open for filling)
	 *
	 * @param   object  $item  The edital item
	 *
	 * @return  boolean
	 */
	public function isAvailable($item)
	{
		$now = Factory::getDate();
		$opening = Factory::getDate($item->dt_abertura);
		$closing = Factory::getDate($item->dt_encerramento);

		return $now >= $opening && $now <= $closing;
	}

	/**
	 * Check if a form is closed (expired)
	 *
	 * @param   object  $item  The edital item
	 *
	 * @return  boolean
	 */
	public function isClosed($item)
	{
		return !$this->isAvailable($item);
	}

    public function getTamanhoFormulario($idEdital){
        $db    = $this->getDbo();
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
              ->from('`#__tb_pergunta`')
              ->where('co_edital = ' . (int) $idEdital)
              ->where('state = 1')
              ->where('tp_pergunta NOT IN (' . $db->quote('informativo') . ')');

        $db->setQuery($query);
        $qtPerguntas =  (int) $db->loadResult();    

        $query = $db->getQuery(true);
        $query->select('COUNT(DISTINCT co_agrupamento)')
              ->from('`#__tb_pergunta`')
              ->where('co_edital = ' . (int) $idEdital)
              ->where('state = 1')
              ->where('tp_pergunta NOT IN (' . $db->quote('informativo') . ')');

        $db->setQuery($query);
        $qtAgrupamentos =  (int) $db->loadResult();    
        
		return [
            'qtPerguntas' => $qtPerguntas,
            'qtAgrupamentos' => $qtAgrupamentos
        ];
    }

	/**
	 * Restricts forms to the solicitante modality of the logged-in user.
	 */
	protected function applyFiltroEsferaSolicitante($query, $db): void
	{
		$userId = (int) Factory::getApplication()->getIdentity()->id;
		$empty  = '(' . $db->quoteName('a.tp_esfera_solicitante') . ' IS NULL OR '
			. $db->quoteName('a.tp_esfera_solicitante') . ' = ' . $db->quote('') . ')';

		if ($userId <= 0)
		{
			$query->where($empty);

			return;
		}

		$esferasQuery = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('tp_esfera_governamental'))
			->from($db->quoteName('#__tb_adesao'))
			->where($db->quoteName('created_by') . ' = ' . $userId)
			->where($db->quoteName('state') . ' = 1')
			->where($db->quoteName('tp_esfera_governamental') . ' != ' . $db->quote(''));
		$db->setQuery($esferasQuery);
		$esferas = array_filter((array) $db->loadColumn());

		if ($esferas === [])
		{
			$query->where($empty);

			return;
		}

		$quoted = implode(',', array_map([$db, 'quote'], $esferas));
		$query->where('(' . $empty . ' OR ' . $db->quoteName('a.tp_esfera_solicitante') . ' IN (' . $quoted . '))');
	}
}
