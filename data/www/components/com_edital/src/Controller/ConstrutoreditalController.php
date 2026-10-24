<?php

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class ConstrutoreditalController extends FormController
{
	protected $view_item = 'construtoredital';

	protected $view_list = 'construtoredital';

	protected $context = 'construtoredital';

	public function execute($task)
	{
		CadastroHelper::assertManage();

		return parent::execute($task);
	}

	public function getModel($name = 'Construtoredital', $prefix = 'Site', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}

	protected function getRedirectUrlToItem($recordId = null, $urlVar = 'id'): string
	{
		$id = ($recordId === null || $recordId === '') ? 0 : (int) $recordId;

		return Route::_(CadastroHelper::getFormUrl($this->view_item, $this->view_list, $id), false);
	}

	protected function getRedirectUrlToList(): string
	{
		return CadastroHelper::getViewRoute($this->view_list);
	}

	/**
	 * Method to save a record.
	 *
	 * @param   string  $key     The name of the primary key of the URL variable.
	 * @param   string  $urlVar  The name of the URL variable if different from the primary key (sometimes the primary key is not used in the URL).
	 *
	 * @return  boolean  True if successful, false otherwise.
	 */
	public function save($key = null, $urlVar = 'id')
	{
		// Check for request forgeries.
		$this->checkToken();

		$app = Factory::getApplication();
		/** @var \Comedital\Component\Edital\Site\Model\ConstrutoreditalModel $model */
		$model = $this->getModel();
		$data = $app->input->post->getArray();

		// Validate the posted data.
		$form = $model->getForm();

		if (!$form)
		{
			$app->enqueueMessage(Text::_('COM_EDITAL_ERROR_LOADING_FORM'), 'error');
			return false;
		}

		// Validate the posted data.
		if (!$model->validate($form, $data))
		{
			// Get the validation messages.
			$errors = $model->getErrors();

			foreach ($errors as $error)
			{
				if ($error instanceof \Exception)
				{
					$app->enqueueMessage($error->getMessage(), 'error');
				}
				else
				{
					$app->enqueueMessage($error, 'error');
				}
			}

			// Redirect back to the edit form.
			$id = isset($data['id']) ? (int) $data['id'] : 0;
			$this->setRedirect(Route::_('index.php?option=com_edital&view=construtoredital&id=' . $id, false));

			return false;
		}

		//seta data de publicacao igual a data de abertura
		if (isset($data['dt_abertura']))
		{
			$data['dt_publicacao'] = $data['dt_abertura'];
		}

		// var_dump($data);die();

		// Attempt to save the data.
		if (!$model->save($data))
		{
			// Get the model errors.
			$errors = $model->getErrors();

			foreach ($errors as $error)
			{
				if ($error instanceof \Exception)
				{
					$app->enqueueMessage($error->getMessage(), 'error');
				}
				else
				{
					$app->enqueueMessage($error, 'error');
				}
			}

			// Redirect back to the edit form.
			$id = isset($data['id']) ? (int) $data['id'] : 0;
			$this->setRedirect(Route::_('index.php?option=com_edital&view=construtoredital&id=' . $id, false));

			return false;
		}

		$isNovo = empty($data['id']);
		$recordId = (int) $model->getState('construtoredital.id');

		$app->enqueueMessage(Text::_('COM_EDITAL_ITEM_SAVED_SUCCESSFULLY'), 'success');

		// Edital recém-criado segue para o cadastro das seções e perguntas.
		$destino = $isNovo && $recordId
			? 'index.php?option=com_edital&view=construtorperguntas&id=' . $recordId
			: 'index.php?option=com_edital&view=construtorlista';
		$this->setRedirect(Route::_($destino, false));

		return true;
	}
}
