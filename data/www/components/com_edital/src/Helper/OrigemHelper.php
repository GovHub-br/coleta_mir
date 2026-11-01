<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Model\RespostaModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Formulários encadeados: um formulário de origem (ex.: gestor do TED, que lista os projetos)
 * e um formulário de execução (tb_edital.co_edital_adesao = origem), respondido por projeto.
 *
 * Cada projeto da lista de origem tem um link próprio para o formulário de execução
 * (origem_key = chave pública da resposta de origem, projeto = posição na lista; 0 = execução
 * pela própria unidade). As perguntas da execução com o parâmetro "origem" vêm preenchidas e
 * travadas a partir da resposta de origem:
 *   - "pergunta:<título>": resposta da pergunta de mesmo título no formulário de origem;
 *   - "projeto:<coluna>": coluna do projeto escolhido (ou o parâmetro "origem_propria" quando
 *     projeto = 0; sem ele, a pergunta fica livre).
 *
 * @since  1.0.10
 */
class OrigemHelper
{
    /** Parâmetro de ds_opcoes (execução): de onde vem o valor travado. */
    public const PARAM_ORIGEM = 'origem';

    /** Parâmetro de ds_opcoes (execução): valor para a execução pela própria unidade (projeto 0). */
    public const PARAM_ORIGEM_PROPRIA = 'origem_propria';

    /** Parâmetro de ds_opcoes (origem): marca a lista de itens com os projetos. */
    public const PARAM_PROJETOS = 'projetos_execucao';

    /**
     * Parâmetro de ds_opcoes (lista de projetos): JSON com as respostas da pergunta de que a lista
     * depende em que a própria unidade também executa (habilita o projeto 0).
     */
    public const PARAM_PROPRIA_QUANDO = 'propria_quando';

    public const PARAMETROS = [self::PARAM_ORIGEM, self::PARAM_ORIGEM_PROPRIA, self::PARAM_PROJETOS, self::PARAM_PROPRIA_QUANDO];

