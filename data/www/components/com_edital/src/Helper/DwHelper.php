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
 * Lê só o schema coleta_mir, o contrato publicado pelo DW para o Coleta MIR (papel de leitura
 * coleta_leitor). Só a sincronização do espelho (EspelhoDwHelper) consulta o DW: os
 * formulários leem as tabelas espelho do banco do Coleta MIR. Só executa as consultas do
 * catálogo abaixo, nunca SQL vindo de fora.
 *
 * O catálogo traduz o contrato para os nomes de campo que os formulários usam (campo_dw =
 * "registro.campo"): mudar um alias abaixo exige mudar as perguntas que o usam.
 *
 * Contratos lidos:
 *   - TED: coleta_ted, coleta_ted_execucao, coleta_ted_fonte, coleta_ted_produto e coleta_unidade;
 *   - tedinho (descentralização sem registro no TransfereGov): coleta_tedinho;
 *   - convênio, contrato de repasse, termo de fomento e termo de colaboração: coleta_convenio,
 *     coleta_convenio_execucao, coleta_convenio_fonte e coleta_convenio_produto;
 *   - listas: coleta_lista_ppa e coleta_lista_pei.
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
     * Separador de valores num campo de checkbox vindo do DW (ex.: origem dos recursos com
     * emenda e programação própria): cada parte marca uma alternativa.
     */
    public const SEPARADOR_VALORES = '|';

    /**
     * Fontes de opções. Cada consulta devolve valor, nome e, opcionalmente, filtro: o valor
     * da pergunta-pai para o qual a opção vale. Nas fontes de FONTES_COM_FILTRO o filtro é a
     * chave do instrumento escolhido no preenchimento.
     *
     * Uma fonte pode ter várias consultas, somadas na ordem. Se uma falhar, a fonte inteira
     * fica sem resposta e o espelho mantém as opções anteriores, em vez de perder as dessa parte.
     */
    private const OPCOES = [
        // TEDs pelo número da transferência (o que a pessoa vê no TransfereGov) e tedinhos
        // pelo processo SEI, a chave de coleta_tedinho.
        'teds' => [
            "SELECT nr_transferencia AS valor, nr_transferencia AS nome
             FROM coleta_mir.coleta_ted
             ORDER BY nr_transferencia",
            "SELECT nr_processo_sei AS valor, nr_processo_sei || ' (tedinho - ' || COALESCE(sg_parceiro, 's/ parceiro') || ')' AS nome
             FROM coleta_mir.coleta_tedinho
             ORDER BY nr_processo_sei",
        ],
        // Convênios, contratos de repasse e termos de fomento e de colaboração: o mesmo formulário.
        'convenios' => "
            SELECT nr_convenio AS valor, nr_convenio AS nome
            FROM coleta_mir.coleta_convenio
            ORDER BY nr_convenio",
        // Filtro: código do programa do PPA ("5802"), o começo da opção escolhida em "Programa do PPA".
        'ppa_objetivos_especificos' => "
            SELECT codigo || ' - ' || titulo AS valor, codigo || ' - ' || titulo AS nome, split_part(chave_pai, ':', 2) AS filtro
            FROM coleta_mir.coleta_lista_ppa
            WHERE nivel = 'objetivo_especifico' AND in_vigente
            ORDER BY codigo",
        'ppa_entregas_medidas' => "
            SELECT valor, valor AS nome, filtro FROM (
                SELECT 'Entrega ' || e.codigo || ' - ' || e.titulo AS valor, split_part(o.chave_pai, ':', 2) AS filtro
                FROM coleta_mir.coleta_lista_ppa e
                JOIN coleta_mir.coleta_lista_ppa o ON o.chave = e.chave_pai
                WHERE e.nivel = 'entrega' AND e.in_vigente
                UNION
                SELECT 'Medida institucional ' || m.codigo || ' - ' || m.titulo AS valor, split_part(m.chave_pai, ':', 2) AS filtro
                FROM coleta_mir.coleta_lista_ppa m
                WHERE m.nivel = 'medida_institucional_programa' AND m.in_vigente
                UNION
                SELECT 'Medida institucional ' || m.codigo || ' - ' || m.titulo AS valor, split_part(o.chave_pai, ':', 2) AS filtro
                FROM coleta_mir.coleta_lista_ppa m
                JOIN coleta_mir.coleta_lista_ppa o ON o.chave = m.chave_pai
                WHERE m.nivel = 'medida_institucional_objetivo_especifico' AND m.in_vigente
            ) x
            ORDER BY valor",
        'pei_areas' => "
            SELECT titulo AS valor, titulo AS nome
            FROM coleta_mir.coleta_lista_pei
            WHERE nivel = 'area'
            ORDER BY nr_ordem NULLS LAST, titulo",
        'pei_objetivos' => "
            SELECT titulo AS valor, titulo AS nome
            FROM coleta_mir.coleta_lista_pei
            WHERE nivel = 'objetivo_estrategico'
            ORDER BY nr_ordem NULLS LAST, length(codigo), codigo",
        // Metas e etapas do plano. Filtro: chave do instrumento ("ted:946708", "convenio:700236").
        'metas_instrumento' => [
            "SELECT valor, valor AS nome, filtro FROM (
                SELECT 'ted:' || t.nr_transferencia AS filtro, p.nr_meta, p.nr_etapa,
                       'Meta ' || p.nr_meta || COALESCE('.' || p.nr_etapa, '') || ' - '
                           || %RESUMO(COALESCE(NULLIF(trim(p.no_item), ''), trim(p.ds_item)))% AS valor
                FROM coleta_mir.coleta_ted_produto p
                JOIN coleta_mir.coleta_ted t ON t.id_plano_acao = p.id_plano_acao
             ) x
             WHERE valor IS NOT NULL
             ORDER BY filtro, nr_meta, nr_etapa NULLS FIRST",
            "SELECT valor, valor AS nome, filtro FROM (
                SELECT 'convenio:' || p.nr_convenio AS filtro, p.nr_meta, p.nr_etapa,
                       'Meta ' || p.nr_meta || COALESCE('.' || p.nr_etapa, '') || ' - '
                           || %RESUMO(COALESCE(NULLIF(trim(p.no_item), ''), trim(p.ds_item)))% AS valor
                FROM coleta_mir.coleta_convenio_produto p
             ) x
             WHERE valor IS NOT NULL
             ORDER BY filtro, nr_meta, nr_etapa NULLS FIRST",
        ],
    ];

    private const FONTES_COM_FILTRO = ['metas_instrumento'];

    /**
     * Tipo do instrumento de cada fonte de instrumentos. A resposta guarda só o código
     * (ex.: "931295"); a chave do registro é "<tipo>:<código>" (ex.: "ted:931295"). Os
     * tedinhos entram como "ted:<processo SEI>"; os termos de fomento, como "convenio:<número>".
     */
    private const TIPOS_INSTRUMENTO = ['teds' => 'ted', 'convenios' => 'convenio'];

    /**
     * Valor em R$ com separadores brasileiros ("1.234,56"), para os textos montados abaixo.
     * %s é a expressão numérica.
     */
    private const BRL = "translate(to_char(%s, 'FM999,999,999,990.00'), ',.', '.,')";

    /**
     * Texto longo cortado em 250 caracteres, com reticências: as descrições de meta do siconv
     * passam de 2 mil caracteres e não cabem como sugestão (nem no espelho). %s é o texto.
     */
    private const RESUMO = 'CASE WHEN length(%1$s) > 250 THEN left(%1$s, 249) || \'…\' ELSE %1$s END';

    /**
     * Registros para pré-preenchimento. Cada consulta recebe :chave e devolve uma linha
     * cujas colunas são os campos referenciados por campo_dw ("registro.coluna").
     *
     * Um registro pode ter várias consultas, uma por contrato: vale a primeira que achar a
     * chave. Se uma falhar (ex.: contrato ainda não publicado no DW), as outras seguem.
     * Consultas agrupadas pelo começo da chave ("ted:", "convenio:") só rodam para as chaves
     * com esse começo.
     */
    private const REGISTROS = [
        'instrumento' => [
          'ted:' => [
            // TED. A UG é a da unidade descentralizadora (coleta_unidade.cd_ug, 810008); o
            // gestor do TED é liberado pela UG do parceiro (tp_identificador/co_identificador).
            "SELECT t.ds_objeto AS objeto,
                    concat_ws(' - ', NULLIF(trim(t.no_parceiro), ''), NULLIF(trim(t.ds_objeto), '')) AS descricao,
                    t.vl_total AS vl_firmado,
                    e.vl_empenhado,
                    e.vl_liquidado,
                    e.vl_pago,
                    t.dt_inicio_vigencia,
                    t.dt_fim_vigencia,
                    t.dt_assinatura,
                    t.nr_processo_sei,
                    t.no_parceiro || COALESCE(' (UG ' || t.cd_ug_parceiro || ')', '') AS parceiro,
                    t.no_parceiro,
                    CASE WHEN t.cd_ug_parceiro IS NOT NULL THEN 'ug' END AS tp_identificador,
                    t.cd_ug_parceiro AS co_identificador,
                    COALESCE(t.ds_situacao_termo, t.ds_situacao_plano) AS ds_situacao,
                    NULLIF(concat_ws(' - ', t.sg_unidade_acompanhamento, t.no_unidade_acompanhamento), '') AS unidade_mir,
                    u.cd_ug AS ug_mir,
                    t.cd_programa_ppa AS programa_ppa,
                    (SELECT string_agg(f.linha, E'\\n' ORDER BY f.aa_emissao, f.linha)
                     FROM (
                         SELECT aa_emissao,
                                concat_ws(' · ', aa_emissao::text, 'Programa ' || cd_programa, 'Ação ' || cd_acao,
                                          'PTRES ' || cd_ptres, 'Fonte ' || cd_fonte, 'ND ' || cd_natureza_despesa)
                                    || ': R$ ' || %BRL(vl_liquido)% AS linha
                         FROM coleta_mir.coleta_ted_fonte
                         WHERE id_plano_acao = t.id_plano_acao
                     ) f) AS programa_orcamentario,
                    t.ds_justificativa AS justificativa
             FROM coleta_mir.coleta_ted t
             LEFT JOIN coleta_mir.coleta_ted_execucao e ON e.id_plano_acao = t.id_plano_acao
             LEFT JOIN coleta_mir.coleta_unidade u ON u.sg_unidade = t.sg_unidade_acompanhamento
             WHERE 'ted:' || t.nr_transferencia = :chave",
            // Tedinho: não tem UG do parceiro, então não aparece para gestor de TED.
            "SELECT d.ds_objeto AS objeto,
                    concat_ws(' - ', NULLIF(trim(d.sg_parceiro), ''), NULLIF(trim(d.ds_objeto), '')) AS descricao,
                    d.vl_total AS vl_firmado,
                    d.vl_empenhado,
                    d.vl_pago,
                    d.nr_processo_sei,
                    d.sg_parceiro AS parceiro,
                    d.sg_parceiro AS no_parceiro,
                    d.ds_observacao
             FROM coleta_mir.coleta_tedinho d
             WHERE 'ted:' || d.nr_processo_sei = :chave",
          ],
          'convenio:' => [
            // Convênio, contrato de repasse, termo de fomento e termo de colaboração. Os campos de
            // alternativas (tipo, natureza, situação, origem) já saem nos valores do formulário.
            //
            // Enquanto o contrato não traz programa do PPA único nem situação mapeada
            // (data-application-gov-hub#534), os dois são calculados aqui: programa vigente
            // primeiro, depois o de maior empenho, como em coleta_ted.cd_programa_ppa.
            "SELECT c.ds_objeto AS objeto,
                    concat_ws(' - ', NULLIF(trim(c.no_convenente), ''), NULLIF(trim(c.ds_objeto), '')) AS descricao,
                    CASE c.tp_instrumento
                        WHEN 'CONVENIO' THEN 'Convênio'
                        WHEN 'CONTRATO DE REPASSE' THEN 'Contrato de repasse'
                        WHEN 'TERMO DE FOMENTO' THEN 'Termo de fomento'
                        WHEN 'TERMO DE COLABORACAO' THEN 'Termo de colaboração'
                    END AS tipo_instrumento,
                    c.vl_global AS vl_firmado,
                    c.vl_repasse AS valor_repasse,
                    c.vl_contrapartida AS valor_contrapartida,
                    CASE WHEN c.vl_contrapartida > 0 THEN 'Sim' WHEN c.vl_contrapartida = 0 THEN 'Não' END AS contrapartida_pactuada,
                    e.vl_desembolsado AS vl_pago,
                    e.vl_contrapartida_aportada,
                    c.dt_inicio_vigencia,
                    c.dt_fim_vigencia,
                    c.dt_assinatura,
                    c.dt_publicacao,
                    c.nr_processo AS nr_processo_sei,
                    CASE WHEN c.qt_aditivos > 0 THEN 'Sim' ELSE 'Não' END AS existem_aditivos,
                    CASE WHEN c.qt_aditivos > 0
                         THEN concat_ws(', assinado em ', c.nr_ultimo_aditivo, to_char(c.dt_ultimo_aditivo, 'DD/MM/YYYY'))
                    END AS ultimo_aditivo,
                    c.no_convenente || COALESCE(' (CNPJ ' || c.nr_cnpj_convenente || ')', '') AS parceiro,
                    c.no_convenente AS no_parceiro,
                    CASE WHEN c.nr_cnpj_convenente IS NOT NULL THEN 'cnpj' END AS tp_identificador,
                    c.nr_cnpj_convenente AS co_identificador,
                    c.tp_natureza_convenente AS natureza_convenente,
                    c.ds_situacao,
                    CASE
                        WHEN c.ds_situacao IN ('Assinado', 'Proposta/Plano de Trabalho Aprovado', 'Assinatura Pendente Registro TV Siafi')
                            THEN 'Plano de trabalho aprovado, sem início da execução'
                        WHEN c.ds_situacao = 'Em execução' THEN 'Em execução'
                        WHEN c.ds_situacao ILIKE '%prestação de contas%' THEN 'Execução concluída, em prestação de contas'
                        WHEN c.ds_situacao ILIKE '%suspen%' THEN 'Suspensa'
                        WHEN c.ds_situacao ILIKE '%rescind%' OR c.ds_situacao ILIKE '%extint%'
                            OR c.ds_situacao IN ('Cancelado', 'Convênio Anulado') THEN 'Extinta / rescindida'
                    END AS situacao_execucao,
                    NULLIF(concat_ws(' - ', c.sg_unidade_concedente, c.no_unidade_concedente), '') AS unidade_mir,
                    c.cd_ug_concedente AS ug_mir,
                    (SELECT p.cd_programa
                     FROM (
                         SELECT cd_programa, sum(vl_empenhado) AS vl
                         FROM coleta_mir.coleta_convenio_fonte
                         WHERE nr_convenio = c.nr_convenio AND cd_programa IS NOT NULL
                         GROUP BY cd_programa
                     ) p
                     ORDER BY EXISTS (
                                  SELECT 1 FROM coleta_mir.coleta_lista_ppa l
                                  WHERE l.nivel = 'programa' AND l.in_vigente AND l.codigo = p.cd_programa
                              ) DESC,
                              p.vl DESC NULLS LAST, p.cd_programa
                     LIMIT 1) AS programa_ppa,
                    (SELECT string_agg(f.linha, E'\\n' ORDER BY f.aa_emissao, f.linha)
                     FROM (
                         SELECT aa_emissao,
                                concat_ws(' · ', aa_emissao::text,
                                          'Programa ' || NULLIF(concat_ws(' - ', cd_programa, no_programa), ''),
                                          'Ação ' || NULLIF(concat_ws(' - ', cd_acao, no_acao), ''),
                                          'UO ' || cd_uo,
                                          'PTRES ' || cd_ptres,
                                          'Fonte ' || NULLIF(concat_ws(' - ', cd_fonte, no_fonte), ''),
                                          'ND ' || NULLIF(concat_ws(' - ', cd_natureza_despesa, no_natureza_despesa), ''))
                                    || ': R$ ' || %BRL(sum(vl_empenhado))% AS linha
                         FROM coleta_mir.coleta_convenio_fonte
                         WHERE nr_convenio = c.nr_convenio
                         GROUP BY aa_emissao, cd_programa, no_programa, cd_acao, no_acao, cd_uo, cd_ptres,
                                  cd_fonte, no_fonte, cd_natureza_despesa, no_natureza_despesa
                     ) f) AS programa_orcamentario,
                    (SELECT string_agg(DISTINCT tp_origem, '|')
                     FROM coleta_mir.coleta_convenio_fonte
                     WHERE nr_convenio = c.nr_convenio AND tp_origem <> 'Outra') AS origem_programacao,
                    (SELECT string_agg(DISTINCT cd_emenda || COALESCE(' - ' || no_autor_emenda, ''), '; ')
                     FROM coleta_mir.coleta_convenio_fonte
                     WHERE nr_convenio = c.nr_convenio AND cd_emenda <> 'sem_emenda') AS emendas,
                    c.ds_justificativa AS justificativa
             FROM coleta_mir.coleta_convenio c
             LEFT JOIN coleta_mir.coleta_convenio_execucao e ON e.nr_convenio = c.nr_convenio
             WHERE 'convenio:' || c.nr_convenio = :chave",
          ],
        ],
        // Objetivo geral, objetivos estratégicos e público-alvo do programa do PPA
        // (chave = código do programa, ex.: "5802").
        'ppa_programa' => "
            SELECT NULLIF(concat_ws(E'\\n\\n',
                       (SELECT 'Objetivo geral: ' || string_agg(titulo, '; ' ORDER BY codigo)
                        FROM coleta_mir.coleta_lista_ppa
                        WHERE nivel = 'objetivo_geral' AND in_vigente AND chave_pai = 'programa:' || :chave),
                       (SELECT 'Objetivos estratégicos: ' || string_agg(titulo, '; ' ORDER BY codigo)
                        FROM coleta_mir.coleta_lista_ppa
                        WHERE nivel = 'objetivo_estrategico' AND in_vigente AND chave_pai = 'programa:' || :chave),
                       (SELECT 'Público-alvo: ' || ds_publico_alvo
                        FROM coleta_mir.coleta_lista_ppa
                        WHERE chave = 'programa:' || :chave)
                   ), '') AS objetivo_geral",
    ];

    /**
     * Chaves de cada registro, para copiar todos ao espelho. Cada consulta roda à parte e,
     * se falhar (ex.: contrato ainda não publicado no DW), é ignorada.
     */
    private const CHAVES = [
        'instrumento' => [
            "SELECT 'ted:' || nr_transferencia AS chave FROM coleta_mir.coleta_ted",
            "SELECT 'ted:' || nr_processo_sei AS chave FROM coleta_mir.coleta_tedinho",
            "SELECT 'convenio:' || nr_convenio AS chave FROM coleta_mir.coleta_convenio",
        ],
        'ppa_programa' => [
            "SELECT codigo AS chave FROM coleta_mir.coleta_lista_ppa WHERE nivel = 'programa' AND in_vigente",
        ],
    ];

    /** Tipo do instrumento ("ted", "convenio") de uma fonte de instrumentos, ou vazio. */
    public static function tipoInstrumento(string $fonte): string
    {
        return self::TIPOS_INSTRUMENTO[$fonte] ?? '';
    }

    /** Chave do registro ("ted:931295") a partir da fonte da pergunta e do código respondido. */
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

    /**
     * Consultas de uma entrada do catálogo (uma ou várias), com os valores em R$ já expandidos.
     * Numa entrada agrupada pelo começo da chave, só as do grupo de $chave.
     */
    private static function consultas($entrada, string $chave = ''): array
    {
        if (is_array($entrada) && !array_is_list($entrada)) {
            $grupo = [];

            foreach ($entrada as $prefixo => $sqls) {
                if (strpos($chave, $prefixo) === 0) {
                    $grupo = $sqls;
                    break;
                }
            }

            $entrada = $grupo;
        }

        return array_map(
            static fn (string $sql) => preg_replace_callback(
                '/%(BRL|RESUMO)\((.+?)\)%/s',
                static fn ($m) => sprintf($m[1] === 'BRL' ? self::BRL : self::RESUMO, $m[2]),
                $sql
            ),
            (array) $entrada
        );
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
     * Null quando a fonte não existe ou o DW não responde a alguma das consultas (para não
     * apagar o espelho).
     */
    public static function getOpcoes(string $fonte): ?array
    {
        if (!isset(self::OPCOES[$fonte])) {
            Log::add('Fonte de opções do DW desconhecida: ' . $fonte, Log::WARNING, 'com_edital');

            return null;
        }

        if (!($db = self::conexao())) {
            return null;
        }

        $opcoes = [];

        foreach (self::consultas(self::OPCOES[$fonte]) as $i => $sql) {
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
                Log::add('Falha ao ler a fonte "' . $fonte . '" (consulta ' . $i . ') do DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');

                return null;
            }
        }

        return $opcoes;
    }

    /**
     * Campos de um registro, já formatados para os campos do formulário
     * (datas em dd/mm/aaaa, números com ponto decimal). Null quando não encontrado.
     */
    public static function getRegistro(string $registro, string $chave): ?array
    {
        $chave = self::normalizarChave($registro, $chave);

        if (!isset(self::REGISTROS[$registro]) || $chave === '' || !($db = self::conexao())) {
            return null;
        }

        $linha = null;

        foreach (self::consultas(self::REGISTROS[$registro], $chave) as $i => $sql) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute([':chave' => $chave]);
                $linha = $stmt->fetch() ?: null;
            } catch (\Throwable $e) {
                Log::add('Falha ao ler o registro "' . $registro . '" (consulta ' . $i . ') do DW: ' . $e->getMessage(), Log::WARNING, 'com_edital');
                continue;
            }

            if ($linha) {
                break;
            }
        }

        if (!$linha) {
            return null;
        }

        $dados = [];

        foreach ($linha as $campo => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $valor = (string) $valor;

            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T][\d:.+-]+)?$/', $valor, $m)) {
                $valor = $m[3] . '/' . $m[2] . '/' . $m[1];
            }

            $dados[$campo] = $valor;
        }

        return $dados;
    }
}
