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
 * Resposta model.
 *
 * @since  1.0.0
 */
class RespostaModel extends ItemModel
{
	public $_item;

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws Exception
	 */
	protected function populateState()
	{
		$app  = Factory::getApplication('com_edital');
		$user = $app->getIdentity();

		// Check published state
		if ((!$user->authorise('core.edit.state', 'com_edital')) && (!$user->authorise('core.edit', 'com_edital')))
		{
			$this->setState('filter.published', 1);
			$this->setState('filter.archived', 2);
		}

		// Load state from the request userState on edit or from the passed variable on default
		if (Factory::getApplication()->input->get('layout') == 'edit')
		{
			$id = Factory::getApplication()->getUserState('com_edital.edit.resposta.id');
		}
		else
		{
			$id = Factory::getApplication()->input->get('id');
			Factory::getApplication()->setUserState('com_edital.edit.resposta.id', $id);
		}

		$this->setState('resposta.id', $id);

		// Load the parameters.
		$params       = $app->getParams();
		$params_array = $params->toArray();

		if (isset($params_array['item_id']))
		{
			$this->setState('resposta.id', $params_array['item_id']);
		}

		$this->setState('params', $params);
	}

	/**
	 * Method to get an object.
	 *
	 * @param   integer $id The id of the object to get.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function getItem($id = null)
	{
		if ($this->_item === null)
		{
			$this->_item = false;

			if (empty($id))
			{
				$id = $this->getState('resposta.id');
			}

			// Get a level row instance.
			$table = $this->getTable();

			// Attempt to load the row.
			if ($table && $table->load($id))
			{
				// Check published state.
				if ($published = $this->getState('filter.published'))
				{
					if (isset($table->state) && $table->state != $published)
					{
						throw new \Exception(Text::_('COM_EDITAL_ITEM_NOT_LOADED'), 403);
					}
				}

				// Convert the Table to a clean CMSObject.
				$properties  = $table->getProperties(1);
				$this->_item = ArrayHelper::toObject($properties, CMSObject::class);
			}

			if (empty($this->_item))
			{
				throw new \Exception(Text::_('COM_EDITAL_ITEM_NOT_LOADED'), 404);
			}
		}

		$container = \Joomla\CMS\Factory::getContainer();
		$userFactory = $container->get(UserFactoryInterface::class);

		if (isset($this->_item->created_by))
		{
			$user = $userFactory->loadUserById($this->_item->created_by);
			$this->_item->created_by_name = $user->name;
		}

		if (isset($this->_item->modified_by))
		{
			$user = $userFactory->loadUserById($this->_item->modified_by);
			$this->_item->modified_by_name = $user->name;
		}

		// Load pergunta data if co_pergunta is set
		if (isset($this->_item->co_pergunta) && $this->_item->co_pergunta != '')
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true);

			$query
				->select('p.*')
				->from($db->quoteName('#__tb_pergunta', 'p'))
				->where($db->quoteName('p.id') . ' = ' . $db->quote($this->_item->co_pergunta));

			$db->setQuery($query);
			$pergunta = $db->loadObject();

			if ($pergunta)
			{
				$this->_item->pergunta = $pergunta;
			}
		}

		// Load adesao data if co_adesao is set
		if (isset($this->_item->co_adesao) && $this->_item->co_adesao != '')
		{
			$db    = $this->getDbo();
			$query = $db->getQuery(true);

			$query
				->select('a.*')
				->from($db->quoteName('#__tb_adesao', 'a'))
				->where($db->quoteName('a.id') . ' = ' . $db->quote($this->_item->co_adesao));

			$db->setQuery($query);
			$adesao = $db->loadObject();

			if ($adesao)
			{
				$this->_item->adesao = $adesao;
			}
		}

		return $this->_item;
	}

	/**
	 * Get respostas by adesao ID
	 *
	 * @param   integer $adesaoId The adesao ID
	 *
	 * @return  mixed    Array of responses on success, false on failure.
	 */
	public function getRespostasByAdesao($adesaoId)
	{
		if (empty($adesaoId))
		{
			return false;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select('r.*, p.no_pergunta, p.tp_pergunta, p.st_obrigatoria')
			->from($db->quoteName('#__tb_resposta', 'r'))
			->leftJoin($db->quoteName('#__tb_pergunta', 'p') . ' ON p.id = r.co_pergunta')
			->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote($adesaoId))
			->order('p.ordering ASC');

		$db->setQuery($query);
		return $db->loadObjectList();
	}

	/**
	 * Get resposta by adesao and pergunta IDs
	 *
	 * @param   integer $adesaoId The adesao ID
	 * @param   integer $perguntaId The pergunta ID
	 *
	 * @return  mixed    Object on success, false on failure.
	 */
	public function getRespostaByAdesaoPergunta($adesaoId, $perguntaId)
	{
		if (empty($adesaoId) || empty($perguntaId))
		{
			return false;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true);

		$query
			->select('r.*')
			->from($db->quoteName('#__tb_resposta', 'r'))
			->where($db->quoteName('r.co_adesao') . ' = ' . $db->quote($adesaoId))
			->where($db->quoteName('r.co_pergunta') . ' = ' . $db->quote($perguntaId));

		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * Return active answers keyed by question ID.
	 */
	public function getRespostasMapByAdesao(int $adesaoId): array
	{
		if ($adesaoId <= 0)
		{
			return [];
		}

		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('r.*')
			->from($db->quoteName('#__tb_resposta', 'r'))
			->where($db->quoteName('r.co_adesao') . ' = ' . $adesaoId)
			->where($db->quoteName('r.state') . ' = 1');

		$db->setQuery($query);

		return $db->loadObjectList('co_pergunta') ?: [];
	}

	/**
	 * Get an instance of Table class
	 *
	 * @param   string $type   Name of the Table class to get an instance of.
	 * @param   string $prefix Prefix for the table class name. Optional.
	 * @param   array  $config Array of configuration values for the Table object. Optional.
	 *
	 * @return  Table|bool Table if success, false on failure.
	 */
	public function getTable($type = 'Resposta', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to check in an item.
	 *
	 * @param   integer $id The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function checkin($id = null)
	{
		// Get the id.
		$id = (!empty($id)) ? $id : (int) $this->getState('resposta.id');
				
		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Attempt to check the row in.
			if (method_exists($table, 'checkin'))
			{
				if (!$table->checkin($id))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Method to check out an item for editing.
	 *
	 * @param   integer $id The id of the row to check out.
	 *
	 * @return  boolean True on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function checkout($id = null)
	{
		// Get the user id.
		$id = (!empty($id)) ? $id : (int) $this->getState('resposta.id');

		if ($id)
		{
			// Initialise the table
			$table = $this->getTable();

			// Get the current user object.
			$user = Factory::getApplication()->getIdentity();

			// Attempt to check the row out.
			if (method_exists($table, 'checkout'))
			{
				if (!$table->checkout($user->get('id'), $id))
				{
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Publish the element
	 *
	 * @param   int $id    Item id
	 * @param   int $state Publish state
	 *
	 * @return  boolean
	 */
	public function publish($id, $state)
	{
		$table = $this->getTable();
				
		$table->load($id);
		$table->state = $state;

		return $table->store();
	}

	/**
	 * Method to delete an item
	 *
	 * @param   int $id Element id
	 *
	 * @return  bool
	 */
	public function delete($id)
	{
		$table = $this->getTable();

		return $table->delete($id);
	}
}