    /** Formulário de execução ligado a um formulário de origem (ou null). */
    public static function getEditalExecucao(int $editalOrigemId): ?object
    {
        if ($editalOrigemId <= 0) {
            return null;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__tb_edital'))
            ->where($db->quoteName('co_edital_adesao') . ' = ' . $editalOrigemId)
            ->where($db->quoteName('tp_edital') . ' = ' . $db->quote('formulario'))
            ->where($db->quoteName('state') . ' = 1')
            ->setLimit(1);
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /** O formulário é de execução (tem formulário de origem)? */
    public static function ehExecucao($edital): bool
    {
        return ($edital->tp_edital ?? '') === 'formulario' && (int) ($edital->co_edital_adesao ?? 0) > 0;
    }

    /**
     * Projetos de uma resposta de origem: [posição => ['rotulo' => ..., 'responsavel' => ..., 'email' => ..., 'itens' => colunas]].
     * A posição 0 (própria unidade) só aparece quando a resposta da pergunta de que a lista
     * depende está em propria_quando (ou a lista não tem esse parâmetro e não há projetos).
     */
    public static function getProjetos(int $adesaoOrigemId): array
    {
        $adesao = AdesaoHelper::getAdesaoById($adesaoOrigemId);

        if (!$adesao) {
            return [];
        }

        $perguntas = PerguntaHelper::getPerguntasByEdital((int) $adesao->co_edital, 'adesao');
        $respostas = (new RespostaModel())->getRespostasMapByAdesao($adesaoOrigemId);
        $lista = null;

        foreach ($perguntas as $pergunta) {
            if ($pergunta->tp_pergunta === 'tabela' && PerguntaHelper::getParametro($pergunta, self::PARAM_PROJETOS) !== '') {
                $lista = $pergunta;
                break;
            }
        }

        if ($lista === null) {
            return [];
        }

        $projetos = [];
        $propriaQuando = json_decode(PerguntaHelper::getParametro($lista, self::PARAM_PROPRIA_QUANDO), true);
        $respostaControle = (int) $lista->depende_de > 0 ? (string) ($respostas[(int) $lista->depende_de]->ds_resposta ?? '') : '';

        if (is_array($propriaQuando) && in_array($respostaControle, $propriaQuando, true)) {
            $projetos[0] = ['rotulo' => PerguntaHelper::getParametro($lista, self::PARAM_PROJETOS), 'responsavel' => '', 'email' => '', 'cpf' => '', 'itens' => []];
        }

        // Itens da lista só valem quando ela está visível (resposta de controle entre as que a exibem).
        $valoresDep = json_decode((string) ($lista->depende_de_valor ?? ''), true);
        $listaVisivel = (int) $lista->depende_de <= 0 || (is_array($valoresDep) && in_array($respostaControle, $valoresDep, true));

        if ($listaVisivel) {
            foreach (PerguntaHelper::normalizarTabela($lista, $respostas[(int) $lista->id]->ds_resposta ?? '') as $i => $item) {
                $projetos[$i + 1] = [
                    'rotulo'      => trim(implode(' - ', array_filter([$item['instituicao'] ?? '', $item['tema'] ?? '']))) ?: 'Projeto ' . ($i + 1),
                    'responsavel' => $item['responsavel'] ?? '',
                    'email'       => $item['email'] ?? '',
                    'cpf'         => preg_replace('/\D/', '', (string) ($item['cpf'] ?? '')),
                    'itens'       => $item,
                ];
            }
        }

        return $projetos;
    }

    /**
     * Valores travados da execução para um projeto da resposta de origem, por id de pergunta.
     *
     * @return  array  ['erro' => string|null, 'adesao' => object|null, 'valores' => [perguntaId => valor]]
     */
    public static function resolver($editalExecucao, string $origemKey, int $projeto): array
    {
        $erro = ['erro' => 'Link de execução inválido. Peça um novo link ao gestor do instrumento.', 'adesao' => null, 'valores' => []];
        $adesaoId = (int) AdesaoHelper::getAdesaoIdByPubKey($origemKey);
        $adesao = $adesaoId > 0 ? AdesaoHelper::getAdesaoById($adesaoId) : null;

        if (!$adesao || (int) $adesao->co_edital !== (int) ($editalExecucao->co_edital_adesao ?? 0) || $adesao->st_adesao === 'rascunho') {
            return $erro;
        }

        $projetos = self::getProjetos($adesaoId);

        if (!isset($projetos[$projeto])) {
            return $erro;
        }

        if (($bloqueio = self::bloqueioExecucao($adesao, $projetos[$projeto])) !== null) {
            return ['erro' => $bloqueio] + $erro;
        }

        $respostasOrigem = [];
        $respostasPorId = (new RespostaModel())->getRespostasMapByAdesao($adesaoId);

        foreach (PerguntaHelper::getPerguntasByEdital((int) $adesao->co_edital, 'adesao') as $pergunta) {
            $respostasOrigem[$pergunta->ds_titulo] = (string) ($respostasPorId[(int) $pergunta->id]->ds_resposta ?? '');
        }

        $valores = [];

        foreach (PerguntaHelper::getPerguntasByEdital((int) $editalExecucao->id, 'adesao') as $pergunta) {
            [$tipo, $campo] = array_pad(explode(':', PerguntaHelper::getParametro($pergunta, self::PARAM_ORIGEM), 2), 2, '');

            if ($tipo === 'pergunta') {
                $valores[(int) $pergunta->id] = $respostasOrigem[$campo] ?? '';
            } elseif ($tipo === 'projeto' && $projeto > 0) {
                $valores[(int) $pergunta->id] = (string) ($projetos[$projeto]['itens'][$campo] ?? '');
            } elseif ($tipo === 'projeto' && ($propria = PerguntaHelper::getParametro($pergunta, self::PARAM_ORIGEM_PROPRIA)) !== '') {
                $valores[(int) $pergunta->id] = $propria;
            }
        }

        return ['erro' => null, 'adesao' => $adesao, 'valores' => $valores];
    }

    /**
     * Valores travados de uma resposta de execução já salva: os que ela gravou nas perguntas com
     * o parâmetro "origem" (não mudam ao editar).
     */
    public static function getValoresSalvos(array $perguntas, array $respostasExistentes): array
    {
        $valores = [];

        foreach ($perguntas as $pergunta) {
            if (PerguntaHelper::getParametro($pergunta, self::PARAM_ORIGEM) !== '' && isset($respostasExistentes[(int) $pergunta->id])) {
                $valores[(int) $pergunta->id] = (string) $respostasExistentes[(int) $pergunta->id]->ds_resposta;
            }
        }

        return $valores;
    }

    /**
     * Projeto de uma resposta de execução, para identificá-la na listagem: respostas das
     * perguntas travadas a partir do projeto (origem "projeto:..."), ex.: "Universidade X - Formação".
     */
    public static function getRotuloProjeto(int $adesaoId, int $editalId): string
    {
        $partes = [];

        foreach (PerguntaHelper::getPerguntasByEdital($editalId, 'adesao') ?: [] as $pergunta) {
            if (strpos(PerguntaHelper::getParametro($pergunta, self::PARAM_ORIGEM), 'projeto:') !== 0) {
                continue;
            }

            $resposta = PerguntaHelper::getRespostaByAdesaoPergunta($adesaoId, (int) $pergunta->id);
            $valor = is_object($resposta) ? trim((string) $resposta->ds_resposta) : '';

            if ($valor !== '') {
                $partes[] = $valor;
            }
        }

        return implode(' - ', $partes);
    }

    /**
     * Só o responsável do projeto (CPF da lista de projetos = CPF do login) e o gestor que enviou a
     * lista preenchem a execução; a execução pela própria unidade (projeto 0) e projetos sem CPF,
     * só o gestor. Devolve a mensagem de bloqueio ou null.
     */
    public static function bloqueioExecucao(object $adesaoOrigem, array $projeto): ?string
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user->guest) {
            return 'Entre com o seu CPF para preencher a execução deste projeto.';
        }

        if ((int) $user->id === (int) $adesaoOrigem->created_by) {
            return null;
        }

        if ($projeto['cpf'] === '') {
            return 'A execução deste projeto é preenchida pelo gestor do instrumento.';
        }

        if ($projeto['cpf'] === self::cpfDoUsuario($user)) {
            return null;
        }

        return 'Este projeto está vinculado a outro CPF. A execução só pode ser preenchida pelo responsável do projeto ou pelo gestor do instrumento.';
    }

