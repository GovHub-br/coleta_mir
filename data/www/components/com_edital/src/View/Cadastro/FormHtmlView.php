<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Cadastro;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Administrator\Helper\EditalHelper;
use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Formulário de cadastro no site.
 *
 * @since  1.0.0
 */
class FormHtmlView extends BaseHtmlView
{
	public $state;
	public $item;
	public $form;
	public $params;
	public $canDo;
	public $checkedOut = false;
	public $listView = '';
	public $formController = '';

	/**
	 * @param   string  $tpl
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	public function display($tpl = null)
	{
		CadastroHelper::assertManage();

		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		// JCE no site quebra (GET com_jce → 403/HTML). O campo editor cai no padrão
		// da aplicação quando editor="|" — força TinyMCE nesta tela.
		if (PluginHelper::isEnabled('editors', 'tinymce'))
		{
			$app->set('editor', 'tinymce');
		}

		$this->state  = $this->get('State');
		$this->item   = $this->get('Item');
		$this->form   = $this->get('Form');
		$this->params = $app->getParams('com_edital');
		$this->canDo  = EditalHelper::getActions();

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		if (!empty($this->item->checked_out)
			&& (int) $this->item->checked_out !== (int) $user->id)
		{
			$this->checkedOut = true;
			$app->enqueueMessage(Text::_('COM_EDITAL_CADASTRO_CHECKED_OUT'), 'warning');
		}

		$this->afterLoad();
		$this->prepareCadastroDocument();
		parent::display($tpl);
	}

	/**
	 * Extensão opcional após carregar item/form.
	 *
	 * @return  void
	 */
	protected function afterLoad()
	{
	}

	/**
	 * @return  void
	 */
	protected function prepareCadastroDocument()
	{
		$app  = Factory::getApplication();
		$isNew = empty($this->item->id);
		$key   = $isNew ? 'COM_EDITAL_CADASTRO_NOVO' : 'COM_EDITAL_CADASTRO_EDITAR';
		$title = Text::_($key);

		if ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);
		$this->params->def('page_heading', Text::_($key));
	}
}
