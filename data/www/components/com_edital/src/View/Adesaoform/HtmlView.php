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

		// Links enviados aos entes que aderiram antes da plataforma trazem a localidade na URL.
		$this->prefill = AdesaoHelper::getPrefillLocalidade($app->input);

		if ($this->prefill)
		{
			$this->item->tp_esfera_governamental = $this->prefill['esfera'];
			$this->item->sg_estado               = $this->prefill['uf'];

			if (empty($this->item->no_oficial))
			{
				if ($this->prefill['nome'] !== '')
				{
					$this->item->no_oficial = $this->prefill['nome'];
				}
				elseif ($this->prefill['esfera'] === 'municipal' && !empty($this->prefill['municipios'][0]->no_municipio))
				{
					$this->item->no_oficial = $this->prefill['municipios'][0]->no_municipio;
				}
				elseif ($this->prefill['esfera'] === 'estadual' && !empty($this->prefill['no_estado']))
				{
					$this->item->no_oficial = $this->prefill['no_estado'];
				}
			}

			if ($this->prefill['email'] !== '' && empty($this->item->ds_email_responsavel))
			{
				$this->item->ds_email_responsavel = $this->prefill['email'];
			}

			if ($this->prefill['email_alt'] !== '' && empty($this->item->ds_emails_alternativos))
			{
				$this->item->ds_emails_alternativos = $this->prefill['email_alt'];
			}

			if ($this->prefill['cnpj'] !== '' && empty($this->item->ds_cnpj))
			{
				$this->item->ds_cnpj = $this->prefill['cnpj'];
			}
		}
		elseif ($app->input->get('esfera', '', 'alnum') !== '')
		{
			$app->enqueueMessage(
				Text::_('Não foi possível identificar a localidade a partir do link de acesso. '
					. 'Preencha os dados manualmente ou entre em contato pelo e-mail sinapir@igualdaderacial.gov.br.'),
				'warning'
			);
		}
		
		// tratamento para coleta de informações de municípios consorciados
		$origemKey = $app->input->getString('origem_key', '');
		if(!empty($origemKey)){
			$AdesaoHelper = new AdesaoHelper();
			$idAdesaOrigem = intval($AdesaoHelper->getAdesaoIdByPubKey($origemKey));
			if($idAdesaOrigem > 0){
				$this->item->co_adesao_origem = $idAdesaOrigem;
			}
		}

		// dados da adesao sao preenchidos no prepare document qnd vem do banco
		$this->_prepareDocument();

		//edital do tipo formulario sao questionarios sobre uma adesao realizada anteriormente, deve-se encontra-la
		if($this->edital->tp_edital == 'formulario' && empty($this->item->co_adesao_origem)){
			$idEditalDeAdesao = $this->edital->co_edital_adesao ?? 0;
			$adesaoOrigem = AdesaoHelper::getAdesaoByUserEdital($user->id, $idEditalDeAdesao);
			if($adesaoOrigem->st_adesao != 'deferido' && $adesaoOrigem->tp_solicitacao != 'migracao'){
				//lanca erro
				$app->enqueueMessage(
					Text::_('A adesão anterior não foi deferida.'),
					'error'
				);
				return;
			}
		}

		if (($this->edital->tp_edital ?? '') === 'formulario' && !$this->previewMode)
		{
			if (empty($user->id))
			{
				$return = base64_encode(Uri::getInstance()->toString());
				$app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));

				return;
			}

			if (empty($this->avisoMinhaAdesao) && empty($this->item->co_adesao_origem))
			{
				$idEditalDeAdesao = (int) ($this->edital->co_edital_adesao ?? 0);
				$adesaoOrigem = AdesaoHelper::getAdesaoByUserEdital((int) $user->id, $idEditalDeAdesao);
				
				if (empty($adesaoOrigem) || (($adesaoOrigem->st_adesao ?? '') !== 'deferido' && ($adesaoOrigem->tp_solicitacao ?? '' )!== 'migracao'))
				{
					$this->avisoMinhaAdesao = Text::_('COM_EDITAL_ERROR_ADESAO_ORIGEM_NAO_DEFERIDA');
				}
				elseif (!EditalHelper::usuarioAtendeEsferaSolicitante($this->edital, $adesaoOrigem->tp_esfera_governamental ?? ''))
				{
					$this->avisoMinhaAdesao = Text::_('COM_EDITAL_ERROR_EDITAL_ESFERA_SOLICITANTE');
				}
				else
				{
					$this->item->co_adesao_origem = $adesaoOrigem->id;
				}
			}
		}

		if($this->item->co_adesao_origem > 0){
			$MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();
			$this->item->adesaoOrigem = $MinhaAdesaoModel->getItem($this->item->co_adesao_origem);
		}

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

		EditalHelper::addMinhaAdesaoPathway();
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

		if(!$this->previewMode && $user->authorise('minhaadesao.manage', 'com_edital')) {
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

		$perguntasAjuda = PerguntaHelper::getPerguntasByEdital($editalId, 'ajuda_adesao');
		$this->perguntasAjuda = $perguntasAjuda;
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
