<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Adesaoform;
// No direct access
defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use Comedital\Component\Edital\Site\Helper\EditalHelper;
use Comedital\Component\Edital\Site\Helper\OrigemHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * View class for a list of Edital.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * O título da página (nome do formulário) é exibido pelo template, com o breadcrumb.
	 *
	 * @var bool
	 */
	public $tituloNoTemplate = false;

	protected $state;

	protected $item;

	protected $form;

	protected $params;

	protected $canSave;

	protected $previewMode = false;

	/**
	 * Localidade pré-preenchida pelos parâmetros do link de acesso (ou null).
	 *
	 * @var array|null
	 */
	protected $prefill;

	/**
	 * Formulário de execução: valores travados vindos do formulário de origem, por id de pergunta.
	 *
	 * @var array
	 */
	public $valoresOrigem = [];

	/** Quem define os valores travados: o formulário de origem é do MIR ou do gestor. */
	public $definidoPor = 'definido pelo gestor';

	/** Execução nova: chave da resposta de origem e posição do projeto (vindos do projeto em "Meus instrumentos"). */
	public $origemKey = '';

	public $projeto = -1;

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

		$this->state   = $this->get('State');
		$this->item    = $this->get('Item');
		$this->params  = $app->getParams('com_edital');
		$this->canSave = $this->get('CanSave');
		$this->form		= $this->get('Form');

		$layout = $app->input->getCmd('layout', 'default');
		$mode = $app->input->getCmd('modo', $this->params->get('modo', ''));

		$this->previewMode = $layout === 'preview' || $mode === 'preview';
		$this->user = $user;		

		if ($this->previewMode)
		{
			$this->item = (object) [
				'id' => '',
				'tp_esfera_governamental' => '',
				'ds_cnpj' => '',
				'no_oficial' => '',
				'sg_estado' => '',
				'ds_municipios' => '',
				'ds_email_responsavel' => '',
				'ds_emails_alternativos' => '',
				'ds_senha' => '',
				'ds_confirma_senha' => '',
			];
			$this->municipios = [];
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}
		
		$this->sg_estado_options = AdesaoHelper::getSgEstadoOptions();

		// dados da adesao sao preenchidos no prepare document qnd vem do banco
		$this->_prepareDocument();


		if (($this->edital->tp_edital ?? '') === 'formulario' && !$this->previewMode)
		{
			$this->prepareTituloFormulario();
		}


		parent::display($tpl);
	}

	/**
	 * Preenchimento de formulário (censo) aberto pelo item de menu "Meus Formulários":
	 * o título do template passa a ser o nome do formulário, e o breadcrumb fica
	 * Início > Minha Adesão > Meus Formulários > <formulário>.
	 *
	 * @return void
	 */
	protected function prepareTituloFormulario()
	{
		$app  = Factory::getApplication();
		$menu = $app->getMenu()->getActive();

		if (!$menu || ($menu->query['view'] ?? '') !== 'formularios' || empty($this->edital->no_edital))
		{
			return;
		}

		// Lido pelo Helix (features/title.php) ao montar o título da página
		$menu->getParams()->set('helixultimate_page_title_alt', $this->edital->no_edital);
		$this->tituloNoTemplate = true;

		$app->getPathway()->addItem($this->edital->no_edital);
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
		$user = $app->getIdentity();

		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();

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

		//get input edital_id
		$this->input = Factory::getApplication()->input;


		$editalId = (int) $this->params->get('edital_id', $this->input->getInt('edital_id'));
		$this->edital = $editalId ? EditalHelper::getEdital($editalId) : null;

		$resPermiteAdesao = EditalHelper::editalPermiteAdesao($editalId);
		if($resPermiteAdesao['success'] === false){
			throw new \Exception(Text::_('Edital não permite adesão. '. $resPermiteAdesao['message']), 403);
		}

		if (!$this->previewMode && ($this->edital->tp_edital ?? '') === 'formulario') {
			$this->prepararRespostaFormulario($user, $editalId);
		} elseif(!$this->previewMode && $user->authorise('minhaadesao.manage', 'com_edital')) {
			$MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();
			$adesoes = $MinhaAdesaoModel->getAdesoesByUserIdEdital($user->id, $editalId);


			foreach($adesoes as $adesao){
				if($adesao->co_edital == $editalId && $adesao->st_adesao == 'rascunho'){
					$this->item = $adesao;
					$this->municipios = $MinhaAdesaoModel->getMunicipiosAdesao($adesao->id);
					break;
				}
				if($adesao->co_edital == $editalId && $adesao->st_adesao != 'rascunho'){
					if (($this->edital->tp_edital ?? '') === 'formulario')
					{
						$this->avisoMinhaAdesao = Text::sprintf(
							'COM_EDITAL_FORMULARIO_JA_PREENCHIDO',
							Route::_('index.php?option=com_edital&view=formularios')
						);
						break;
					}
					$this->avisoMinhaAdesao = Text::sprintf(
						'COM_EDITAL_ADESAO_JA_ENVIADA',
						Route::_('index.php?option=com_edital&view=minhaadesao&id=' . $adesao->id)
					);
					break;
				}

			}
		}

		$perguntas = PerguntaHelper::getPerguntasByEdital($editalId, 'adesao');
		$this->perguntas = $perguntas;

		if (!$this->previewMode && OrigemHelper::ehExecucao($this->edital)) {
			$this->prepararOrigem($perguntas);
		}

		$perguntasAjuda = PerguntaHelper::getPerguntasByEdital($editalId, 'ajuda_adesao');
		$this->perguntasAjuda = $perguntasAjuda;
	}

	/**
	 * Formulários aceitam várias respostas por usuário (ex.: uma por TED/convênio), editáveis
	 * mesmo depois de enviadas: abre a resposta pedida em adesao_id, se for do usuário e deste
	 * formulário, ou uma resposta nova com os dados do responsável vindos da conta.
	 *
	 * @param   object  $user      Usuário logado
	 * @param   int     $editalId  Formulário
	 *
	 * @return void
	 */
	protected function prepararRespostaFormulario($user, int $editalId)
	{
		$adesaoId = $this->input->getInt('adesao_id', 0);

		if ($adesaoId > 0 && !$user->guest) {
			$MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();

			foreach ($MinhaAdesaoModel->getAdesoesByUserIdEdital($user->id, $editalId) as $adesao) {
				if ((int) $adesao->id === $adesaoId) {
					$adesao->no_responsavel = $adesao->no_responsavel ?? $adesao->no_oficial;
					$this->item = $adesao;
					$this->municipios = $MinhaAdesaoModel->getMunicipiosAdesao($adesao->id);

					return;
				}
			}

			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		if (!$user->guest) {
			$this->item->no_responsavel = $user->name;
			$this->item->ds_email_responsavel = $user->email;
		}
	}

	/**
	 * Formulário de execução: perguntas preenchidas e travadas a partir do formulário de origem.
	 * Resposta nova: vem do link do projeto (origem_key e projeto); resposta salva: mantém os
	 * valores gravados.
	 *
	 * @param   array  $perguntas  Perguntas do formulário
	 *
	 * @return void
	 */
	protected function prepararOrigem(array $perguntas)
	{
		$editalOrigem = EditalHelper::getEdital((int) $this->edital->co_edital_adesao);
		$this->definidoPor = $editalOrigem && OrigemHelper::ehExecucao($editalOrigem) ? 'definido pelo gestor' : 'definido pelo MIR';

		if (!empty($this->item->id)) {
			$respostas = (new \Comedital\Component\Edital\Site\Model\RespostaModel())->getRespostasMapByAdesao((int) $this->item->id);
			$this->valoresOrigem = OrigemHelper::getValoresSalvos($perguntas, $respostas);

			return;
		}

		$this->origemKey = $this->input->getString('origem_key', '');
		$this->projeto = $this->input->getInt('projeto', -1);
		$origem = $this->origemKey !== '' ? OrigemHelper::resolver($this->edital, $this->origemKey, $this->projeto) : null;

		if ($origem === null || $origem['erro'] !== null) {
			$this->avisoMinhaAdesao = $origem['erro']
				?? 'A execução é preenchida a partir do projeto, em "Meus instrumentos": pelo responsável cadastrado (CPF) na lista de projetos do gestor ou pelo próprio gestor.';

			return;
		}

		$this->item->co_adesao_origem = (int) $origem['adesao']->id;
		$this->valoresOrigem = $origem['valores'];
	}

	public function isPreviewMode(): bool
	{
		return $this->previewMode;
	}

	public function getChaveByTitulo($titulo){
		$chave = str_replace(' ', '-', mb_strtolower($titulo));
		//remove acentos e caracteres especiais
		$chave = preg_replace('/[áàãâä]/u', 'a', $chave);
		$chave = preg_replace('/[éèêë]/u', 'e', $chave);
		$chave = preg_replace('/[íìîï]/u', 'i', $chave);
		$chave = preg_replace('/[óòõôö]/u', 'o', $chave);
		$chave = preg_replace('/[úùûü]/u', 'u', $chave);
		$chave = preg_replace('/[ç]/u', 'c', $chave);
		$chave = preg_replace('/[^a-z0-9\-]/', '', $chave);
		return $chave;
	}

}
