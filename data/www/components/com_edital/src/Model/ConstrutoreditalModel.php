<?php

namespace Comedital\Component\Edital\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Form\Form;
use Comedital\Component\Edital\Administrator\Model\EditalModel as AdminEditalModel;

class ConstrutoreditalModel extends ListModel
{
	protected $item = null;
	protected $_errors = array();

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

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      An optional array of data for the form to interogate.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \JForm|boolean  A \JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_edital/forms');
		Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_edital/src/Field');

		// Get the form from admin model
		$adminModel = new AdminEditalModel();
		$form = $adminModel->getForm($data, $loadData);

		if (empty($form))
		{
			return false;
		}

		// Disable state field if user doesn't have permission
		if (!Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_edital'))
		{
			$form->setFieldAttribute('state', 'disabled', 'true');
			$form->setFieldAttribute('state', 'filter', 'unset');
		}

		return $form;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  \stdClass|boolean  Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		if ($this->item === null)
		{
			$this->item = false;

			if (empty($pk))
			{
				$pk = $this->getState($this->getName() . '.id');
			}

			$pk = (!empty($pk)) ? (int) $pk : 0;

			if ($pk > 0)
			{
				// Try to load the data from database.
				$db = $this->getDbo();
				$query = $db->getQuery(true)
					->select('*')
					->from($db->quoteName('#__tb_edital'))
					->where($db->quoteName('id') . ' = ' . $pk);

				$db->setQuery($query);
				$this->item = $db->loadObject();
			}
		}

		return $this->item;
	}

	/**
	 * Save method - delegates to AdminEditalModel
	 *
	 * @param   array  $data  The data to save
	 *
	 * @return  boolean  True on success, false on failure
	 */
	public function save($data)
	{
		// Convert datetime fields from dd/mm/yyyy HH:mm to yyyy-mm-dd HH:mm:ss format
		if (!empty($data['dt_abertura']))
		{
			$data['dt_abertura'] = $this->convertDateTimeFormat($data['dt_abertura']);
		}
		
		if (!empty($data['dt_encerramento']))
		{
			$data['dt_encerramento'] = $this->convertDateTimeFormat($data['dt_encerramento']);
		}
		
		if (!empty($data['dt_publicacao']))
		{
			$data['dt_publicacao'] = $this->convertDateTimeFormat($data['dt_publicacao']);
		}
		
		$adminModel = new AdminEditalModel();
		$result = $adminModel->save($data);

		if ($result)
		{
			$this->setState('construtoredital.id', (int) $adminModel->getState($adminModel->getName() . '.id'));
		}
		
		// Copy any errors from admin model to this model
		if ($errors = $adminModel->getErrors())
		{
			$this->_errors = array_merge($this->_errors ?? array(), (array) $errors);
		}
		
		return $result;
	}

	/**
	 * Convert datetime from dd/mm/yyyy HH:mm to yyyy-mm-dd HH:mm:ss format
	 *
	 * @param   string  $dateTime  DateTime string in dd/mm/yyyy HH:mm format
	 *
	 * @return  string  DateTime string in yyyy-mm-dd HH:mm:ss format
	 */
	private function convertDateTimeFormat($dateTime)
	{
		if (empty($dateTime))
		{
			return '';
		}

		// Try to parse the datetime string
		try
		{
			// Handle format: dd/mm/yyyy HH:mm
			if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $dateTime, $matches))
			{
				$day = $matches[1];
				$month = $matches[2];
				$year = $matches[3];
				$hour = $matches[4];
				$minute = $matches[5];
				
				return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
			}
			
			// Handle format: yyyy-mm-dd HH:mm:ss (already correct)
			if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $dateTime))
			{
				return $dateTime;
			}
			
			// Handle format: yyyy-mm-dd (just date)
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTime))
			{
				return $dateTime . ' 00:00:00';
			}
		}
		catch (\Exception $e)
		{
			// Return original if parsing fails
		}

		return $dateTime;
	}

	/**
	 * Method to validate form data.
	 *
	 * @param   Form   $form  The form to validate against.
	 * @param   array  $data  The data to validate.
	 *
	 * @return  array|boolean  Array of filtered data if valid, false otherwise.
	 */
	public function validate($form, $data)
	{
		// O filtro server_utc do formulário exige o formato Y-m-d H:i:s.
		foreach (array('dt_abertura', 'dt_encerramento', 'dt_publicacao') as $campo)
		{
			if (!empty($data[$campo]))
			{
				$data[$campo] = $this->convertDateTimeFormat($data[$campo]);
			}
		}

		$adminModel = new AdminEditalModel();
		$result = $adminModel->validate($form, $data);
		
		// Copy any errors from admin model to this model
		if ($errors = $adminModel->getErrors())
		{
			$this->_errors = array_merge($this->_errors ?? array(), (array) $errors);
		}
		
		return $result;
	}

	/**
	 * Get the model errors.
	 *
	 * @return  array  An array of error messages or Exception objects.
	 */
	public function getErrors()
	{
		return $this->_errors ?? array();
	}
}
