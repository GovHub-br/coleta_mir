<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/**
 * Perguntas list controller class.
 *
 * @since  1.0.0
 */
class PerguntasController extends AdminController
{
	/**
	 * Method to clone existing Perguntas
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 */
	public function duplicate()
	{
		// Check for request forgeries
		$this->checkToken();

		// Get id(s)
		$pks = $this->input->post->get('cid', array(), 'array');

		try
		{
			if (empty($pks))
			{
				throw new \Exception(Text::_('COM_EDITAL_NO_ELEMENT_SELECTED'));
			}

			ArrayHelper::toInteger($pks);
			$model = $this->getModel();
			$model->duplicate($pks);
			$this->setMessage(Text::_('COM_EDITAL_ITEMS_SUCCESS_DUPLICATED'));
		}
		catch (\Exception $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		$this->setRedirect('index.php?option=com_edital&view=perguntas');
	}

	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    Optional. Model name
	 * @param   string  $prefix  Optional. Class prefix
	 * @param   array   $config  Optional. Configuration array for model
	 *
	 * @return  object	The Model
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Pergunta', $prefix = 'Administrator', $config = array())
	{
		return parent::getModel($name, $prefix, array('ignore_request' => true));
	}

	/**
	 * Marca as perguntas selecionadas como requeridas.
	 */
	public function requerida()
	{
		$this->definirRequerida('sim');
	}

	/**
	 * Desmarca as perguntas selecionadas como requeridas.
	 */
	public function naoRequerida()
	{
		$this->definirRequerida('nao');
	}

	protected function definirRequerida(string $valor): void
	{
		$this->checkToken();

		$cid = array_filter((array) $this->input->get('cid', [], 'int'));

		if ($cid === [])
		{
			$this->setMessage(Text::_('COM_EDITAL_NO_ITEM_SELECTED'), 'warning');
		}
		else
		{
			$model = $this->getModel();

			if (!$model->setRequerida($cid, $valor))
			{
				$this->setMessage($model->getError(), 'error');
			}
			else
			{
				$chave = $valor === 'sim' ? 'COM_EDITAL_N_PERGUNTAS_REQUERIDA' : 'COM_EDITAL_N_PERGUNTAS_NAO_REQUERIDA';
				$this->setMessage(Text::plural($chave, count($cid)));
			}
		}

		$this->setRedirect($this->getRedirectUrlToList());
	}

	

	/**
	 * Method to save the submitted ordering values for records via AJAX.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 *
	 * @throws  Exception
	 */
	public function saveOrderAjax()
	{
		// Get the input
		$pks   = $this->input->post->get('cid', array(), 'array');
		$order = $this->input->post->get('order', array(), 'array');

		// Sanitize the input
		ArrayHelper::toInteger($pks);
		ArrayHelper::toInteger($order);

		// Get the model
		$model = $this->getModel();

		// Save the ordering
		$return = $model->saveorder($pks, $order);

		if ($return)
		{
			echo "1";
		}

		// Close the application
		Factory::getApplication()->close();
	}
}
