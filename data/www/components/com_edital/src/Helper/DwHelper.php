<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use \Joomla\CMS\Log\Log;

/**
 * Leitura do data warehouse do data-application-mir (Postgres).
 *
 * Lê o schema coleta_mir, o contrato publicado pelo DW para o Coleta MIR (papel de leitura
 * coleta_leitor), e os complementos temporários abaixo. Só a sincronização do espelho
 * (EspelhoDwHelper) consulta o DW: os formulários leem as tabelas espelho do banco do
 * Coleta MIR. Só executa as consultas do catálogo abaixo, nunca SQL vindo de fora.
 *
 * Conexão: variáveis DB_DW_HOST_MIR, DB_DW_PORT_MIR, DB_DW_DBNAME_MIR, DB_DW_USER_MIR e
 * DB_DW_PASSWORD_MIR (mesmos nomes usados pelo dbt do data-application-mir).
 *
 * @since  1.0.9
 */
class DwHelper
{
    /** Parâmetro de ds_opcoes: nome da fonte de opções (lista, radio, checkbox). */
    public const PARAM_FONTE = 'fonte_dw';

    /** Parâmetro de ds_opcoes: a resposta desta pergunta identifica um registro do DW. */
    public const PARAM_CHAVE = 'chave_dw';

    /** Parâmetro de ds_opcoes: "registro.campo" que pré-preenche esta pergunta. */
    public const PARAM_CAMPO = 'campo_dw';

    /** Parâmetro de ds_opcoes: fonte de sugestões (datalist) de um campo de texto. */
    public const PARAM_SUGESTOES = 'sugestoes_dw';

    /** Parâmetro de ds_opcoes: as sugestões são filtradas pela resposta da pergunta com esta fonte_dw. */
    public const PARAM_FILTRO = 'filtro_dw';

    public const PARAMETROS = [self::PARAM_FONTE, self::PARAM_CHAVE, self::PARAM_CAMPO, self::PARAM_SUGESTOES, self::PARAM_FILTRO];

    /**
     * Fontes de opções. Cada consulta devolve valor, nome e, opcionalmente, filtro: o valor
     * da pergunta-pai para o qual a opção vale. Nas fontes de FONTES_COM_FILTRO o filtro é a
     * chave do instrumento escolhido no preenchimento.
     */
    private const OPCOES = [
        'teds' => "
            SELECT nr_instrumento AS valor, nr_instrumento AS nome
            FROM coleta_mir.coleta_instrumento
            WHERE tp_instrumento = 'ted'
            ORDER BY nr_instrumento",
        'convenios' => "
            SELECT nr_instrumento AS valor, nr_instrumento AS nome
            FROM coleta_mir.coleta_instrumento
            WHERE tp_instrumento = 'convenio'
            ORDER BY nr_instrumento",
        'ppa_objetivos_especificos' => "
            SELECT codigo || ' - ' || titulo AS valor, codigo || ' - ' || titulo AS nome, codigo_pai AS filtro
            FROM coleta_mir.coleta_lista_ppa
            WHERE nivel = 'objetivo_especifico'
              AND ano_ppa = (SELECT max(ano_ppa) FROM coleta_mir.coleta_lista_ppa)
            ORDER BY codigo",
        'ppa_entregas_medidas' => "
            SELECT valor, valor AS nome, filtro FROM (
                SELECT 'Entrega ' || e.codigo || ' - ' || e.titulo AS valor, o.codigo_pai AS filtro
                FROM coleta_mir.coleta_lista_ppa e
                JOIN coleta_mir.coleta_lista_ppa o
                  ON o.nivel = 'objetivo_especifico' AND o.codigo = e.codigo_pai AND o.ano_ppa = e.ano_ppa
                WHERE e.nivel = 'entrega'
                  AND e.ano_ppa = (SELECT max(ano_ppa) FROM coleta_mir.coleta_lista_ppa)
                UNION
                SELECT 'Medida institucional ' || m.codigo || ' - ' || m.titulo AS valor, m.codigo_pai AS filtro
                FROM coleta_mir.coleta_lista_ppa m
                WHERE m.nivel = 'medida_institucional'
                  AND m.ano_ppa = (SELECT max(ano_ppa) FROM coleta_mir.coleta_lista_ppa)
            ) x
            ORDER BY valor",
        'pei_areas' => "
            SELECT titulo AS valor, titulo AS nome
            FROM coleta_mir.coleta_lista_pei
            WHERE nivel = 'area'
            ORDER BY titulo",
        'pei_objetivos' => "
            SELECT titulo AS valor, titulo AS nome
            FROM coleta_mir.coleta_lista_pei
            WHERE nivel = 'objetivo_estrategico'
            ORDER BY codigo",
        'metas_instrumento' => "
            SELECT valor, valor AS nome, filtro FROM (
                SELECT DISTINCT ON (chave_instrumento, nr_meta, nr_etapa)
                       'Meta ' || nr_meta || COALESCE('.' || nr_etapa, '') || ' - ' || COALESCE(NULLIF(trim(no_meta), ''), ds_meta) AS valor,
                       chave_instrumento AS filtro, nr_meta, nr_etapa
                FROM coleta_mir.coleta_instrumento_meta
                ORDER BY chave_instrumento, nr_meta, nr_etapa NULLS FIRST
            ) x
            WHERE valor IS NOT NULL
            ORDER BY filtro, nr_meta, nr_etapa NULLS FIRST",
    ];

