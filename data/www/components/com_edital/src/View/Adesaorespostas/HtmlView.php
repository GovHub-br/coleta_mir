<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Adesaorespostas;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Model\MinhaadesaoModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Scheduler\Administrator\Extension\SchedulerComponent;
use Joomla\Component\Scheduler\Administrator\Model\TasksModel;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;

/**
 * View class for Adesao Respostas.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $state;
	protected $item;
	protected $params;
	protected $listMenuId;
	protected $gestaoMenuId;

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
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		$this->state = $this->get('State');
		$this->params = $app->getParams();
		$idAdesao = $app->input->getInt('id', 0);

		//verifica se usuario esta logado se nao manda pra tela de login pasando a url de retorno para a tela de login
        $user = $app->getIdentity();
        if (!$user->id) {
            $loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Route::_('index.php?option=com_edital&view=adesaorespostas&id=' . $idAdesao, false)), false);
            $app->redirect($loginUrl);
            return;
        }

		// Check for admin permission
		if (!$user->authorise('respostas.manage', 'com_edital')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
        }

		// Capture the list menu Itemid
		$this->listMenuId = $this->_getListMenuId();
		$this->gestaoMenuId = $this->_getGestaoMenuId();
		

		// Get layout to determine which data to load
		$layout = $app->input->get('layout', 'default');
		$model = $this->getModel();
		
		if ($idAdesao == 0) {
			// Load all adesoes for listing
			$this->items = $model->getAdesoesList();
			
			
			foreach ($this->items as $k => $item) {			
				$this->items[$k] = $model->getAdesao($item->id);
			}
		} elseif ($layout === 'recurso') {
			// Load recurso view data
			$MinhaAdesaoModel = new MinhaadesaoModel();
			$this->marco_indeferido = $MinhaAdesaoModel->getMarcoTemporal($idAdesao, 'indeferido')->dt_marco;
			$this->item = $model->getItem($idAdesao, 'recurso');
			$this->perguntas = PerguntaHelper::getPerguntasByEdital($this->item->co_edital_id, 'avaliacao_recurso');
		} else {
			// Load specific adesao for viewing
			// $this->item = $this->get('Item');
			$this->item = $model->getItem($idAdesao); // Ensure item is loaded in model
			$municipioAdesaoModel = new \Comedital\Component\Edital\Site\Model\MunicipioAdesaoModel();
			$this->municipios = $municipioAdesaoModel->getMunicipiosByAdesao($idAdesao);
			
		}

        $this->idUsuarioRejeicaoAutomatica = $this->getUserTaskRejeicaoAutomatica();
        
		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->_prepareDocument();
		$this->_prepareBreadcrumb();

		parent::display($tpl);
	}

	/**
	 * Get the list menu Itemid
	 *
	 * @return int|null
	 *
	 * @since  1.0.0
	 */
	protected function _getListMenuId()
	{
		$app = Factory::getApplication();
		$menus = $app->getMenu('site');
		
		$menuItems = $menus->getItems('component', 'com_edital');
		foreach ($menuItems as $item) {
			if (isset($item->query['view']) && $item->query['view'] === 'adesaorespostas'  
				// && isset($item->query['layout']) && $item->query['layout'] === 'list'
				) {
				return $item->id;
			}
		}
		
		return null;
	}

	/**
	 * Get the adesaogestao menu Itemid
	 *
	 * @return int|null
	 *
	 * @since  1.0.0
	 */
	protected function _getGestaoMenuId()
	{
		$app = Factory::getApplication();
		$menus = $app->getMenu('site');
		
		$menuItems = $menus->getItems('component', 'com_edital');
		foreach ($menuItems as $item) {
			if (isset($item->query['view']) && $item->query['view'] === 'adesaogestao') {
				return $item->id;
			}
		}
		
		return null;
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
		$app = Factory::getApplication();
		$menus = $app->getMenu('site');
		
		// Get layout to set appropriate title
		$layout = $app->input->get('layout', 'default');
		$title = '';

		// Get the active menu item or try to find the list menu item for inheritance
		$menu = $menus->getActive();
		
		// If no active menu and we're in default layout, try to inherit from list menu item
		if (!$menu && $layout === 'default' && $this->listMenuId) {
			$menu = $menus->getItem($this->listMenuId);
		}

		// Set dynamic title based on context
		if ($layout === 'default' && !empty($this->item)) {
			// Item view - use adesao specific info
			$protocolo = AdesaoHelper::getProtocoloAdesao($this->item->id);
			$nomeEnte = $this->item->no_oficial ?? 'Adesão';
			$title = 'Adesão ' . $protocolo . ' - ' . $nomeEnte;
			$this->params->set('page_heading', $title);
			
			// Set menu title dynamically if menu exists
			if ($menu) {
				$menu->title = $title;
			}
		} elseif ($layout === 'list') {
			// List view
			$title = $menu ? $menu->title : 'Lista de Adesões';
			$this->params->def('page_heading', $title);
		} else {
			// Fallback
			$title = $menu ? $menu->title : 'Gestão de Adesões';
			$this->params->def('page_heading', $title);
		}

		// Apply site name format if configured
		if ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);

		// Set meta description for item view
		if ($layout === 'default' && !empty($this->item)) {
			$protocolo = AdesaoHelper::getProtocoloAdesao($this->item->id);
			$metaDesc = 'Visualização das respostas e documentos da adesão ' . $protocolo . ' - ' . ($this->item->no_oficial ?? '');
			$this->document->setDescription($metaDesc);
		}

		// Set meta data from menu parameters if available
		if ($menu) {
			$menuParams = $menu->getParams();
			
			if ($menuParams->get('menu-meta_description'))
			{
				$this->document->setDescription($menuParams->get('menu-meta_description'));
			}

			if ($menuParams->get('menu-meta_keywords'))
			{
				$this->document->setMetadata('keywords', $menuParams->get('menu-meta_keywords'));
			}

			if ($menuParams->get('robots'))
			{
				$this->document->setMetadata('robots', $menuParams->get('robots'));
			}
		} else {
			// Fallback to component parameters
			if ($this->params->get('menu-meta_description'))
			{
				$this->document->setDescription($this->params->get('menu-meta_description'));
			}

			if ($this->params->get('menu-meta_keywords'))
			{
				$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
			}

			if ($this->params->get('robots'))
			{
				$this->document->setMetadata('robots', $this->params->get('robots'));
			}
		}
	}

	/**
	 * Prepares the breadcrumb (pathway)
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	protected function _prepareBreadcrumb()
	{
		$app = Factory::getApplication();
		$pathway = $app->getPathway();
		$layout = $app->input->get('layout', 'default');
		
		// Add breadcrumb for item view
		if ($layout === 'default' && !empty($this->item)) {
			// Add parent menu if gestao exists
			// if ($this->gestaoMenuId) {
			// 	$menus = $app->getMenu('site');
			// 	$gestaoMenu = $menus->getItem($this->gestaoMenuId);
			// 	if ($gestaoMenu && !$app->getMenu()->getActive()) {
			// 		$pathway->addItem($gestaoMenu->title, Route::_($this->getGestaoUrl()));
			// 	}
			// }
			
			// Add current adesao
			$protocolo = AdesaoHelper::getProtocoloAdesao($this->item->id);
			$nomeEnte = $this->item->no_oficial ?? 'Adesão';
			$breadcrumbTitle = 'Adesão ' . $protocolo . ' - ' . $nomeEnte;
			
			$pathwayItems = $pathway->getPathway();
 			$lastIndex = count($pathwayItems) - 1;
 			if ($lastIndex >= 0) {
 				$pathway->setItemName($lastIndex, $breadcrumbTitle);
 			} else {
 				$pathway->addItem($breadcrumbTitle);
 			}
		}
	}

	/**
	 * Get the list URL with proper Itemid
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	public function getListUrl()
	{
		$url = 'index.php?option=com_edital&view=adesaorespostas&layout=list';
		
		if ($this->listMenuId) {
			$url .= '&Itemid=' . $this->listMenuId;
		}
		
		return Route::_($url);
	}

	/**
	 * Get the gestao URL with proper Itemid
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	public function getGestaoUrl()
	{
		$url = 'index.php?option=com_edital&view=adesaogestao';
		
		if ($this->gestaoMenuId) {
			$url .= '&Itemid=' . $this->gestaoMenuId;
		}
		
		return Route::_($url);
	}

	/**
	 * Get the list menu Itemid
	 *
	 * @return int|null
	 *
	 * @since  1.0.0
	 */
	public function getListMenuId()
	{
		return $this->listMenuId;
	}

	public function convertToWebPath($idResposta)
	{
		$webPath = Route::_('index.php?option=com_edital&task=adesaorespostas.downloadResposta&resposta_id=' . $idResposta, false);
		return $webPath;
	}


    private function getUserTaskRejeicaoAutomatica(): ?int
    {
        $app = Factory::getApplication();
        $componentScheduler = $app->bootComponent('com_scheduler');
		$tasksModel = $componentScheduler->getMVCFactory()->createModel('Tasks', 'Administrator', ['ignore_request' => true]);

        // Traz também a coluna params no select (por padrão ela não vem)
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $tasksModel->setState('list.select', [
            $db->quoteName('a.id'),
            $db->quoteName('a.title'),
            $db->quoteName('a.type'),
            $db->quoteName('a.state'),
            $db->quoteName('a.params'),
            $db->quoteName('a.next_execution'),
            $db->quoteName('a.last_execution'),
        ]);

                // Filtro pela rotina da sua task
        $tasksModel->setState('filter.type', 'editalautorreject.reject_overdue_complementacao');

        // Opcional: limitar a 1 registro
        $tasksModel->setState('list.limit', 1);
        $tasksModel->setState('list.start', 0);

        $items = $tasksModel->getItems();
        $task  = $items[0] ?? null;

        if ($task && isset($task->params)) {
            $params = json_decode($task->params, true);
			return (int) ($params['updated_by'] ?? $params['user_id'] ?? 0) ?: null;
        }
        return null;
    }
}
