<?php

namespace Comedital\Component\Edital\Site\View\Construtorlista;

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

		$model = new \Comedital\Component\Edital\Site\Model\ConstructorlistaModel();
		$this->state = $model->getState();
		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();

		foreach ((array) $this->items as $item)
		{
			$item->tamanho = $model->getTamanhoFormulario($item->id);
			$item->is_open = Factory::getDate() >= Factory::getDate($item->dt_abertura)
				&& Factory::getDate() <= Factory::getDate($item->dt_encerramento);
		}

		if (count((array) $this->get('Errors')))
		{
			throw new \Exception(implode("\n", (array) $this->get('Errors')));
		}

		$this->document->setTitle('Construtor de Formulários');
		parent::display($tpl);
	}
}
