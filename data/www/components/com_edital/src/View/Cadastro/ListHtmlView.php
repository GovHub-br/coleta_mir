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

/**
 * Listagem de cadastro no site.
 *
 * @since  1.0.0
 */
class ListHtmlView extends BaseHtmlView
{
	public $items;
	public $pagination;
	public $state;
	public $filterForm;
	public $activeFilters;
	public $params;
	public $canDo;
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

		$app = Factory::getApplication();

		$this->state         = $this->get('State');
		$this->items         = $this->get('Items');
		$this->pagination    = $this->get('Pagination');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');
		$this->params        = $app->getParams('com_edital');
		$this->canDo         = EditalHelper::getActions();

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->prepareCadastroDocument();
		parent::display($tpl);
	}

	/**
	 * @return  void
	 */
	protected function prepareCadastroDocument()
	{
		$app   = Factory::getApplication();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();

		if ($menu)
		{
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else
		{
			$this->params->def('page_heading', Text::_('COM_EDITAL_DEFAULT_PAGE_TITLE'));
		}

		$title = $this->params->get('page_title', '');

		if (empty($title))
		{
			$title = $app->get('sitename');
		}
		elseif ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);
	}
}
