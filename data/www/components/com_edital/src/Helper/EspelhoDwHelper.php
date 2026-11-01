<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Espelho do data warehouse no banco do Coleta MIR.
 *
 * O DW é a base: quando está configurado e responde, a sincronização copia para tabelas
 * próprias (#__coleta_dw_*) as opções e os registros já consolidados (contrato coleta_mir
 * mais os complementos de DwHelper) e leva as opções às alternativas das perguntas com
 * fonte_dw. Os formulários leem só o banco do Coleta MIR, que segue funcionando com o DW
 * fora do ar. As respostas ficam nas tabelas do Coleta MIR, de onde o DW poderá lê-las.
 *
 * A sincronização roda sozinha: as telas do componente disparam uma requisição em segundo
 * plano quando o espelho está vazio ou com mais de DB_DW_SINCRONIZAR_HORAS horas (padrão 24).
 *
 * @since  1.0.9
 */
class EspelhoDwHelper
{
    /**
     * Parâmetro de ds_opcoes: valores do DW que foram excluídos da pergunta no construtor e
     * que a sincronização não deve trazer de volta (JSON).
     */
    public const PARAM_REMOVIDOS = 'dw_removidos';

    private const TABELA_OPCAO = '#__coleta_dw_opcao';
    private const TABELA_REGISTRO = '#__coleta_dw_registro';
    private const TABELA_SINCRONIZACAO = '#__coleta_dw_sincronizacao';

    /** Sincronização em andamento há mais que isso é considerada interrompida. */
    private const MINUTOS_EM_ANDAMENTO = 15;

    /** Depois de uma falha (DW fora do ar), espera isso antes de tentar de novo. */
    private const MINUTOS_APOS_ERRO = 30;

    private const TIPOS_SELECAO = ['lista', 'radio', 'checkbox'];

    private static $opcoesCache = [];