    private const FONTES_COM_FILTRO = ['metas_instrumento'];

    /**
     * Tipo do instrumento de cada fonte de instrumentos. A resposta guarda só o código
     * (nr_instrumento, ex.: "931295"); a chave do contrato é "<tipo>:<código>" (ex.: "ted:931295").
     */
    private const TIPOS_INSTRUMENTO = ['teds' => 'ted', 'convenios' => 'convenio'];

    /**
     * Registros para pré-preenchimento. Cada consulta recebe :chave e devolve uma linha
     * cujas colunas são os campos referenciados por campo_dw ("registro.coluna").
     */
    private const REGISTROS = [
        'instrumento' => "
            SELECT ds_objeto AS objeto,
                   concat_ws(' - ', NULLIF(trim(no_parceiro), ''), NULLIF(trim(ds_objeto), '')) AS descricao,
                   vl_firmado,
                   vl_empenhado,
                   vl_liquidado,
                   vl_pago,
                   dt_inicio_vigencia,
                   dt_fim_vigencia,
                   dt_assinatura,
                   nr_processo_sei,
                   no_parceiro || COALESCE(' (' || upper(tp_identificador) || ' ' || co_identificador || ')', '') AS parceiro,
                   CASE WHEN qt_aditivos > 0 THEN 'Sim' WHEN qt_aditivos = 0 THEN 'Não' END AS existem_aditivos,
                   ds_situacao,
                   CASE
                       WHEN tp_instrumento <> 'convenio' THEN NULL
                       WHEN ds_situacao = 'Proposta/Plano de Trabalho Aprovado' THEN 'Plano de trabalho aprovado, sem início da execução'
                       WHEN ds_situacao = 'Em execução' THEN 'Em execução'
                       WHEN ds_situacao ILIKE '%prestação de contas%' THEN 'Execução concluída, em prestação de contas'
                       WHEN ds_situacao IN ('Cancelado', 'Convênio Anulado') THEN 'Extinta / rescindida'
                   END AS situacao_execucao
            FROM coleta_mir.coleta_instrumento
            WHERE chave = :chave",
        // Fora do contrato: objetivo geral, objetivo estratégico e público-alvo do programa do PPA
        // (chave = código do programa, ex.: "5802"). Sem acesso ao ppa_dbt, fica sem pré-preenchimento.
        'ppa_programa' => "
            SELECT NULLIF(concat_ws(E'\\n\\n',
                       (SELECT 'Objetivo geral: ' || g.enunciado
                        FROM ppa_dbt.objetivo_geral_filtrado_mir g
                        WHERE g.programa::text = :chave
                        ORDER BY g.ano_ppa DESC NULLS LAST, g.exercicio DESC NULLS LAST LIMIT 1),
                       (SELECT 'Objetivo estratégico: ' || o.titulo_objetivo_estrategico
                        FROM ppa_dbt.objetivo_estrategico_programa_filtrado_mir o
                        WHERE o.programa::text = :chave
                        ORDER BY o.exercicio DESC NULLS LAST LIMIT 1),
                       (SELECT 'Público-alvo: ' || p.publico_alvo
                        FROM ppa_dbt.programa_filtrado_mir p
                        WHERE p.programa::text = :chave
                        ORDER BY p.exercicio DESC NULLS LAST LIMIT 1)
                   ), '') AS objetivo_geral",
    ];

