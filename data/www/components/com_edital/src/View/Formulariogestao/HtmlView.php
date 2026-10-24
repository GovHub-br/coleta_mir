<?php

namespace Comedital\Component\Edital\Site\View\Formulariogestao;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->authorise('respostas.manage', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->state = $this->get('State');
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$model = new \Comedital\Component\Edital\Site\Model\FormulariogestaoModel();

		foreach ((array) $this->items as $item)
		{
			$item->tamanho = $model->getTamanhoFormulario($item->id);
			$item->is_open = Factory::getDate() >= Factory::getDate($item->dt_abertura)
				&& Factory::getDate() <= Factory::getDate($item->dt_encerramento);
		}

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->document->setTitle('Gestão de formulários');
		parent::display($tpl);
	}
}