    /**
     * Projetos sob a responsabilidade de um CPF, em todas as respostas enviadas de formulários de
     * origem: lista de ['adesao' => resposta de origem, 'projeto' => posição, 'dados' => getProjetos].
     */
    public static function getProjetosDoCpf(string $cpf, int $editalOrigemId): array
    {
        if ($cpf === '' || $editalOrigemId <= 0) {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $origens = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'created_by']))
                ->from($db->quoteName('#__tb_adesao'))
                ->where($db->quoteName('co_edital') . ' = ' . $editalOrigemId)
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('st_adesao') . ' != ' . $db->quote('rascunho'))
                ->order($db->quoteName('id'))
        )->loadObjectList();

        $encontrados = [];

        foreach ($origens as $adesao) {
            foreach (self::getProjetos((int) $adesao->id) as $projeto => $dados) {
                if ($projeto > 0 && $dados['cpf'] === $cpf) {
                    $encontrados[] = ['adesao' => $adesao, 'projeto' => $projeto, 'dados' => $dados];
                }
            }
        }

        return $encontrados;
    }

    /** CPF (só dígitos) do usuário: o login do site é o CPF. */
    public static function cpfDoUsuario($user): string
    {
        return preg_replace('/\D/', '', (string) ($user->username ?? ''));
    }

    /** Link do formulário de execução para um projeto da resposta de origem. */
    public static function getLinkExecucao(int $editalExecucaoId, int $adesaoOrigemId, int $projeto): string
    {
        $link = 'index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . $editalExecucaoId
            . '&origem_key=' . rawurlencode(AdesaoHelper::getAdesaoPubKey($adesaoOrigemId))
            . '&projeto=' . $projeto;

        return Uri::getInstance()->toString(['scheme', 'host', 'port']) . Route::_($link, false);
    }
}
