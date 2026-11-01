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
use \Joomla\CMS\Router\Route;
use \Comedital\Component\Edital\Site\Helper\EditalHelper;
use \Comedital\Component\Edital\Site\Helper\InstrumentoHelper;
use \Comedital\Component\Edital\Site\Helper\OrigemHelper;
use \Comedital\Component\Edital\Site\Helper\PerfilHelper;
use \Comedital\Component\Edital\Site\Model\MinhaadesaoModel;

/**
 * Listagem do preenchimento, focada nos instrumentos: as respostas do usuário a todos os
 * formulários, agrupadas pelo TED/convênio escolhido em cada uma, e o botão "Novo instrumento"
 * com os formulários do perfil do usuário (PerfilHelper).
 *
 * Layout "execucao": o executor escolhe o instrumento e o projeto cadastrado pelo gestor para
 * abrir o formulário de execução ligado à resposta do gestor (OrigemHelper).
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	protected $items;

	protected $pagination;

	protected $state;

	protected $params;

	/** Instrumentos com respostas do usuário: chave => objeto com tipo, código, descrição e respostas. */
	protected $instrumentos = [];

	/** Respostas sem instrumento (formulário sem a pergunta ou ainda não respondida), por formulário. */
	protected $outrasRespostas = [];

	/** Formulários do botão "Novo instrumento": objetos com titulo e url. */
	protected $novos = [];

	/** Layout "execucao": formulário de execução, instrumentos disponíveis e projetos do escolhido. */
	protected $execucao;

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
		$this->items = $this->get('Items') ?: [];
		$this->pagination = $this->get('Pagination');
		$this->params = $app->getParams('com_edital');

		$model = $this->getModel();
		$editais = [];

		foreach ($this->items as $item)
		{
			$item->aberto = $model->isAvailable($item);
			$editais[(int) $item->id] = $item;
		}

		if ($this->getLayout() === 'execucao')
		{
			$this->prepararExecucao($editais, $app->input->getInt('edital_id'), trim($app->input->getString('instrumento', '')));
		}
		else
		{
			$this->prepararListagem($editais, $user);
		}

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		parent::display($tpl);
	}

	/** Respostas do usuário agrupadas por instrumento e formulários do botão "Novo instrumento". */
	protected function prepararListagem(array $editais, $user)
	{
		$itemid = EditalHelper::getItemidMeusFormularios();
		$itemid = $itemid ? '&Itemid=' . $itemid : '';
		$minhaAdesao = new MinhaadesaoModel();

		foreach ($editais as $edital)
		{
			if ($edital->aberto && PerfilHelper::podeIniciar($user, $edital))
			{
				$this->novos[] = (object) [
					'titulo' => $edital->no_edital,
					'url'    => (int) $edital->co_edital_adesao > 0
						? Route::_('index.php?option=com_edital&view=formularios&layout=execucao&edital_id=' . (int) $edital->id . $itemid)
						: Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . (int) $edital->id . $itemid),
				];
			}

			if ($user->guest)
			{
				continue;
			}

			$editalExecucao = OrigemHelper::getEditalExecucao((int) $edital->id);

			foreach ($minhaAdesao->getAdesoesByUserIdEdital((int) $user->id, (int) $edital->id) as $adesao)
			{
				$resposta = $this->montarResposta($adesao, $edital, $editalExecucao, $minhaAdesao, $itemid);
				$instrumento = InstrumentoHelper::getDaResposta((int) $adesao->id, (int) $edital->id);

				if ($instrumento === null)
				{
					$this->outrasRespostas[(int) $edital->id] ??= (object) ['formulario' => $edital->no_edital, 'respostas' => []];
					$this->outrasRespostas[(int) $edital->id]->respostas[] = $resposta;
					continue;
				}

				if (!isset($this->instrumentos[$instrumento['chave']]))
				{
					$this->instrumentos[$instrumento['chave']] = (object) ($instrumento + [
						'descricao' => InstrumentoHelper::getDescricao($instrumento['chave']),
						'respostas' => [],
					]);
				}

				$this->instrumentos[$instrumento['chave']]->respostas[] = $resposta;
			}
		}

		// Instrumentos por tipo e código; dentro de cada um, a resposta mais recente primeiro.
		uasort($this->instrumentos, static fn ($a, $b) => [$a->rotuloTipo, $a->codigo] <=> [$b->rotuloTipo, $b->codigo]);

		foreach (array_merge($this->instrumentos, $this->outrasRespostas) as $grupo)
		{
			usort($grupo->respostas, static fn ($a, $b) => $b->id <=> $a->id);
		}
	}

	/** Dados de exibição de uma resposta: progresso, links de edição/visualização e de execução por projeto. */
	protected function montarResposta($adesao, $edital, $editalExecucao, MinhaadesaoModel $minhaAdesao, string $itemid): object
	{
		$progresso = $minhaAdesao->getProgressoPreenchimentoAdesao($adesao->id, $edital->id);
		$total = (int) ($progresso->total ?? 0);
		$respondidos = (int) ($progresso->respondidos ?? 0);
		$enviado = $adesao->st_adesao !== 'rascunho';
		$execucoes = [];

		if ($editalExecucao && $enviado)
		{
			foreach (OrigemHelper::getProjetos((int) $adesao->id) as $projeto => $dados)
			{
				$execucoes[] = (object) [
					'rotulo' => $dados['rotulo'],
					'url'    => OrigemHelper::getLinkExecucao((int) $editalExecucao->id, (int) $adesao->id, $projeto),
				];
			}
		}

		return (object) [
			'id'          => (int) $adesao->id,
			'formulario'  => $edital->no_edital,
			'projeto'     => (int) $edital->co_edital_adesao > 0 ? OrigemHelper::getRotuloProjeto((int) $adesao->id, (int) $edital->id) : '',
			'enviado'     => $enviado,
			'total'       => $total,
			'respondidos' => $respondidos,
			'pct'         => $total > 0 ? (int) round($respondidos / $total * 100) : 0,
			'dt_criacao'  => $adesao->dt_criacao,
			'urlEditar'   => $edital->aberto
				? Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . (int) $edital->id . '&adesao_id=' . (int) $adesao->id . $itemid)
				: '',
			'urlVer'      => $enviado ? Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . (int) $adesao->id) : '',
			'execucoes'   => $execucoes,
		];
	}

	/**
	 * Escolha do executor: instrumentos com resposta enviada no formulário de origem (gestor) e,
	 * para o instrumento escolhido, os projetos de cada resposta com o link da execução.
	 */
	protected function prepararExecucao(array $editais, int $editalId, string $codigo)
	{
		$user = Factory::getApplication()->getIdentity();
		$edital = $editais[$editalId] ?? null;

		if (!$edital || !$edital->aberto || (int) $edital->co_edital_adesao <= 0 || !PerfilHelper::podeIniciar($user, $edital))
		{
			throw new \Exception('Formulário de execução indisponível para o seu perfil.', 403);
		}

		$origemId = (int) $edital->co_edital_adesao;
		$this->execucao = (object) [
			'edital'       => $edital,
			'urlVoltar'    => Route::_('index.php?option=com_edital&view=formularios'),
			'instrumentos' => [],
			'escolhido'    => null,
			'projetos'     => [],
		];

		$db = Factory::getContainer()->get('DatabaseDriver');
		$respostasOrigem = $db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName(['id']))
				->from($db->quoteName('#__tb_adesao'))
				->where($db->quoteName('co_edital') . ' = ' . $origemId)
				->where($db->quoteName('state') . ' = 1')
				->where($db->quoteName('st_adesao') . ' != ' . $db->quote('rascunho'))
				->order($db->quoteName('id'))
		)->loadColumn();

		foreach ($respostasOrigem as $adesaoId)
		{
			$instrumento = InstrumentoHelper::getDaResposta((int) $adesaoId, $origemId);

			if ($instrumento === null)
			{
				continue;
			}

			if (!isset($this->execucao->instrumentos[$instrumento['codigo']]))
			{
				$this->execucao->instrumentos[$instrumento['codigo']] = (object) ($instrumento + [
					'descricao' => InstrumentoHelper::getDescricao($instrumento['chave']),
					'origens'   => [],
				]);
			}

			$this->execucao->instrumentos[$instrumento['codigo']]->origens[] = (int) $adesaoId;
		}

		ksort($this->execucao->instrumentos, SORT_NATURAL);

		if ($codigo === '' || !isset($this->execucao->instrumentos[$codigo]))
		{
			return;
		}

		$this->execucao->escolhido = $this->execucao->instrumentos[$codigo];

		foreach ($this->execucao->escolhido->origens as $adesaoId)
		{
			foreach (OrigemHelper::getProjetos($adesaoId) as $projeto => $dados)
			{
				$this->execucao->projetos[] = (object) [
					'rotulo'      => $dados['rotulo'],
					'responsavel' => $dados['responsavel'],
					'url'         => OrigemHelper::getLinkExecucao((int) $edital->id, $adesaoId, $projeto),
				];
			}
		}
	}
}