    private static function db()
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }

    private static function agora(): string
    {
        return Factory::getDate()->toSql();
    }

    private static function horasValidade(): int
    {
        return max(1, (int) (getenv('DB_DW_SINCRONIZAR_HORAS') ?: 24));
    }

    /** Cria as tabelas espelho, se faltarem, e amplia ds_opcoes para listas grandes (409 convênios). */
    public static function garantirTabelas(): void
    {
        $db = self::db();

        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABELA_OPCAO) . ' (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `fonte` VARCHAR(64) NOT NULL,
            `valor` VARCHAR(1000) NOT NULL,
            `nome` TEXT NOT NULL,
            `filtro` VARCHAR(191) NOT NULL DEFAULT \'\',
            `ordem` INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_fonte_filtro` (`fonte`, `filtro`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();

        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABELA_REGISTRO) . ' (
            `registro` VARCHAR(32) NOT NULL,
            `chave` VARCHAR(191) NOT NULL,
            `dados` MEDIUMTEXT NOT NULL,
            `dt_sincronizacao` DATETIME NOT NULL,
            PRIMARY KEY (`registro`, `chave`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();

        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName(self::TABELA_SINCRONIZACAO) . ' (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `dt_inicio` DATETIME NOT NULL,
            `dt_fim` DATETIME NULL,
            `st_sincronizacao` VARCHAR(20) NOT NULL,
            `qt_opcoes` INT NOT NULL DEFAULT 0,
            `qt_registros` INT NOT NULL DEFAULT 0,
            `qt_perguntas` INT NOT NULL DEFAULT 0,
            `ds_mensagem` TEXT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')->execute();

        $tipo = $db->setQuery(
            $db->getQuery(true)
                ->select('DATA_TYPE')
                ->from('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME = ' . $db->quote($db->replacePrefix('#__tb_pergunta')))
                ->where('COLUMN_NAME = ' . $db->quote('ds_opcoes'))
        )->loadResult();

        if ($tipo === 'text') {
            $db->setQuery('ALTER TABLE ' . $db->quoteName('#__tb_pergunta') . ' MODIFY `ds_opcoes` MEDIUMTEXT NULL')->execute();
        }
    }

    /** Última sincronização registrada, ou null (inclusive quando as tabelas ainda não existem). */
    public static function ultimaSincronizacao(): ?object
    {
        try {
            $db = self::db();

            return $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName(self::TABELA_SINCRONIZACAO))
                    ->order('id DESC'),
                0,
                1
            )->loadObject() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** O DW está configurado e o espelho está vazio, vencido ou com falha antiga. */
    public static function precisaSincronizar(): bool
    {
        if (!DwHelper::configurado()) {
            return false;
        }

        $ultima = self::ultimaSincronizacao();

        if (!$ultima) {
            return true;
        }

        $minutos = (time() - strtotime($ultima->dt_inicio . ' UTC')) / 60;

        switch ($ultima->st_sincronizacao) {
            case 'andamento':
                return $minutos > self::MINUTOS_EM_ANDAMENTO;
            case 'erro':
                return $minutos > self::MINUTOS_APOS_ERRO;
            default:
                return $minutos > self::horasValidade() * 60;
        }
    }

    /**
     * Nas telas do componente, dispara a sincronização em segundo plano quando necessária,
     * sem atrasar a página.
     */
    public static function agendarSincronizacao(): void
    {
        $app = Factory::getApplication();

        if (!self::precisaSincronizar() || $app->getDocument()->getType() !== 'html') {
            return;
        }

        $url = Uri::root() . 'index.php?option=com_edital&task=espelhodw.sincronizar&' . Session::getFormToken() . '=1';

        $app->getDocument()->addScriptDeclaration(
            'window.addEventListener("load",function(){fetch(' . json_encode($url) . ',{credentials:"same-origin"}).catch(function(){});});'
        );
    }

    /**
     * Copia o DW para o espelho e atualiza as alternativas das perguntas com fonte_dw.
     * Devolve o registro da sincronização. Não faz nada se outra estiver em andamento.
     */
    public static function sincronizar(): ?object
    {
        self::garantirTabelas();

        $db = self::db();

        // Só uma sincronização por vez.
        $ultima = self::ultimaSincronizacao();

        if ($ultima && $ultima->st_sincronizacao === 'andamento'
            && time() - strtotime($ultima->dt_inicio . ' UTC') < self::MINUTOS_EM_ANDAMENTO * 60) {
            return null;
        }

        $registro = (object) [
            'dt_inicio'        => self::agora(),
            'st_sincronizacao' => 'andamento',
        ];
        $db->insertObject(self::TABELA_SINCRONIZACAO, $registro, 'id');

        @set_time_limit(600);
        ignore_user_abort(true);

        $mensagens = [];
        $qtOpcoes = 0;
        $qtRegistros = 0;
        $qtPerguntas = 0;

        try {
            if (!DwHelper::disponivel()) {
                throw new \RuntimeException('Data warehouse indisponível.');
            }

            foreach (DwHelper::fontes() as $fonte) {
                $opcoes = DwHelper::getOpcoes($fonte);

                if ($opcoes === null) {
                    $mensagens[] = 'Fonte "' . $fonte . '" não lida; o espelho anterior foi mantido.';
                    continue;
                }

                self::gravarOpcoes($fonte, $opcoes);
                $qtOpcoes += count($opcoes);
            }

            foreach (DwHelper::registros() as $nome) {
                $chaves = DwHelper::getChaves($nome);

                if (!$chaves) {
                    $mensagens[] = 'Registro "' . $nome . '" sem chaves no DW; o espelho anterior foi mantido.';
                    continue;
                }

                $dados = [];

                foreach ($chaves as $chave) {
                    if (($linha = DwHelper::getRegistro($nome, $chave)) !== null) {
                        $dados[$chave] = $linha;
                    }
                }

                self::gravarRegistros($nome, $dados);
                $qtRegistros += count($dados);
            }

            $qtPerguntas = self::atualizarPerguntas();
            $registro->st_sincronizacao = 'ok';
        } catch (\Throwable $e) {
            $registro->st_sincronizacao = 'erro';
            $mensagens[] = $e->getMessage();
            Log::add('Sincronização do espelho do DW falhou: ' . $e->getMessage(), Log::WARNING, 'com_edital');
        }

        $registro->dt_fim = self::agora();
        $registro->qt_opcoes = $qtOpcoes;
        $registro->qt_registros = $qtRegistros;
        $registro->qt_perguntas = $qtPerguntas;
        $registro->ds_mensagem = $mensagens ? implode("\n", $mensagens) : null;
        $db->updateObject(self::TABELA_SINCRONIZACAO, $registro, 'id', true);

        self::$opcoesCache = [];

        return $registro;
    }

    private static function gravarOpcoes(string $fonte, array $opcoes): void
    {
        $db = self::db();
        $db->transactionStart();

        try {
            $db->setQuery(
                $db->getQuery(true)->delete($db->quoteName(self::TABELA_OPCAO))->where('fonte = ' . $db->quote($fonte))
            )->execute();

            foreach (array_chunk($opcoes, 200, true) as $lote) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName(self::TABELA_OPCAO))
                    ->columns($db->quoteName(['fonte', 'valor', 'nome', 'filtro', 'ordem']));

                foreach ($lote as $ordem => $opcao) {
                    $query->values(implode(',', [
                        $db->quote($fonte),
                        $db->quote($opcao['valor']),
                        $db->quote($opcao['nome']),
                        $db->quote($opcao['filtro'] ?? ''),
                        (int) $ordem,
                    ]));
                }

                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
    }

    private static function gravarRegistros(string $nome, array $dados): void
    {
        $db = self::db();
        $agora = self::agora();
        $db->transactionStart();

        try {
            $db->setQuery(
                $db->getQuery(true)->delete($db->quoteName(self::TABELA_REGISTRO))->where('registro = ' . $db->quote($nome))
            )->execute();

            foreach (array_chunk($dados, 200, true) as $lote) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName(self::TABELA_REGISTRO))
                    ->columns($db->quoteName(['registro', 'chave', 'dados', 'dt_sincronizacao']));

                foreach ($lote as $chave => $linha) {
                    $query->values(implode(',', [
                        $db->quote($nome),
                        $db->quote((string) $chave),
                        $db->quote(json_encode($linha, JSON_UNESCAPED_UNICODE)),
                        $db->quote($agora),
                    ]));
                }

                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
    }

    /**
     * Opções do espelho: lista de ['nome' => ..., 'valor' => ..., 'filtro' => ...], na ordem do DW.
     * Com $filtro, só as opções desse filtro (ex.: metas de um instrumento).
     */
    public static function getOpcoes(string $fonte, ?string $filtro = null): array
    {
        $cacheKey = $fonte . '|' . $filtro;

        if (isset(self::$opcoesCache[$cacheKey])) {
            return self::$opcoesCache[$cacheKey];
        }

        $opcoes = [];

        try {
            $db = self::db();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['valor', 'nome', 'filtro']))
                ->from($db->quoteName(self::TABELA_OPCAO))
                ->where('fonte = ' . $db->quote($fonte))
                ->order('ordem');

            if ($filtro !== null) {
                $query->where('filtro = ' . $db->quote(trim($filtro)));
            }

            $opcoes = $db->setQuery($query)->loadAssocList();
        } catch (\Throwable $e) {
            // Espelho ainda não criado: sem opções até a primeira sincronização.
        }

        return self::$opcoesCache[$cacheKey] = $opcoes;
    }

    /** Campos de um registro do espelho (já formatados para os formulários), ou null. */
    public static function getRegistro(string $registro, string $chave): ?array
    {
        $chave = DwHelper::normalizarChave($registro, $chave);

        if ($chave === '') {
            return null;
        }

        try {
            $db = self::db();
            $dados = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('dados'))
                    ->from($db->quoteName(self::TABELA_REGISTRO))
                    ->where('registro = ' . $db->quote($registro))
                    ->where('chave = ' . $db->quote($chave))
            )->loadResult();
        } catch (\Throwable $e) {
            return null;
        }

        $dados = $dados ? json_decode($dados, true) : null;

        return is_array($dados) ? $dados : null;
    }

    /**
     * Leva as opções do espelho às alternativas das perguntas com fonte_dw, que passam a ser
     * editáveis no construtor. Alternativas já existentes mantêm o nome (pode ter sido editado)
     * e recebem o filtro do DW; as novas entram na ordem do DW; as excluídas no construtor
     * (dw_removidos) não voltam; as que saíram do DW ficam, pois podem ter respostas.
     * Devolve quantas perguntas mudaram.
     */
    public static function atualizarPerguntas(): int
    {
        $db = self::db();
        $perguntas = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'tp_pergunta', 'ds_opcoes']))
                ->from($db->quoteName('#__tb_pergunta'))
                ->whereIn($db->quoteName('tp_pergunta'), self::TIPOS_SELECAO, \Joomla\Database\ParameterType::STRING)
                ->where($db->quoteName('ds_opcoes') . ' LIKE ' . $db->quote('%' . DwHelper::PARAM_FONTE . '%'))
        )->loadObjectList();

        $alteradas = 0;

        foreach ($perguntas as $pergunta) {
            $fonte = PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_FONTE);

            if ($fonte === '' || DwHelper::fonteComFiltro($fonte) || !($opcoesDw = self::getOpcoes($fonte))) {
                continue;
            }

            $novo = self::mesclarOpcoes($pergunta, $opcoesDw);

            if ($novo !== $pergunta->ds_opcoes) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__tb_pergunta'))
                        ->set($db->quoteName('ds_opcoes') . ' = ' . $db->quote($novo))
                        ->where('id = ' . (int) $pergunta->id)
                )->execute();
                $alteradas++;
            }
        }

        return $alteradas;
    }

    /** ds_opcoes da pergunta com as opções do DW mescladas (ver atualizarPerguntas). */
    private static function mesclarOpcoes(object $pergunta, array $opcoesDw): string
    {
        $decoded = json_decode((string) $pergunta->ds_opcoes);
        $atuais = [];
        $parametros = [];

        foreach ((is_array($decoded) || is_object($decoded)) ? $decoded : [] as $opcao) {
            if (!is_object($opcao)) {
                continue;
            }

            if (PerguntaHelper::ehParametro($opcao)) {
                $parametros[] = ['nome' => (string) $opcao->nome, 'valor' => (string) ($opcao->valor ?? '')];
            } else {
                $valor = (string) ($opcao->valor ?? $opcao->nome ?? '');
                $atuais[$valor] = ['nome' => (string) ($opcao->nome ?? $valor), 'valor' => $valor];
            }
        }

        $removidos = array_flip(self::getRemovidos($pergunta));
        $resultado = [];

        foreach ($opcoesDw as $opcaoDw) {
            $valor = $opcaoDw['valor'];

            if (isset($atuais[$valor])) {
                $opcao = $atuais[$valor];
                unset($atuais[$valor]);
            } elseif (isset($removidos[$valor])) {
                continue;
            } else {
                $opcao = ['nome' => $opcaoDw['nome'], 'valor' => $valor];
            }

            if (($opcaoDw['filtro'] ?? '') !== '') {
                $opcao['filtro'] = $opcaoDw['filtro'];
            }

            $resultado[] = $opcao;
        }

        // Versões antigas de uma opção do DW (mesmo texto, salvo pontuação, espaços e caixa, como
        // as dos formulários iniciais) saem quando nenhuma resposta as usa.
        $doDw = [];

        foreach ($opcoesDw as $opcaoDw) {
            $doDw[self::textoComparavel($opcaoDw['valor'])] = true;
        }

        foreach ($atuais as $valor => $opcao) {
            if (isset($doDw[self::textoComparavel($valor)]) && !self::valorRespondido((int) $pergunta->id, $valor)) {
                unset($atuais[$valor]);
            }
        }

        // Alternativas criadas no construtor ou que saíram do DW, depois das do DW.
        $resultado = array_merge($resultado, array_values($atuais), $parametros);
        $opcoes = [];

        foreach ($resultado as $i => $opcao) {
            $opcoes['ds_opcoes' . $i] = $opcao;
        }

        return json_encode($opcoes, JSON_UNESCAPED_UNICODE);
    }

    private static function textoComparavel(string $texto): string
    {
        return rtrim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($texto))), " .;:,");
    }

    /** Alguma resposta da pergunta usa o valor (sozinho ou numa lista de checkbox). */
    private static function valorRespondido(int $perguntaId, string $valor): bool
    {
        $db = self::db();
        $condicoes = ['ds_resposta = ' . $db->quote($valor)];

        // Checkbox grava um array JSON; cobre as codificações possíveis do texto.
        foreach ([JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_UNICODE, 0] as $flags) {
            $condicoes[] = 'ds_resposta LIKE ' . $db->quote('%' . $db->escape(json_encode($valor, $flags), true) . '%');
        }

        return (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__tb_resposta'))
                ->where('co_pergunta = ' . $perguntaId)
                ->where('(' . implode(' OR ', array_unique($condicoes)) . ')'),
            0,
            1
        )->loadResult();
    }

    /** Valores do DW excluídos da pergunta no construtor. */
    public static function getRemovidos(object $pergunta): array
    {
        $removidos = json_decode(PerguntaHelper::getParametro($pergunta, self::PARAM_REMOVIDOS), true);

        return is_array($removidos) ? array_values(array_map('strval', $removidos)) : [];
    }
}
