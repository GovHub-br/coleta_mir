<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Controller;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Formulário de cadastro no site. allowAdd/allowEdit permanecem os do FormController.
 *
 * @since  1.0.0
 */
abstract class CadastroFormController extends FormController
{
	/**
	 * @param   string  $task
	 *
	 * @return  mixed
	 */
	public function execute($task)
	{
		CadastroHelper::assertManage();

		return parent::execute($task);
	}

	/**
	 * @param   string  $name
	 * @param   string  $prefix
	 * @param   array   $config
	 *
	 * @return  object
	 */
	public function getModel($name = '', $prefix = 'Site', $config = ['ignore_request' => true])
	{
		if ($name === '')
		{
			$name = $this->context;
		}

		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * @param   integer  $recordId
	 * @param   string   $urlVar
	 *
	 * @return  string
	 */
	protected function getRedirectUrlToItem($recordId = null, $urlVar = 'id'): string
	{
		$id = ($recordId === null || $recordId === '') ? 0 : (int) $recordId;

		return Route::_(CadastroHelper::getFormUrl($this->view_item, $this->view_list, $id), false);
	}

	/**
	 * @return  string
	 */
	protected function getRedirectUrlToList(): string
	{
		return CadastroHelper::getViewRoute($this->view_list);
	}
}
