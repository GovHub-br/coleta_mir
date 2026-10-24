<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Minhaadesao;
// No direct access
defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;

/**
 * View class for a list of Edital.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $state;

	protected $item;

	protected $docsComplementacao = [];

	protected $form;

	protected $params;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  Template name
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function display($tpl = null)
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		$this->params = $app->getParams('com_edital');


		$idAdesao = $app->input->getInt('id', 0);

		if (!empty($this->item)) {
			$this->form = $this->get('Form');
		}

		// Check permission for adesao section
		if (!$user->authorise('minhaadesao.manage', 'com_edital')) {
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();
		$this->items = $MinhaAdesaoModel->getAdesoesByUserId($user->id);
		//filtrar apenas adesões com tp_edital = 'adesao'
		$this->items = array_filter($this->items, function($item) {
			return isset($item->tp_edital) && $item->tp_edital === 'adesao';
		});

		if ($idAdesao > 0) {
			$this->item = $MinhaAdesaoModel->getItem($idAdesao);
		} else {
			$this->item = $MinhaAdesaoModel->getItem($this->items[0]->id);
		}

		//setar titulo do menu com nome do item
		$menus = $app->getMenu();
		$menu = $menus->getActive();
		if ($menu && !empty($this->item->no_oficial)) {
			$menu->title = $this->item->no_oficial;
		}

		$this->item->marco_deferido = $MinhaAdesaoModel->getMarcoTemporal($this->item->id, 'deferido');
		$this->item->marco_indeferido = $MinhaAdesaoModel->getMarcoTemporal($this->item->id, 'indeferido');
		$this->item->marco_recurso = $MinhaAdesaoModel->getMarcoTemporal($this->item->id, 'recurso');

		// Get respostas (documentos)
		$AdesaorespostasModel = new \Comedital\Component\Edital\Site\Model\AdesaorespostasModel();
		$this->item->respostas = $AdesaorespostasModel->getRespostasByAdesao($this->item->id);

		//getPerguntas Recurso
		$perguntas = PerguntaHelper::getPerguntasByEdital($this->item->co_edital_id, 'recurso');
		$this->perguntas_recurso = $perguntas;

		$this->temDocPendenteCorrecao = $this->verificarDocumentoPendenteCorrecao($this->item->respostas);
		$this->docsComplementacao = $this->getDocumentosEmComplementacao($this->item->respostas);

		//formularios abertos
		if($this->item->st_adesao=='deferido' || $this->item->tp_solicitacao=='migracao') {
			$this->formularios_abertos = $MinhaAdesaoModel->getFormulariosAbertos(
				$this->item->co_edital_id,
				$this->item->tp_esfera_governamental ?? ''
			);		

			foreach ($this->formularios_abertos as $formulario) {
				$resAdesaoForm = $MinhaAdesaoModel->getAdesoesByUserIdEdital($user->id, $formulario->id);
				$formulario->adesao = $resAdesaoForm[0];
			}
		}

		$this->_prepareDocument();

		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function _prepareDocument()
	{
		$app   = Factory::getApplication();
		$menus = $app->getMenu();
		$title = null;

		// Because the application sets a default page title,
		// We need to get it from the menu item itself
		$menu = $menus->getActive();

		if ($menu) {
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		} else {
			$this->params->def('page_heading', Text::_('COM_EDITAL_DEFAULT_PAGE_TITLE'));
		}

		$title = $this->item->title ?? $this->params->get('page_title', '');

		if (empty($title)) {
			$title = $app->get('sitename');
		} elseif ($app->get('sitename_pagetitles', 0) == 1) {
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		} elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);

		if ($this->params->get('menu-meta_description')) {
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords')) {
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}

	private function verificarDocumentoPendenteCorrecao($respostas)
	{
		foreach ($respostas as $resposta) {
			if ($resposta->st_situacao == 'complementacao') {
				return true;
			}
		}
		return false;
	}

	private function getDocumentosEmComplementacao($respostas)
	{
		$documentos = [];

		foreach ($respostas as $resposta) {
			if (($resposta->st_situacao ?? '') === 'complementacao') {
				$documentos[] = $resposta;
			}
		}

		return $documentos;
	}
}
