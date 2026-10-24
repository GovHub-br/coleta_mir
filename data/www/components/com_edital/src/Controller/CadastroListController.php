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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;

/**
 * Lista de cadastro no site (publish, lixeira, duplicar, ordenar).
 *
 * @since  1.0.0
 */
abstract class CadastroListController extends AdminController
{
	/**
	 * Model de formulário usado por publish/delete/duplicate (igual ao admin).
	 *
	 * @var  string
	 */
	protected $formModelName = '';

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
	public function getModel($name = '', $prefix = 'Site', $config = [])
	{
		if ($name === '')
		{
			$name = $this->formModelName;
		}

		return parent::getModel($name, $prefix, array_merge(['ignore_request' => true], $config));
	}

	/**
	 * @return  void
	 */
	public function duplicate()
	{
		$this->checkToken();

		$pks = $this->input->post->get('cid', [], 'array');

		try
		{
			if (empty($pks))
			{
				throw new \Exception(Text::_('JGLOBAL_NO_ITEM_SELECTED'));
			}

			ArrayHelper::toInteger($pks);
			$this->getModel()->duplicate($pks);
			$this->setMessage(Text::_('COM_EDITAL_ITEMS_SUCCESS_DUPLICATED'));
		}
		catch (\Exception $e)
		{
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		$this->setRedirect($this->getRedirectUrlToList());
	}

	/**
	 * @return  void
	 */
	public function saveOrderAjax()
	{
		$this->checkToken();

		$pks   = $this->input->post->get('cid', [], 'array');
		$order = $this->input->post->get('order', [], 'array');

		ArrayHelper::toInteger($pks);
		ArrayHelper::toInteger($order);

		$return = $this->getModel()->saveorder($pks, $order);

		if ($return)
		{
			echo '1';
		}

		Factory::getApplication()->close();
	}

	/**
	 * @return  string
	 */
	protected function getRedirectToListAppend()
	{
		return parent::getRedirectToListAppend() . CadastroHelper::itemIdAppend($this->view_list);
	}
}