    /**
     * Chaves de cada registro, para copiar todos ao espelho. Cada consulta roda à parte e,
     * se falhar (ex.: sem acesso ao ppa_dbt), é ignorada.
     */
    private const CHAVES = [
        'instrumento' => [
            "SELECT chave FROM coleta_mir.coleta_instrumento ORDER BY chave",
        ],
        'ppa_programa' => [
            "SELECT DISTINCT codigo AS chave FROM coleta_mir.coleta_lista_ppa WHERE nivel = 'programa'",
            "SELECT DISTINCT programa::text AS chave FROM ppa_dbt.programa_filtrado_mir",
        ],
    ];

    /** Chave do contrato ("ted:931295") a partir da fonte da pergunta e do código respondido. */
    public static function chaveInstrumento(string $fonte, string $codigo): string
    {
        $codigo = trim($codigo);
        $tipo = self::TIPOS_INSTRUMENTO[$fonte] ?? '';

        return $tipo !== '' && $codigo !== '' && strpos($codigo, ':') === false ? $tipo . ':' . $codigo : $codigo;
    }

    /** Normaliza a chave recebida, por registro. */
    public static function normalizarChave(string $registro, string $chave): string
    {
        $chave = trim($chave);

        // "5802 - Políticas para..." -> "5802"
        if ($registro === 'ppa_programa' && preg_match('/^(\d+)/', $chave, $m)) {
            return $m[1];
        }

        return $chave;
    }

