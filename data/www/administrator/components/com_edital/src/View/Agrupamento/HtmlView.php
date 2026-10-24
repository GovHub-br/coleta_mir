<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Administrator\View\Agrupamento;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use \Comedital\Component\Edital\Administrator\Helper\EditalHelper;
use \Joomla\CMS\Language\Text;

/**
 * View class for a single Agrupamento.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $state;

	protected $item;

	protected $form;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  Template name
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function display($tpl = null)
	{
		$this->state = $this->get('State');
		$this->item  = $this->get('Item');
		$this->form  = $this->get('Form');

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user       = Factory::getApplication()->getIdentity();
		$isNew      = ($this->item->id == 0);
		$checkedOut = false;

		if (isset($this->item->checked_out))
		{
			$checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		}

		$canDo = EditalHelper::getActions();

		ToolbarHelper::title(Text::_('COM_EDITAL_TITLE_AGRUPAMENTO'), 'generic');

		if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create')))
		{
			ToolbarHelper::apply('agrupamento.apply', 'JTOOLBAR_APPLY');
			ToolbarHelper::save('agrupamento.save', 'JTOOLBAR_SAVE');
		}

		if (!$checkedOut && $canDo->get('core.create'))
		{
			ToolbarHelper::custom('agrupamento.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
		}

		if (!$isNew && $canDo->get('core.create'))
		{
			ToolbarHelper::custom('agrupamento.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
		}

		if ($this->state->params->get('save_history', 1) && $user->authorise('core.edit'))
		{
			ToolbarHelper::versions('com_edital.agrupamento', $this->item->id);
		}

		if (empty($this->item->id))
		{
			ToolbarHelper::cancel('agrupamento.cancel', 'JTOOLBAR_CANCEL');
		}
		else
		{
			ToolbarHelper::cancel('agrupamento.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}
