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
 * formulários, agrupadas pelo TED/convênio escolhido em cada uma, os projetos cujo responsável
 * é o CPF do usuário (com o link da execução) e o botão "Novo instrumento" com os formulários
 * do perfil do usuário (PerfilHelper). A execução não entra no botão: começa pelo projeto.
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

		$this->prepararListagem($editais, $user);

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
			if ($edital->aberto && (int) $edital->co_edital_adesao <= 0 && PerfilHelper::podeIniciar($user, $edital))
			{
				$this->novos[] = (object) [
					'titulo' => $edital->no_edital,
					'url'    => Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . (int) $edital->id . $itemid),
				];
			}

			if ($user->guest)
			{
				continue;
			}

			$editalExecucao = OrigemHelper::getEditalExecucao((int) $edital->id);

			// Preenchimentos vinculados ao CPF do usuário por outra pessoa: projetos cadastrados pelo
			// gestor (execução) e gestores cadastrados pelo MIR (formulário do gestor).
			if ($editalExecucao && isset($editais[(int) $editalExecucao->id]) && $editais[(int) $editalExecucao->id]->aberto)
			{
				foreach (OrigemHelper::getProjetosDoCpf(OrigemHelper::cpfDoUsuario($user), (int) $edital->id) as $encontrado)
				{
					if ((int) $encontrado['adesao']->created_by === (int) $user->id)
					{
						continue;
					}

					$instrumento = InstrumentoHelper::getDaResposta((int) $encontrado['adesao']->id, (int) $edital->id);

					if ($instrumento === null)
					{
						continue;
					}

					$this->grupoInstrumento($instrumento)->projetos[] = (object) [
						'rotulo'     => $encontrado['dados']['projeto'] ? $encontrado['dados']['rotulo'] : '',
						'gestor'     => Factory::getUser((int) $encontrado['adesao']->created_by)->name,
						'formulario' => $editalExecucao->no_edital,
						'url'        => OrigemHelper::getLinkExecucao((int) $editalExecucao->id, (int) $encontrado['adesao']->id, $encontrado['projeto']),
					];
				}
			}

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

				$this->grupoInstrumento($instrumento)->respostas[] = $resposta;
			}
		}

		// Instrumentos por tipo e código; dentro de cada um, a resposta mais recente primeiro.
		uasort($this->instrumentos, static fn ($a, $b) => [$a->rotuloTipo, $a->codigo] <=> [$b->rotuloTipo, $b->codigo]);

		foreach (array_merge($this->instrumentos, $this->outrasRespostas) as $grupo)
		{
			usort($grupo->respostas, static fn ($a, $b) => $b->id <=> $a->id);
		}
	}

	/** Card do instrumento na listagem (criado na primeira vez). */
	protected function grupoInstrumento(array $instrumento): object
	{
		if (!isset($this->instrumentos[$instrumento['chave']]))
		{
			$this->instrumentos[$instrumento['chave']] = (object) ($instrumento + [
				'descricao' => InstrumentoHelper::getDescricao($instrumento['chave']),
				'respostas' => [],
				'projetos'  => [],
			]);
		}

		return $this->instrumentos[$instrumento['chave']];
	}

	/** Dados de exibição de uma resposta: progresso, links de edição/visualização e de execução por projeto. */
	protected function montarResposta($adesao, $edital, $editalExecucao, MinhaadesaoModel $minhaAdesao, string $itemid): object
	{
		$progresso = $minhaAdesao->getProgressoPreenchimentoAdesao($adesao->id, $edital->id);
		$total = (int) ($progresso->total ?? 0);
		$respondidos = (int) ($progresso->respondidos ?? 0);
		$enviado = $adesao->st_adesao !== 'rascunho';
		$execucoes = [];
		$rotuloExecucoes = $editalExecucao ? $editalExecucao->no_edital : '';

		if ($editalExecucao && $enviado)
		{
			foreach (OrigemHelper::getProjetos((int) $adesao->id) as $projeto => $dados)
			{
				$execucoes[] = (object) [
					'rotulo'      => $dados['rotulo'],
					'responsavel' => $dados['responsavel'],
					'url'         => OrigemHelper::getLinkExecucao((int) $editalExecucao->id, (int) $adesao->id, $projeto),
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
			'rotuloExecucoes' => $rotuloExecucoes,
		];
	}
}