    /**
     * Complementos temporários, fora do contrato coleta_mir: para cada registro, consultas
     * cujas colunas preenchem os campos que o contrato não traz (nunca sobrescrevem o
     * contrato). Cada uma roda à parte e, se falhar (ex.: coleta_leitor sem acesso ao
     * schema), é ignorada. Remover cada uma quando o contrato passar a trazer os campos.
     */
    private const COMPLEMENTOS = [
        'instrumento' => [
            // Processo SEI de TEDs publicado no gov.br (cobre 25 dos 31 TEDs sem processo no
            // contrato, dump de 31/10/2026). Só o num_transf principal: casar pelos
            // num_transf_alternativos atribui o processo de outro TED.
            "SELECT processo AS nr_processo_sei
             FROM siafi_dbt.teds_gov_br
             WHERE 'ted:' || ltrim(trim(num_transf), '0') = :chave
               AND NULLIF(trim(processo), '') IS NOT NULL
             ORDER BY dt_ingest DESC NULLS LAST
             LIMIT 1",
            // TED: unidade do MIR que acompanha, programa do PPA (código), programa orçamentário e
            // justificativa do plano de ação.
            "SELECT r.sigla_unidade_responsavel_acompanhamento AS unidade_mir,
                    NULLIF(trim(r.programa_governo::text), '') AS programa_ppa,
                    NULLIF(concat_ws(' - ', r.programa_governo, r.programa_governo_descricao), '') AS programa_orcamentario,
                    p.tx_justificativa_plano_acao AS justificativa
             FROM siafi_dbt.ted_resumo_orcamentario r
             LEFT JOIN LATERAL (
                 SELECT pa.tx_justificativa_plano_acao
                 FROM siafi_dbt.planos_acao_ted pa
                 WHERE pa.id_plano_acao::text = r.plano_acao::text
                 ORDER BY pa.dt_ingest DESC NULLS LAST
                 LIMIT 1
             ) p ON true
             WHERE r.tipo_instrumento = 'TED' AND 'ted:' || r.num_transf = :chave
             LIMIT 1",
            // TED: UG da unidade do MIR que acompanha, pela sigla (SEPAR -> "238024 - SEPAR/MIR").
            // Sem UG quando a sigla é só "MIR" ou está vazia.
            "SELECT u.ug_codigo || ' - ' || u.ug_nome AS ug_mir
             FROM siafi_dbt.ted_resumo_orcamentario r
             JOIN mir.ugs_mir u ON u.ug_nome = split_part(r.sigla_unidade_responsavel_acompanhamento, '-', 1) || '/MIR'
             WHERE r.tipo_instrumento = 'TED' AND 'ted:' || r.num_transf = :chave
             LIMIT 1",
            // Convênio: UG responsável (ou emitente) no SIAFI; quando vêm várias ("238022, 810008"),
            // a primeira.
            "SELECT concat_ws(' - ', c.ug, u.ug_nome) AS ug_mir
             FROM mir_silver.convenio_mir m
             CROSS JOIN LATERAL (SELECT trim(split_part(coalesce(m.ug_responsavel_codigo, m.ug_emitente), ',', 1)) AS ug) c
             LEFT JOIN mir.ugs_mir u ON u.ug_codigo = c.ug
             WHERE 'convenio:' || m.nr_convenio = :chave
               AND coalesce(m.ug_responsavel_codigo, m.ug_emitente) IS NOT NULL
             ORDER BY (m.ug_responsavel_codigo IS NULL), (m.ug_responsavel_codigo = '810008')
             LIMIT 1",
            // Convênio: valores separados, publicação, natureza do convenente e origem dos recursos,
            // já nos valores das alternativas dos formulários.
            "SELECT CASE WHEN m.modalidade = 'CONVENIO' THEN 'Convênio' END AS tipo_instrumento,
                    m.valor_repasse,
                    m.valor_contrapartida,
                    m.data_publicacao AS dt_publicacao,
                    CASE
                        WHEN m.convenente_natureza_juridica = 'Organização da Sociedade Civil' THEN 'Entidade privada sem fins lucrativos'
                        WHEN m.convenente_natureza_juridica = 'Administração Pública Municipal' THEN 'Administração pública municipal'
                        WHEN m.convenente_natureza_juridica = 'Administração Pública Estadual ou do Distrito Federal'
                            THEN CASE WHEN m.uf = 'DF' THEN 'Administração pública distrital' ELSE 'Administração pública estadual' END
                    END AS natureza_convenente,
                    CASE WHEN m.origem_recurso = 'Recurso próprio' THEN 'Programação própria do MIR' END AS origem_programacao,
                    CASE WHEN m.valor_contrapartida > 0 THEN 'Sim' WHEN m.valor_contrapartida = 0 THEN 'Não' END AS contrapartida_pactuada
             FROM mir_silver.convenio_mir m
             WHERE 'convenio:' || m.nr_convenio = :chave
             LIMIT 1",
            // Convênio: programa do PPA pela ação orçamentária das notas de empenho (só convênios
            // com empenho vinculado no DW: 30 de 409 no dump de 31/10/2026).
            "SELECT max(a.codigo_programa::text) AS programa_ppa
             FROM mir_convenios.fato_execucao_orcamentaria f
             JOIN mir_convenios.dim_convenio c ON c.sk_convenio = f.sk_convenio
             JOIN mir_convenios.dim_acao_orcamentaria a ON a.sk_acao_orcamentaria = f.sk_acao_orcamentaria
             WHERE 'convenio:' || c.nr_convenio = :chave
             HAVING max(a.codigo_programa::text) IS NOT NULL",
        ],
    ];

    private static $conexao = null;

    public static function configurado(): bool
    {
        return (getenv('DB_DW_HOST_MIR') ?: '') !== '';
    }

    private static function conexao(): ?\PDO
    {
        if (self::$conexao !== null) {
            return self::$conexao ?: null;
        }

        self::$conexao = false;

        if (!self::configurado()) {
            return null;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3',
            getenv('DB_DW_HOST_MIR'),
            getenv('DB_DW_PORT_MIR') ?: '5432',
            getenv('DB_DW_DBNAME_MIR') ?: 'analytics'
        );

        try {
            self::$conexao = new \PDO($dsn, getenv('DB_DW_USER_MIR') ?: '', getenv('DB_DW_PASSWORD_MIR') ?: '', [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => true,
            ]);
            self::$conexao->exec("SET statement_timeout = 5000");
        } catch (\Throwable $e) {
            Log::add('DW indisponível: ' . $e->getMessage(), Log::WARNING, 'com_edital');
        }

        return self::$conexao ?: null;
    }

