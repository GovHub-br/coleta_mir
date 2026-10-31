<?php

namespace Comedital\Component\Edital\Site\Model;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Administrator\Table\AgrupamentoTable;
use Comedital\Component\Edital\Administrator\Table\PerguntaTable;
use Comedital\Component\Edital\Site\Helper\DwHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Lê e grava seções (tb_agrupamento) e perguntas (tb_pergunta) de um edital do tipo formulário.
 */
class ConstrutorperguntasModel extends BaseDatabaseModel
{
	/** Rótulo exibido na tela => valor gravado em tp_pergunta. */
	public const TIPOS = [
		'linha'        => 'Texto curto',
		'texto'        => 'Texto longo',
		'radio'        => 'Seleção única',
		'checkbox'     => 'Seleção múltipla',
		'lista'        => 'Lista suspensa',
		'numero'       => 'Número',
		'telefone'     => 'Telefone',
		'email'        => 'E-mail',
		'cnpj'         => 'CNPJ',
		'cep'          => 'CEP',
		'uf'           => 'UF',
		'municipio'    => 'Município',
		'arquivo'      => 'Arquivo',
		'informativo'  => 'Informativo',
	];

	public const TIPOS_SELECAO = ['radio', 'checkbox', 'lista'];

	public const TIPOS_COM_OUTRO = ['radio', 'checkbox'];

	private const ETAPA_PADRAO = 'adesao';

