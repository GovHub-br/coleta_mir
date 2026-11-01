<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\FormulariosRespostas;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\OrigemHelper;
use Comedital\Component\Edital\Site\Model\AdesaorespostasModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * View class for Formulario Respostas.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $state;
	protected $item;
	protected $params;

	/** Links do formulário de execução, por projeto (posição 0 = execução pela própria unidade). */
	public $linksExecucao = [];

	/** Formulário seguinte da cadeia (gestor, a partir do MIR; execução, a partir do gestor). */
	public $editalExecucao;
	protected $municipios;

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

		// Verify user is logged in
		if (!$user->id) {
			$loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . $idAdesao, false)), false);
			$app->redirect($loginUrl);
			return;
		}

        
		// Get the model and load item
		$model = $this->getModel();
        $modelAdesao = new AdesaorespostasModel();
		$this->item = $modelAdesao->getItem($idAdesao);

		if (empty($this->item) || empty($this->item->id))
		{
			throw new \Exception(Text::_('COM_EDITAL_ERROR_ADESAO_NOT_FOUND'), 404);
		}

		if (!$user->authorise('respostas.manage', 'com_edital')) {
			if ((int) $user->id !== (int) $this->item->created_by)
			{
				throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
			}
		}else{
			$this->ehAdmin = true;
		}

		// Load municipios if available
		if (!empty($this->item->id)) {
			$municipioAdesaoModel = new \Comedital\Component\Edital\Site\Model\MunicipioAdesaoModel();
			$this->municipios = $municipioAdesaoModel->getMunicipiosByAdesao($idAdesao);
		}

		// Formulário com formulário de execução ligado: links por projeto (resposta já enviada).
		$editalExecucao = OrigemHelper::getEditalExecucao((int) $this->item->co_edital_id);

		if ($editalExecucao && $this->item->st_adesao !== 'rascunho') {
			$this->editalExecucao = $editalExecucao;

			foreach (OrigemHelper::getProjetos((int) $this->item->id) as $projeto => $dados) {
				$this->linksExecucao[$projeto] = $dados + [
					'url' => OrigemHelper::getLinkExecucao((int) $editalExecucao->id, (int) $this->item->id, $projeto),
				];
			}
		}

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			throw new \Exception(implode("\n", $errors));
		}

		$this->_prepareDocument();

		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 *
	 * @return void
	 */
	protected function _prepareDocument()
	{
		$app = Factory::getApplication();
		$menus = $app->getMenu('site');
		$menu = $menus->getActive();

		$title = 'Respostas de Formulário';

		if (!empty($this->item)) {
			$title = 'Respostas - ' . ($this->item->no_oficial ?? 'Formulário');
		}

		if ($app->get('sitename_pagetitles', 0) == 1) {
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		} elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}

		$this->document->setTitle($title);

		// Breadcrumb do gestor: Início > Painel de Gestão > Gestão de Formulários > <formulário> > <ente>
		if (!empty($this->ehAdmin) && !empty($this->item)) {
			$pathway = $app->getPathway();

			if (!empty($this->item->edital->no_edital)) {
				$pathway->addItem(
					$this->item->edital->no_edital,
					'index.php?option=com_edital&view=formularioadesoes&item_id=' . (int) $this->item->co_edital_id
				);
			}

			$pathway->addItem($this->item->no_oficial ?? 'Respostas');
		}
	}

	/**
	 * Convert resposta ID to web download path
	 *
	 * @param   integer $id Resposta ID
	 *
	 * @return  string  Download URL
	 */
	public function convertToWebPath($id)
	{
		return Route::_('index.php?option=com_edital&task=formulariosrespostas.downloadResposta&resposta_id=' . $id, false);
	}
}
