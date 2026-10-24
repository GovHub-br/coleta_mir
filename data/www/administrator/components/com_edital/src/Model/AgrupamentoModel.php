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

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use Comedital\Component\Edital\Administrator\Helper\EditalHelper;

/**
 * Agrupamento model.
 *
 * @since  1.0.0
 */
class AgrupamentoModel extends AdminModel
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_EDITAL';

	/**
	 * @var    string  Alias to manage history control
	 *
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_edital.agrupamento';

	/**
	 * @var    null  Item data
	 *
	 * @since  1.0.0
	 */
	protected $item = null;

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param   string  $type    The table type to instantiate
	 * @param   string  $prefix  A prefix for the table class name. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  Table    A database object
	 *
	 * @since   1.0.0
	 */
	public function getTable($type = 'Agrupamento', $prefix = 'Administrator', $config = array())
	{
		return parent::getTable($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \JForm|boolean  A \JForm object on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		$form = $this->loadForm(
			'com_edital.agrupamento',
			'agrupamento',
			array(
				'control'   => 'jform',
				'load_data' => $loadData,
			)
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		$data = Factory::getApplication()->getUserState($this->option . '.edit.' . $this->getName() . '.data', array());

		if (empty($data))
		{
			if ($this->item === null)
			{
				$this->item = $this->getItem();
			}

			$data = $this->item;
		}

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed    Object on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			if (!empty($item->co_editais) && is_string($item->co_editais))
			{
				$item->co_editais = array_values(array_filter(explode(',', $item->co_editais)));
			}
			elseif (empty($item->co_editais))
			{
				$item->co_editais = [];
			}
		}

		return $item;
	}

	/**
	 * Method to duplicate an Agrupamento
	 *
	 * @param   array  &$pks  An array of primary key IDs.
	 *
	 * @return  boolean  True if successful.
	 *
	 * @throws  \Exception
	 */
	public function duplicate(&$pks)
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('core.create', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
		}

		PluginHelper::importPlugin($this->events_map['save']);

		$table = $this->getTable();

		foreach ($pks as $pk)
		{
			if ($table->load($pk, true))
			{
				$table->id = 0;

				if (!$table->check())
				{
					throw new \Exception($table->getError());
				}

				if (!$table->store())
				{
					throw new \Exception($table->getError());
				}
			}
			else
			{
				throw new \Exception($table->getError());
			}
		}

		return true;
	}

	/**
	 * Prepare and sanitise the table prior to saving.
	 *
	 * @param   Table  $table  Table Object
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function prepareTable($table)
	{
		if (empty($table->id))
		{
			// Set ordering for new items
			$db    = $this->getDbo();
			$query = $db->getQuery(true)
				->select('MAX(ordering)')
				->from($db->quoteName('#__tb_agrupamento'));
			$db->setQuery($query);
			$max = $db->loadResult();

			$table->ordering = $max + 1;
		}
	}

	/**
	 * @param   object  $record
	 *
	 * @return  boolean
	 */
	protected function canDelete($record)
	{
		if (empty($record->id) || !parent::canDelete($record))
		{
			return false;
		}

		if (EditalHelper::countRelated('#__tb_pergunta', 'co_agrupamento', (int) $record->id) > 0)
		{
			Factory::getApplication()->enqueueMessage(Text::_('COM_EDITAL_ERROR_AGRUPAMENTO_HAS_PERGUNTAS'), 'error');

			return false;
		}

		return true;
	}
}