    public static function fonteComFiltro(string $fonte): bool
    {
        return in_array($fonte, self::FONTES_COM_FILTRO, true);
    }

    /** O DW respondeu à conexão. */
    public static function disponivel(): bool
    {
        return self::conexao() !== null;
    }

    /** Nomes das fontes de opções do catálogo. */
    public static function fontes(): array
    {
        return array_keys(self::OPCOES);
    }

    /** Nomes dos registros do catálogo. */
    public static function registros(): array
    {
        return array_keys(self::REGISTROS);
    }

    /** Todas as chaves de um registro (sem repetição), lidas do DW. */
    public static function getChaves(string $registro): array
    {
        $chaves = [];

        foreach (self::CHAVES[$registro] ?? [] as $i => $sql) {
            if (!($db = self::conexao())) {
                break;
            }

            try {
                foreach ($db->query($sql) as $linha) {
                    $chave = self::normalizarChave($registro, (string) ($linha['chave'] ?? ''));

                    if ($chave !== '') {
                        $chaves[$chave] = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::add('Chaves ' . $i . ' de "' . $registro . '" indisponíveis no DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');
            }
        }

        return array_keys($chaves);
    }

    /**
     * Opções de uma fonte: lista de ['nome' => ..., 'valor' => ..., 'filtro' => ...].
     * Null quando a fonte não existe ou o DW não responde (para não apagar o espelho).
     */
    public static function getOpcoes(string $fonte): ?array
    {
        $sql = self::OPCOES[$fonte] ?? null;
        $opcoes = [];

        if ($sql === null) {
            Log::add('Fonte de opções do DW desconhecida: ' . $fonte, Log::WARNING, 'com_edital');

            return null;
        }

        if ($db = self::conexao()) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute();

                foreach ($stmt as $linha) {
                    $valor = trim((string) ($linha['valor'] ?? ''));

                    if ($valor !== '') {
                        $opcoes[] = [
                            'nome'   => trim((string) ($linha['nome'] ?? $valor)) ?: $valor,
                            'valor'  => $valor,
                            'filtro' => isset($linha['filtro']) ? trim((string) $linha['filtro']) : '',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::add('Falha ao ler a fonte "' . $fonte . '" do DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');

                return null;
            }

            return $opcoes;
        }

        return null;
    }

    /**
     * Campos de um registro, já formatados para os campos do formulário
     * (datas em dd/mm/aaaa, números com ponto decimal). Null quando não encontrado.
     */
    public static function getRegistro(string $registro, string $chave): ?array
    {
        $sql = self::REGISTROS[$registro] ?? null;
        $chave = self::normalizarChave($registro, $chave);

        if ($sql === null || $chave === '' || !($db = self::conexao())) {
            return null;
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([':chave' => $chave]);
            $linha = $stmt->fetch();
        } catch (\Throwable $e) {
            Log::add('Falha ao ler o registro "' . $registro . '" do DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');

            return null;
        }

        if (!$linha) {
            return null;
        }

        foreach (self::COMPLEMENTOS[$registro] ?? [] as $i => $sqlComplemento) {
            try {
                $stmt = $db->prepare($sqlComplemento);
                $stmt->execute([':chave' => $chave]);
                $complemento = $stmt->fetch() ?: [];
            } catch (\Throwable $e) {
                Log::add('Complemento ' . $i . ' de "' . $registro . '" indisponível no DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');
                continue;
            }

            foreach ($complemento as $campo => $valor) {
                if (($linha[$campo] ?? null) === null || $linha[$campo] === '') {
                    $linha[$campo] = $valor;
                }
            }
        }

        $dados = [];

        foreach ($linha as $campo => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $valor = (string) $valor;

            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T][\d:.+-]+)?$/', $valor, $m)) {
                $valor = $m[3] . '/' . $m[2] . '/' . $m[1];
            } elseif ($campo === 'nr_processo_sei' && preg_match('/^(\d{5})(\d{6})(\d{4})(\d{2})$/', $valor, $m)) {
                // Processos sem máscara (comuns nos convênios e termos de fomento): 00000.000000/0000-00.
                $valor = $m[1] . '.' . $m[2] . '/' . $m[3] . '-' . $m[4];
            }

            $dados[$campo] = $valor;
        }

        return $dados;
    }
}
