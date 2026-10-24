<?php

namespace Comedital\Component\Edital\Site\View\Construtorperguntas;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\CadastroHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Model\ConstrutorperguntasModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class HtmlView extends BaseHtmlView
{
	protected $edital;

	/**
	 * O título da página é exibido pelo template, com o breadcrumb.
	 *
	 * @var bool
	 */
	public $tituloNoTemplate = false;

	public function display($tpl = null)
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		CadastroHelper::assertManage();

		if (!$user->authorise('respostas.manage', 'com_edital'))
		{
			throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var ConstrutorperguntasModel $model */
		$model    = $this->getModel();
		$editalId = $app->input->getInt('id', 0);
		$edital   = $editalId ? $model->getEdital($editalId) : null;

		if (!$edital)
		{
			throw new \Exception('Formulário não encontrado.', 404);
		}

		$this->edital = $edital;

		$wa = $this->document->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_edital');
		$wa->useStyle('com_edital.construtorperguntas')->useScript('com_edital.construtorperguntas');

		$this->document->addScriptOptions('com_edital.construtor', [
			'editalId'  => (int) $edital->id,
			'urlSalvar' => Route::_('index.php?option=com_edital&task=construtorperguntas.salvar&format=json', false),
			'urlPublicar' => Route::_('index.php?option=com_edital&task=construtorperguntas.publicar&format=json', false),
			'state'     => (int) $edital->state,
			'aberto'    => Factory::getDate() >= Factory::getDate($edital->dt_abertura)
				&& Factory::getDate() <= Factory::getDate($edital->dt_encerramento),
			'token'     => Session::getFormToken(),
			'tipos'     => ConstrutorperguntasModel::TIPOS,
			'selecao'   => ConstrutorperguntasModel::TIPOS_SELECAO,
			'comOutro'  => ConstrutorperguntasModel::TIPOS_COM_OUTRO,
			'outroValor' => PerguntaHelper::OUTRO_VALOR,
			'secoes'    => $model->getEstrutura((int) $edital->id),
		]);

		$this->document->setTitle('Seções e perguntas - ' . $edital->no_edital);
		$this->prepareTitulo();
		parent::display($tpl);
	}

	/**
	 * Aberta pelo item de menu "Construtor de Formulários": o título do template passa a ser
	 * "Seções e perguntas", e o breadcrumb fica
	 * Início > Painel de Gestão > Construtor de Formulários > Seções e perguntas.
	 *
	 * @return void
	 */
	protected function prepareTitulo()
	{
		$app  = Factory::getApplication();
		$menu = $app->getMenu()->getActive();

		if (!$menu || ($menu->query['view'] ?? '') !== 'construtorlista')
		{
			return;
		}

		// Lido pelo Helix (features/title.php) ao montar o título da página
		$menu->getParams()->set('helixultimate_page_title_alt', 'Seções e perguntas');
		$this->tituloNoTemplate = true;

		$app->getPathway()->addItem('Seções e perguntas');
	}
}
