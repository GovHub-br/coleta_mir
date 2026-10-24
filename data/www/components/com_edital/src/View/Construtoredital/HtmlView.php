<?php

namespace Comedital\Component\Edital\Site\View\Construtoredital;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $form;
	protected $item;
	protected $editalOptions;

	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('respostas.manage', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the model (ConstrutoreditalModel - default model for this view)
		/** @var \Comedital\Component\Edital\Site\Model\ConstrutoreditalModel $model */
		$model = $this->getModel();
		
		if (!$model)
		{
			throw new \Exception('Modelo não encontrado');
		}
		
		// Get the form
		$this->form = $model->getForm();
		
		// Get the item (for edit mode) or null for new
		$id = $app->input->getInt('id', 0);
		if ($id)
		{
			$this->item = $model->getItem($id);
		}
		else
		{
			// Create a new item object with default values
			$this->item = (object) array(
				'id' => 0,
				'no_edital' => '',
				'dt_abertura' => '',
				'dt_encerramento' => '',
				'ds_edital' => '',
				'state' => 1,
				'tp_edital' => 'formulario',
				'tp_esfera_solicitante' => '',
				'co_edital_adesao' => 0
			);
		}

		// Get list of editais for co_edital_adesao dropdown
		$this->editalOptions = $this->getEditalOptions();

		if (count((array) $this->get('Errors')))
		{
			throw new \Exception(implode("\n", (array) $this->get('Errors')));
		}

		$this->document->setTitle('Construtor de Formulários');
		parent::display($tpl);
	}

	/**
	 * Get list of all editais for the dropdown
	 *
	 * @return  array  Array of edital objects with value and text properties
	 */
	private function getEditalOptions()
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		
		$query = $db->getQuery(true)
			->select('0 AS value, "- Selecione -" AS text')
			->union(
				$db->getQuery(true)
					->select('id AS value, CONCAT(id, " - ", no_edital) AS text')
					->from($db->quoteName('#__tb_edital'))
					->where($db->quoteName('state') . ' != -2')
					->order('text ASC')
			);
		
		$db->setQuery($query);
		return $db->loadObjectList();
	}
}
