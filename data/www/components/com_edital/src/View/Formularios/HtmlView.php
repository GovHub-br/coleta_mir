<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\View\Formularios;

defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;
use \Comedital\Component\Edital\Site\Helper\DwHelper;
use \Comedital\Component\Edital\Site\Helper\PerguntaHelper;

/**
 * View class for a list of Formulario records.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $items;

	protected $pagination;

	protected $state;

	protected $params;

	protected $available;

	protected $closed;

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

		// if (empty($user->id))
		// {
		// 	$loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode(Route::_('index.php?option=com_edital&view=formularios', false)), false);
		// 	$app->redirect($loginUrl);

		// 	return;
		// }

		$this->state = $this->get('State');
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->params = $app->getParams('com_edital');
		
		// Group items into available and closed
		$this->available = array();
		$this->closed = array();
		
		$model = $this->getModel();
        $MinhaAdesaoModel = new \Comedital\Component\Edital\Site\Model\MinhaadesaoModel();
		
		if ($this->items)
		{
			foreach ($this->items as $item)
			{

				$item->tamanho = $model->getTamanhoFormulario($item->id);

				// Um formulário aceita várias respostas do usuário (ex.: uma por TED/convênio),
				// todas editáveis enquanto o formulário estiver no prazo.
				$item->respostas = $user->guest ? [] : $MinhaAdesaoModel->getAdesoesByUserIdEdital((int) $user->id, $item->id);
				usort($item->respostas, static fn ($a, $b) => (int) $b->id <=> (int) $a->id);
				$perguntaIdentificacao = $this->getPerguntaIdentificacao($item->id);

				foreach ($item->respostas as $resposta)
				{
					$resposta->progresso = $MinhaAdesaoModel->getProgressoPreenchimentoAdesao($resposta->id, $item->id);
					$resposta->rotulo = $this->getRotuloResposta($perguntaIdentificacao, (int) $resposta->id);
				}

				if ($model->isAvailable($item))
				{
					$this->available[] = $item;
				}
				else
				{
					$this->closed[] = $item;
				}
			}
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}



		parent::display($tpl);
	}

	/**
	 * Pergunta que identifica cada resposta na listagem: a que escolhe o instrumento
	 * (chave do DW ou resposta única), se houver.
	 *
	 * @param   int  $editalId  Formulário
	 *
	 * @return  object|null
	 */
	protected function getPerguntaIdentificacao(int $editalId)
	{
		foreach (PerguntaHelper::getPerguntasByEdital($editalId, 'adesao') ?: [] as $pergunta)
		{
			if (PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_CHAVE) !== ''
				|| PerguntaHelper::getParametro($pergunta, PerguntaHelper::PARAM_RESPOSTA_UNICA) === 'sim'
				|| PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_FONTE) !== '')
			{
				return $pergunta;
			}
		}

		return null;
	}

	/**
	 * Rótulo da resposta: a alternativa escolhida na pergunta de identificação
	 * (ex.: "931295 - Universidade Federal da Bahia - ..."), ou vazio.
	 *
	 * @param   object|null  $pergunta  Pergunta de identificação
	 * @param   int          $adesaoId  Resposta
	 *
	 * @return  string
	 */
	protected function getRotuloResposta($pergunta, int $adesaoId): string
	{
		if (!$pergunta)
		{
			return '';
		}

		$resposta = PerguntaHelper::getRespostaByAdesaoPergunta($adesaoId, $pergunta->id);
		$valor = is_object($resposta) ? trim((string) $resposta->ds_resposta) : '';

		if ($valor === '')
		{
			return '';
		}

		foreach (PerguntaHelper::getOpcoesSelecao($pergunta) as $opcao)
		{
			if ((string) $opcao['valor'] === $valor)
			{
				return $opcao['nome'];
			}
		}

		return $valor;
	}
}
