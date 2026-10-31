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
 * Lê apenas o schema coleta_mir, o contrato publicado pelo DW para o Coleta MIR
 * (papel de leitura coleta_leitor). Só executa as consultas do catálogo abaixo:
 * perguntas e requisições escolhem uma fonte pelo nome, nunca enviam SQL. Se o DW não
 * estiver configurado ou não responder, as funções devolvem vazio e o formulário segue
 * com as opções fixas da pergunta.
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
     * da pergunta-pai (depende_de) para o qual a opção vale. As fontes de
     * FONTES_COM_FILTRO recebem :filtro e só são consultadas com ele.
     */
    private const OPCOES = [
        'teds' => "
            SELECT chave AS valor,
                   concat_ws(' - ', nr_instrumento, no_parceiro, left(NULLIF(trim(ds_objeto), ''), 120)) AS nome
            FROM coleta_mir.coleta_instrumento
            WHERE tp_instrumento = 'ted'
            ORDER BY nr_instrumento",
        'convenios' => "
            SELECT chave AS valor,
                   concat_ws(' - ', nr_instrumento, no_parceiro, left(NULLIF(trim(ds_objeto), ''), 120)) AS nome
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
            SELECT valor, valor AS nome FROM (
                SELECT DISTINCT ON (nr_meta, nr_etapa)
                       'Meta ' || nr_meta || COALESCE('.' || nr_etapa, '') || ' - ' || COALESCE(NULLIF(trim(no_meta), ''), ds_meta) AS valor,
                       nr_meta, nr_etapa
                FROM coleta_mir.coleta_instrumento_meta
                WHERE chave_instrumento = :filtro
                ORDER BY nr_meta, nr_etapa NULLS FIRST
            ) x
            WHERE valor IS NOT NULL
            ORDER BY nr_meta, nr_etapa NULLS FIRST",
    ];

    private const FONTES_COM_FILTRO = ['metas_instrumento'];

    /**
     * Registros para pré-preenchimento. Cada consulta recebe :chave e devolve uma linha
     * cujas colunas são os campos referenciados por campo_dw ("registro.coluna").
     */
    private const REGISTROS = [
        'instrumento' => "
            SELECT ds_objeto AS objeto,
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
                   ds_situacao
            FROM coleta_mir.coleta_instrumento
            WHERE chave = :chave",
    ];

    private static $conexao = null;

    private static $opcoesCache = [];

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

    /**
     * Opções de uma fonte: lista de ['nome' => ..., 'valor' => ..., 'filtro' => ...].
     * Vazio quando a fonte não existe, falta o filtro exigido ou o DW não responde.
     */
    public static function getOpcoes(string $fonte, string $filtro = ''): array
    {
        $cacheKey = $fonte . '|' . $filtro;

        if (array_key_exists($cacheKey, self::$opcoesCache)) {
            return self::$opcoesCache[$cacheKey];
        }

        $sql = self::OPCOES[$fonte] ?? null;
        $comFiltro = self::fonteComFiltro($fonte);
        $opcoes = [];

        if ($sql === null) {
            Log::add('Fonte de opções do DW desconhecida: ' . $fonte, Log::WARNING, 'com_edital');
        } elseif ((!$comFiltro || trim($filtro) !== '') && ($db = self::conexao())) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute($comFiltro ? [':filtro' => trim($filtro)] : []);

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
                $opcoes = [];
            }
        }

        return self::$opcoesCache[$cacheKey] = $opcoes;
    }

    /**
     * Campos de um registro, já formatados para os campos do formulário
     * (datas em dd/mm/aaaa, números com ponto decimal). Null quando não encontrado.
     */
    public static function getRegistro(string $registro, string $chave): ?array
    {
        $sql = self::REGISTROS[$registro] ?? null;
        $chave = trim($chave);

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