	public function getEdital(int $editalId): ?object
	{
		$db    = $this->db();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'no_edital', 'tp_edital', 'state', 'dt_abertura', 'dt_encerramento']))
			->from($db->quoteName('#__tb_edital'))
			->where($db->quoteName('id') . ' = ' . (int) $editalId)
			->where($db->quoteName('tp_edital') . ' = ' . $db->quote('formulario'))
			->where($db->quoteName('state') . ' <> -2');

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Publica (1) ou volta para rascunho (0).
	 *
	 * @throws \InvalidArgumentException  Formulário não encontrado ou estado inválido.
	 */
	public function alterarPublicacao(int $editalId, int $state): int
	{
		if (!in_array($state, [0, 1], true) || !$this->getEdital($editalId))
		{
			throw new \InvalidArgumentException('Formulário não encontrado.');
		}

		$db    = $this->db();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__tb_edital'))
			->set($db->quoteName('state') . ' = ' . $state)
			->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getApplication()->getIdentity()->id)
			->where($db->quoteName('id') . ' = ' . $editalId);

		$db->setQuery($query)->execute();

		return $state;
	}

	/**
	 * Estrutura no formato consumido pelo JavaScript.
	 */
	public function getEstrutura(int $editalId): array
	{
		$secoes    = $this->carregarSecoes($editalId);
		$perguntas = $this->carregarPerguntas($editalId, array_keys($secoes));
		$resultado = [];

		foreach ($secoes as $secao)
		{
			$resultado[(int) $secao->id] = [
				'id'         => (int) $secao->id,
				'titulo'     => (string) $secao->no_agrupamento,
				'descricao'  => (string) $secao->ds_agrupamento,
				'perguntas'  => [],
			];
		}

		foreach ($perguntas as $pergunta)
		{
			$tipo = (string) $pergunta->tp_pergunta;

			$resultado[(int) $pergunta->co_agrupamento]['perguntas'][] = [
				'id'           => (int) $pergunta->id,
				'enunciado'    => (string) $pergunta->ds_titulo,
				'ajuda'        => (string) $pergunta->ds_descricao,
				'tipo'         => $tipo,
				'obrigatoria'  => $pergunta->tp_requerida === 'sim',
				'alternativas' => in_array($tipo, self::TIPOS_SELECAO, true)
					? array_column(PerguntaHelper::getOpcoesSelecao($pergunta, false), 'nome')
					: [],
				'permiteOutro' => PerguntaHelper::permiteOutro($pergunta),
				'chave'        => 'p' . (int) $pergunta->id,
				'exibir'       => $this->lerCondicao($pergunta->depende_de, $pergunta->depende_de_valor, $perguntas, (string) ($pergunta->depende_de_fonte ?? '')),
				'ocultar'      => $this->lerCondicao($pergunta->depende_de_ocultar, $pergunta->depende_de_ocultar_valor, $perguntas, (string) ($pergunta->depende_de_ocultar_fonte ?? '')),
			];
		}

		return array_values($resultado);
	}

	/**
	 * Grava a estrutura completa em uma transação e devolve a estrutura persistida.
	 *
	 * @throws \InvalidArgumentException  Payload inválido.
	 * @throws \RuntimeException          Falha ao gravar.
	 */
	public function salvarEstrutura(int $editalId, array $secoes): array
	{
		if (!$this->getEdital($editalId))
		{
			throw new \InvalidArgumentException('Formulário não encontrado.');
		}

		$secoesAtuais    = $this->carregarSecoes($editalId);
		$perguntasAtuais = $this->carregarPerguntas($editalId, array_keys($secoesAtuais));
		$dados           = $this->normalizar($secoes, $secoesAtuais, $perguntasAtuais);

		$db     = $this->db();
		$userId = (int) Factory::getApplication()->getIdentity()->id;

		$db->transactionStart();
        
		try
		{
			$secoesMantidas    = [];
			$perguntasMantidas = [];
			$mapaChaves        = [];

			foreach ($dados as $posSecao => $secao)
			{
				$secaoId = $this->gravarSecao($db, $editalId, $secao, $posSecao + 1, $userId, $secoesAtuais[$secao['id']] ?? null);
				
                $secoesMantidas[] = $secaoId;

				foreach ($secao['perguntas'] as $posPergunta => $pergunta)
				{
					$perguntaId = $this->gravarPergunta(
						$db,
						$editalId,
						$secaoId,
						$pergunta,
						$posPergunta + 1,
						$userId,
						$perguntasAtuais[$pergunta['id']] ?? null,
						$mapaChaves
					);

					// As origens das condições sempre vêm antes, então já estão neste mapa.
					$mapaChaves[$pergunta['chave']] = $perguntaId;
					$perguntasMantidas[]            = $perguntaId;
				}
			}

            

			$this->removerPerguntas($db, array_diff(array_keys($perguntasAtuais), $perguntasMantidas), $userId);

			foreach (array_diff(array_keys($secoesAtuais), $secoesMantidas) as $secaoRemovidaId)
			{
				$this->removerSecao($db, $editalId, $secoesAtuais[$secaoRemovidaId], $userId);
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e instanceof \InvalidArgumentException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
		}

		return $this->getEstrutura($editalId);
	}

	/**
	 * Valida o payload e devolve uma versão limpa.
	 */
	private function normalizar(array $secoes, array $secoesAtuais, array $perguntasAtuais): array
	{
		$filtro          = InputFilter::getInstance();
		$secoesUsadas    = [];
		$perguntasUsadas = [];
		$chavesUsadas    = [];
		$anteriores      = [];
		$resultado       = [];

		foreach (array_values($secoes) as $iSecao => $secao)
		{
			$n      = $iSecao + 1;
			$id     = (int) ($secao['id'] ?? 0);
			$titulo = trim((string) ($secao['titulo'] ?? ''));

			if ($id && (!isset($secoesAtuais[$id]) || isset($secoesUsadas[$id])))
			{
				throw new \InvalidArgumentException("Seção {$n} inválida.");
			}

			if ($titulo === '' || mb_strlen($titulo) > 255)
			{
				throw new \InvalidArgumentException("Informe o título da seção {$n} (até 255 caracteres).");
			}

			$secoesUsadas[$id] = true;
			$perguntas         = [];

			foreach (array_values((array) ($secao['perguntas'] ?? [])) as $iPergunta => $pergunta)
			{
				$m         = $iPergunta + 1;
				$pId       = (int) ($pergunta['id'] ?? 0);
				$enunciado = trim((string) ($pergunta['enunciado'] ?? ''));
				$tipo      = (string) ($pergunta['tipo'] ?? '');
				$tipoAtual = $pId && isset($perguntasAtuais[$pId]) ? (string) $perguntasAtuais[$pId]->tp_pergunta : null;

				if ($pId && (!isset($perguntasAtuais[$pId]) || isset($perguntasUsadas[$pId])))
				{
					throw new \InvalidArgumentException("Pergunta {$m} da seção {$n} inválida.");
				}

				if ($enunciado === '' || mb_strlen($enunciado) > 255)
				{
					throw new \InvalidArgumentException("Informe o enunciado da pergunta {$m} da seção {$n} (até 255 caracteres).");
				}

				// Tipos legados (ex.: telefones) continuam válidos se a pergunta já os usa.
				if (!isset(self::TIPOS[$tipo]) && $tipo !== $tipoAtual)
				{
					throw new \InvalidArgumentException("Tipo de resposta inválido na pergunta {$m} da seção {$n}.");
				}

				$alternativas = [];

				if (in_array($tipo, self::TIPOS_SELECAO, true))
				{
					foreach ((array) ($pergunta['alternativas'] ?? []) as $alternativa)
					{
						$alternativa = trim((string) $alternativa);

						if ($alternativa !== '')
						{
							$alternativas[] = $alternativa;
						}
					}

					if (count($alternativas) < 2 || count($alternativas) !== count(array_unique($alternativas)))
					{
						throw new \InvalidArgumentException("A pergunta {$m} da seção {$n} precisa de ao menos 2 alternativas distintas.");
					}

					foreach (array_merge([PerguntaHelper::OUTRO_PARAM], DwHelper::PARAMETROS) as $reservada)
					{
						if (in_array($reservada, $alternativas, true))
						{
							throw new \InvalidArgumentException("A alternativa \"" . $reservada . "\" é reservada (pergunta {$m} da seção {$n}).");
						}
					}
				}

				if ($pId)
				{
					$perguntasUsadas[$pId] = true;
				}

				$chave = $pId ? 'p' . $pId : (string) ($pergunta['chave'] ?? '');

				if (!$pId && (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $chave) || preg_match('/^p\d+$/', $chave)))
				{
					throw new \InvalidArgumentException("Pergunta {$m} da seção {$n} sem identificador válido.");
				}

				if (isset($chavesUsadas[$chave]))
				{
					throw new \InvalidArgumentException("Pergunta {$m} da seção {$n} duplicada.");
				}

				$chavesUsadas[$chave] = true;
				$atual                = $perguntasAtuais[$pId] ?? null;
				$rotulo               = "{$m} da seção {$n}";
				$permiteOutro         = !empty($pergunta['permiteOutro']) && in_array($tipo, self::TIPOS_COM_OUTRO, true);
				$exibir               = $this->validarCondicao($pergunta['exibir'] ?? [], $rotulo, $anteriores, $atual, 'depende_de', 'depende_de_valor', 255);
				$ocultar              = $this->validarCondicao($pergunta['ocultar'] ?? [], $rotulo, $anteriores, $atual, 'depende_de_ocultar', 'depende_de_ocultar_valor', null);

				// Registrada só depois da validação: uma pergunta não pode depender de si mesma.
				$anteriores[$chave] = ['tipo' => $tipo, 'alternativas' => $alternativas, 'permiteOutro' => $permiteOutro];

				$perguntas[] = [
					'chave'        => $chave,
					'exibir'       => $exibir,
					'ocultar'      => $ocultar,
					'id'           => $pId,
					'enunciado'    => $enunciado,
					'ajuda'        => $filtro->clean(trim((string) ($pergunta['ajuda'] ?? '')), 'html'),
					'tipo'         => $tipo,
					'obrigatoria'  => !empty($pergunta['obrigatoria']),
					'alternativas' => $alternativas,
					'permiteOutro' => $permiteOutro,
				];
			}

			$resultado[] = [
				'id'         => $id,
				'titulo'     => $titulo,
				'descricao'  => $filtro->clean(trim((string) ($secao['descricao'] ?? '')), 'html'),
				'perguntas'  => $perguntas,
			];
		}

		return $resultado;
	}

	/**
	 * Condição no formato da tela. Origem fora desta estrutura é marcada como legada e preservada.
	 */
	private function lerCondicao($origemId, $valor, array $perguntas, string $fonte = ''): array
	{
		$origemId = (int) $origemId;
		$valores  = PerguntaHelper::valoresCondicao($valor);

		if ($fonte === PerguntaHelper::FONTE_SOLICITANTE)
		{
			return [
				'origem' => null,
				'valores' => $valores,
				'legado' => true,
				'aviso'  => 'Condição pelo tipo de solicitante, definida na administração. Ela será mantida.',
			];
		}

		if ($origemId <= 0)
		{
			return ['origem' => null, 'valores' => [], 'legado' => false];
		}

		if (!isset($perguntas[$origemId]))
		{
			return ['origem' => null, 'valores' => $valores, 'legado' => true];
		}

		return ['origem' => 'p' . $origemId, 'valores' => $valores, 'legado' => false];
	}

	private function validarCondicao($cond, string $rotulo, array $anteriores, ?object $atual, string $colOrigem, string $colValor, ?int $limite): array
	{
		$cond  = is_array($cond) ? $cond : [];
		$vazia = ['origem' => null, 'valores' => [], 'manter' => false];

		if (!empty($cond['legado']))
		{
			return $atual ? ['origem' => null, 'valores' => [], 'manter' => true] : $vazia;
		}

		$origem = (string) ($cond['origem'] ?? '');

		if ($origem === '')
		{
			return $vazia;
		}

		$valores = [];

		foreach ((array) ($cond['valores'] ?? []) as $valor)
		{
			$valor = (string) $valor;

			if ($valor !== '' && !in_array($valor, $valores, true))
			{
				$valores[] = $valor;
			}
		}

		// Condições antigas não são revalidadas (ex.: origem posterior criada no admin).
		if ($this->condicaoInalterada($atual, $colOrigem, $colValor, $origem, $valores))
		{
			return ['origem' => $origem, 'valores' => $valores, 'manter' => true];
		}

		if (!isset($anteriores[$origem]))
		{
			throw new \InvalidArgumentException("A condição da pergunta {$rotulo} precisa usar uma pergunta anterior.");
		}

		$ref = $anteriores[$origem];

		if (!in_array($ref['tipo'], self::TIPOS_SELECAO, true))
		{
			throw new \InvalidArgumentException("A condição da pergunta {$rotulo} usa uma pergunta sem alternativas.");
		}

		$validos = $ref['alternativas'];

		if ($ref['permiteOutro'])
		{
			$validos[] = PerguntaHelper::OUTRO_VALOR;
		}

		if (!$valores || array_diff($valores, $validos))
		{
			throw new \InvalidArgumentException("Selecione alternativas válidas na condição da pergunta {$rotulo}.");
		}

		if ($limite && mb_strlen(json_encode($valores, JSON_UNESCAPED_UNICODE)) > $limite)
		{
			throw new \InvalidArgumentException("A condição de exibição da pergunta {$rotulo} tem alternativas demais (limite de {$limite} caracteres).");
		}

		return ['origem' => $origem, 'valores' => $valores, 'manter' => false];
	}

	private function condicaoInalterada(?object $atual, string $colOrigem, string $colValor, string $origem, array $valores): bool
	{
		if (!$atual || !preg_match('/^p(\d+)$/', $origem, $m) || (int) $atual->$colOrigem !== (int) $m[1])
		{
			return false;
		}

		$antigos = PerguntaHelper::valoresCondicao($atual->$colValor);
		sort($antigos);
		sort($valores);

		return $antigos === $valores;
	}

	private function gravarSecao($db, int $editalId, array $secao, int $ordering, int $userId, ?object $atual): int
	{
		$table = new AgrupamentoTable($db);

		if ($atual)
		{
			$table->load((int) $atual->id);
		}
        
		$tabAtual = (string) $table->no_titulo_tab;
		$array    = array_merge($table->getProperties(), [
			'id'            => $atual ? (int) $atual->id : 0,
			'no_agrupamento' => $secao['titulo'],
			'ds_agrupamento' => $secao['descricao'],
			'ordering'      => $ordering,
			'modified_by'   => $userId,
            'ds_classe_icone' => $secao['classe_icone'] ?? 'fa fa-file',
		]);

		// A aba só acompanha o título quando não foi personalizada.
		if (!$atual || $tabAtual === '' || $tabAtual === (string) $atual->no_agrupamento)
		{
			$array['no_titulo_tab'] = $secao['titulo'];
		}

		if (!$atual)
		{
			$array['state']      = 1;
			$array['created_by'] = $userId;
			$array['co_editais'] = (string) $editalId;
		}

		if (!$table->bind($array) || !$table->store())
		{
			throw new \RuntimeException($table->getError() ?: 'Não foi possível gravar a seção.');
		}

		return (int) $table->id;
	}

	private function gravarPergunta($db, int $editalId, int $secaoId, array $pergunta, int $ordering, int $userId, ?object $atual, array $mapaChaves = []): int
	{
		$table = new PerguntaTable($db);

		if ($atual)
		{
			$table->load((int) $atual->id);
		}

		$tipoAnterior = (string) $table->tp_pergunta;
		$array        = array_merge($table->getProperties(), [
			'id'             => $atual ? (int) $atual->id : 0,
			'ds_titulo'      => $pergunta['enunciado'],
			'ds_descricao'   => $pergunta['ajuda'],
			'tp_pergunta'    => $pergunta['tipo'],
			'tp_requerida'   => $pergunta['obrigatoria'] ? 'sim' : 'nao',
			'co_edital'      => $editalId,
			'co_agrupamento' => $secaoId,
			'ordering'       => $ordering,
			'modified_by'    => $userId,
		]);

		if (!$atual)
		{
			$array['state']      = 1;
			$array['created_by'] = $userId;
			$array['tp_etapa']   = self::ETAPA_PADRAO;
		}

		foreach ([['exibir', 'depende_de', 'depende_de_valor'], ['ocultar', 'depende_de_ocultar', 'depende_de_ocultar_valor']] as [$regra, $colOrigem, $colValor])
		{
			$cond = $pergunta[$regra];

			if ($cond['manter'])
			{
				continue;
			}

			$array[$colOrigem] = $cond['origem'] ? $mapaChaves[$cond['origem']] : null;
			$array[$colValor]  = $cond['origem'] ? json_encode($cond['valores'], JSON_UNESCAPED_UNICODE) : null;
			$array[$colOrigem === 'depende_de' ? 'depende_de_fonte' : 'depende_de_ocultar_fonte'] = null;
		}

		if (in_array($pergunta['tipo'], self::TIPOS_SELECAO, true))
		{
			$opcoes = [];

			foreach ($pergunta['alternativas'] as $i => $nome)
			{
				$opcoes['ds_opcoes' . $i] = ['nome' => $nome, 'valor' => $nome];
			}

			if (!empty($pergunta['permiteOutro']))
			{
				$opcoes['ds_opcoes' . count($opcoes)] = ['nome' => PerguntaHelper::OUTRO_PARAM, 'valor' => 'sim'];
			}

			// Integração com o DW não é editada no construtor: mantém a configuração existente.
			foreach (DwHelper::PARAMETROS as $parametro)
			{
				$valor = PerguntaHelper::getParametro($table, $parametro);

				if ($valor !== '')
				{
					$opcoes['ds_opcoes' . count($opcoes)] = ['nome' => $parametro, 'valor' => $valor];
				}
			}

			$array['ds_opcoes'] = json_encode($opcoes, JSON_UNESCAPED_UNICODE);
		}
		elseif (in_array($tipoAnterior, self::TIPOS_SELECAO, true))
		{
			// Em tipos como "arquivo" e "número" ds_opcoes guarda configuração própria e não pode ser apagado.
			$array['ds_opcoes'] = null;
		}

		if (!$table->bind($array) || !$table->store())
		{
			throw new \RuntimeException($table->getError() ?: 'Não foi possível gravar a pergunta.');
		}

		return (int) $table->id;
	}

	private function removerPerguntas($db, array $ids, int $userId): void
	{
		if (!$ids)
		{
			return;
		}

		$query = $db->getQuery(true)
			->update($db->quoteName('#__tb_pergunta'))
			->set($db->quoteName('state') . ' = -2')
			->set($db->quoteName('modified_by') . ' = ' . $userId)
			->whereIn($db->quoteName('id'), array_map('intval', $ids));

		$db->setQuery($query)->execute();
	}

	private function removerSecao($db, int $editalId, object $secao, int $userId): void
	{
		$editais = array_filter(array_map('trim', explode(',', (string) $secao->co_editais)), 'strlen');
		$outros  = array_values(array_diff($editais, [(string) $editalId]));

		$query = $db->getQuery(true)
			->update($db->quoteName('#__tb_agrupamento'))
			->set($db->quoteName('modified_by') . ' = ' . $userId)
			->where($db->quoteName('id') . ' = ' . (int) $secao->id);

		// Seção compartilhada com outros editais apenas deixa de pertencer a este.
		if ($outros)
		{
			$query->set($db->quoteName('co_editais') . ' = ' . $db->quote(implode(',', $outros)));
		}
		else
		{
			$query->set($db->quoteName('state') . ' = -2');
		}

		$db->setQuery($query)->execute();
	}

	/**
	 * @return  object[]  Indexado pelo id da seção.
	 */
	private function carregarSecoes(int $editalId): array
	{
		$db    = $this->db();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__tb_agrupamento'))
			->where($db->quoteName('state') . ' <> -2')
			->where('FIND_IN_SET(' . (int) $editalId . ', REPLACE(' . $db->quoteName('co_editais') . ', ' . $db->quote(' ') . ', ' . $db->quote('') . '))')
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadObjectList('id') ?: [];
	}

	/**
	 * @return  object[]  Indexado pelo id da pergunta.
	 */
	private function carregarPerguntas(int $editalId, array $secaoIds): array
	{
		if (!$secaoIds)
		{
			return [];
		}

		$db    = $this->db();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__tb_pergunta'))
			->where($db->quoteName('co_edital') . ' = ' . (int) $editalId)
			->where($db->quoteName('state') . ' <> -2')
			->whereIn($db->quoteName('co_agrupamento'), array_map('intval', $secaoIds))
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadObjectList('id') ?: [];
	}

	private function db()
	{
		return Factory::getContainer()->get('DatabaseDriver');
	}
}
