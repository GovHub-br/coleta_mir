<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

/**
 * Perfis de acesso do Coleta MIR, pedidos pela própria pessoa e liberados por um servidor do MIR
 * (tabela #__coleta_solicitacao_acesso):
 *   - mir: servidor do MIR (ao ser liberado, entra no grupo do MIR);
 *   - gestor_ted: gestor de TED, por UG (unidade gestora no SIAFI);
 *   - convenente: gestor de convênio, por CNPJ do convenente.
 * O gestor liberado preenche o formulário do gestor de todos os instrumentos da sua UG/CNPJ
 * (tp_identificador/co_identificador do instrumento no espelho do DW). O executor de projeto
 * não pede perfil: o vínculo é o CPF na lista de projetos do gestor.
 *
 * @since  1.0.11
 */
class AcessoHelper
{
    public const PARAM_ACESSO_UNIDADE = 'acesso_unidade';

    /** Grupo do MIR (o mesmo do construtor e da gestão de formulários). */
    public const GRUPO_MIR = 10;

    /**
     * Grupo das contas criadas em "Solicitar acesso": tem permissão de criar e editar as próprias
     * respostas no componente (core.create e core.edit.own). Buscado pelo nome, que vale em todos os ambientes.
     */
    public const GRUPO_COLETA = 'Coleta MIR';

    public const PERFIS = [
        'mir'        => ['rotulo' => 'Servidor do MIR', 'identificador' => '', 'fonte' => ''],
        'gestor_ted' => ['rotulo' => 'Gestor de TED', 'identificador' => 'ug', 'fonte' => 'teds'],
        'convenente' => ['rotulo' => 'Convenente (gestor de convênio)', 'identificador' => 'cnpj', 'fonte' => 'convenios'],
    ];

    private const TABELA = '#__coleta_solicitacao_acesso';

    private static function db()
    {
        return Factory::getContainer()->get('DatabaseDriver');
    }

    /** Id do grupo GRUPO_COLETA, ou 0 se ainda não foi criado. */
    public static function getGrupoColetaId(): int
    {
        $db = self::db();

        return (int) $db->setQuery(
            $db->getQuery(true)->select('id')->from($db->quoteName('#__usergroups'))->where($db->quoteName('title') . ' = ' . $db->quote(self::GRUPO_COLETA))
        )->loadResult();
    }

    /** UG com 6 dígitos ou CNPJ com 14 dígitos válidos; vazio se inválido. */
    public static function normalizarIdentificador(string $tipo, string $valor): string
    {
        $digitos = preg_replace('/\D/', '', $valor);

        if ($tipo === 'ug') {
            return strlen($digitos) === 6 ? $digitos : '';
        }

        if ($tipo !== 'cnpj' || strlen($digitos) !== 14 || preg_match('/^(\d)\1{13}$/', $digitos)) {
            return '';
        }

        foreach ([12, 13] as $t) {
            $soma = 0;
            $pesos = $t === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $digitos[$i] * $pesos[$i];
            }
            $resto = $soma % 11;
            if ((int) $digitos[$t] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return '';
            }
        }

        return $digitos;
    }

    /**
     * Unidade (UG ou CNPJ) nos instrumentos do espelho do DW: ['nome' => parceiro, 'instrumentos' => n],
     * ou null quando nenhum instrumento a usa.
     */
    public static function getUnidade(string $tipo, string $codigo): ?array
    {
        $db = self::db();
        $linha = $db->setQuery(
            $db->getQuery(true)
                ->select('MAX(JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('dados') . ', ' . $db->quote('$.no_parceiro') . '))) AS nome, COUNT(*) AS instrumentos')
                ->from($db->quoteName('#__coleta_dw_registro'))
                ->where($db->quoteName('registro') . ' = ' . $db->quote('instrumento'))
                ->where('JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('dados') . ', ' . $db->quote('$.tp_identificador') . ')) = ' . $db->quote($tipo))
                ->where('JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('dados') . ', ' . $db->quote('$.co_identificador') . ')) = ' . $db->quote($codigo))
        )->loadAssoc();

        return $linha && (int) $linha['instrumentos'] > 0 ? ['nome' => (string) $linha['nome'], 'instrumentos' => (int) $linha['instrumentos']] : null;
    }

    /**
     * Registra a solicitação de um perfil. Devolve a mensagem de erro ou null.
     */
    public static function solicitar(int $userId, string $perfil, string $identificador, string $justificativa): ?string
    {
        $config = self::PERFIS[$perfil] ?? null;

        if ($config === null) {
            return 'Perfil inválido.';
        }

        $codigo = '';
        $unidade = null;

        if ($config['identificador'] !== '') {
            $codigo = self::normalizarIdentificador($config['identificador'], $identificador);

            if ($codigo === '') {
                return $config['identificador'] === 'ug' ? 'Informe a UG com 6 dígitos.' : 'Informe um CNPJ válido.';
            }

            $unidade = self::getUnidade($config['identificador'], $codigo);
        }

        $db = self::db();
        $repetida = $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName(self::TABELA))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('tp_perfil') . ' = ' . $db->quote($perfil))
                ->where('COALESCE(' . $db->quoteName('co_identificador') . ", '') = " . $db->quote($codigo))
                ->whereIn($db->quoteName('st_solicitacao'), ['pendente', 'aprovada'], \Joomla\Database\ParameterType::STRING)
        )->loadResult();

        if ($repetida) {
            return 'Você já tem uma solicitação pendente ou liberada para este perfil' . ($codigo !== '' ? ' e unidade' : '') . '.';
        }

        $registro = (object) [
            'user_id'          => $userId,
            'tp_perfil'        => $perfil,
            'tp_identificador' => $config['identificador'] ?: null,
            'co_identificador' => $codigo ?: null,
            'no_unidade'       => $unidade['nome'] ?? null,
            'ds_justificativa' => mb_substr(trim(strip_tags($justificativa)), 0, 2000),
            'st_solicitacao'   => 'pendente',
            'dt_solicitacao'   => Factory::getDate()->toSql(),
        ];
        $db->insertObject(self::TABELA, $registro, 'id');

        return null;
    }

    /** Solicitações de um usuário (mais recentes primeiro). */
    public static function getSolicitacoes(int $userId): array
    {
        $db = self::db();

        return $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName(self::TABELA))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->order($db->quoteName('id') . ' DESC')
        )->loadObjectList();
    }

    /** Solicitações para o MIR decidir: pendentes primeiro, com nome, CPF e e-mail de quem pediu. */
    public static function getParaDecisao(string $situacao = 'pendente'): array
    {
        $db = self::db();
        $query = $db->getQuery(true)
            ->select('s.*, u.name AS no_usuario, u.username AS cpf, u.email, d.name AS no_decisor')
            ->from($db->quoteName(self::TABELA, 's'))
            ->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = s.user_id')
            ->join('LEFT', $db->quoteName('#__users', 'd') . ' ON d.id = s.decidido_por')
            ->order('s.id DESC');

        if ($situacao !== '') {
            $query->where($db->quoteName('s.st_solicitacao') . ' = ' . $db->quote($situacao));
        }

        return $db->setQuery($query)->loadObjectList();
    }

    /** Aprova ou recusa uma solicitação pendente. Devolve a mensagem de erro ou null. */
    public static function decidir(int $id, bool $aprovar, string $motivo, int $decisorId): ?string
    {
        $db = self::db();
        $solicitacao = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName(self::TABELA))->where('id = ' . $id)
        )->loadObject();

        if (!$solicitacao || $solicitacao->st_solicitacao !== 'pendente') {
            return 'Solicitação não encontrada ou já decidida.';
        }

        if (!$aprovar && trim($motivo) === '') {
            return 'Informe o motivo da recusa.';
        }

        if ($aprovar && $solicitacao->tp_perfil === 'mir') {
            UserHelper::addUserToGroup((int) $solicitacao->user_id, self::GRUPO_MIR);
        }

        $solicitacao->st_solicitacao = $aprovar ? 'aprovada' : 'recusada';
        $solicitacao->ds_motivo = mb_substr(trim(strip_tags($motivo)), 0, 2000) ?: null;
        $solicitacao->dt_decisao = Factory::getDate()->toSql();
        $solicitacao->decidido_por = $decisorId;
        $db->updateObject(self::TABELA, $solicitacao, 'id', true);

        return null;
    }

    /** UGs/CNPJs liberados para o usuário, por perfil de gestor: ['gestor_ted' => [...], 'convenente' => [...]]. */
    public static function getUnidadesLiberadas(int $userId): array
    {
        $unidades = ['gestor_ted' => [], 'convenente' => []];

        foreach (self::getSolicitacoes($userId) as $solicitacao) {
            if ($solicitacao->st_solicitacao === 'aprovada' && isset($unidades[$solicitacao->tp_perfil]) && $solicitacao->co_identificador) {
                $unidades[$solicitacao->tp_perfil][$solicitacao->co_identificador] = $solicitacao->no_unidade ?: $solicitacao->co_identificador;
            }
        }

        return $unidades;
    }

    /**
     * Instrumentos do espelho do DW nas UGs/CNPJs liberados para o usuário: lista de
     * ['perfil', 'fonte', 'codigo'] (só TEDs e convênios).
     */
    public static function getInstrumentosDoGestor(int $userId): array
    {
        $db = self::db();
        $instrumentos = [];

        foreach (self::getUnidadesLiberadas($userId) as $perfil => $codigos) {
            if (!$codigos) {
                continue;
            }

            $config = self::PERFIS[$perfil];
            $tipo = DwHelper::tipoInstrumento($config['fonte']);
            $chaves = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('chave'))
                    ->from($db->quoteName('#__coleta_dw_registro'))
                    ->where($db->quoteName('registro') . ' = ' . $db->quote('instrumento'))
                    ->where($db->quoteName('chave') . ' LIKE ' . $db->quote($tipo . ':%'))
                    ->where('JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('dados') . ', ' . $db->quote('$.tp_identificador') . ')) = ' . $db->quote($config['identificador']))
                    ->whereIn('JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('dados') . ', ' . $db->quote('$.co_identificador') . '))', array_map('strval', array_keys($codigos)), \Joomla\Database\ParameterType::STRING)
                    ->order($db->quoteName('chave'))
            )->loadColumn();

            foreach ($chaves as $chave) {
                $instrumentos[] = ['perfil' => $perfil, 'fonte' => $config['fonte'], 'codigo' => substr($chave, strlen($tipo) + 1)];
            }
        }

        return $instrumentos;
    }

    /** O usuário é do MIR (grupo do MIR) ou super usuário. */
    public static function ehMir(User $user): bool
    {
        return !$user->guest && ($user->authorise('core.admin') || in_array(self::GRUPO_MIR, $user->getAuthorisedGroups(), true));
    }

    /** O usuário pode preencher o formulário do gestor deste instrumento (UG/CNPJ liberado, ou MIR). */
    public static function podeAcessarInstrumento(User $user, string $fonte, string $codigo): bool
    {
        if ($user->guest || $codigo === '') {
            return false;
        }

        if (self::ehMir($user)) {
            return true;
        }

        foreach (self::getInstrumentosDoGestor((int) $user->id) as $instrumento) {
            if ($instrumento['fonte'] === $fonte && $instrumento['codigo'] === $codigo) {
                return true;
            }
        }

        return false;
    }

    /** Pergunta do formulário que escolhe o instrumento pela UG/CNPJ liberada (parâmetro acesso_unidade), ou null. */
    public static function getPerguntaAcessoUnidade(array $perguntas): ?object
    {
        foreach ($perguntas as $pergunta) {
            if (PerguntaHelper::getParametro($pergunta, self::PARAM_ACESSO_UNIDADE) === 'sim') {
                return $pergunta;
            }
        }

        return null;
    }

    /**
     * Valores travados do formulário do gestor: o instrumento e a descrição dele. Resposta nova:
     * o instrumento vem do endereço (escolhido em "Meus instrumentos") e precisa estar numa UG/CNPJ
     * liberada; resposta salva: mantém o que foi gravado.
     *
     * @return  array  ['erro' => string|null, 'codigo' => string, 'valores' => [perguntaId => valor]]
     */
    public static function valoresDoInstrumento(array $perguntas, array $respostasSalvas, string $codigo, User $user): array
    {
        $pergunta = self::getPerguntaAcessoUnidade($perguntas);
        $resultado = ['erro' => null, 'codigo' => '', 'valores' => []];

        if ($pergunta === null) {
            return $resultado;
        }

        $descricao = null;

        foreach ($perguntas as $p) {
            if (PerguntaHelper::getParametro($p, DwHelper::PARAM_CAMPO) === 'instrumento.descricao') {
                $descricao = $p;
            }
        }

        if ($respostasSalvas) {
            foreach (array_filter([$pergunta, $descricao]) as $p) {
                $resultado['valores'][(int) $p->id] = (string) ($respostasSalvas[(int) $p->id]->ds_resposta ?? '');
            }

            return $resultado;
        }

        $fonte = PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_FONTE);
        $codigo = trim($codigo);

        if (!self::podeAcessarInstrumento($user, $fonte, $codigo)) {
            $resultado['erro'] = 'Este formulário é aberto pelo instrumento, em "Meus instrumentos", para quem tem a UG (TED) ou o CNPJ (convênio) do instrumento liberado. Peça o acesso em "Meus acessos".';

            return $resultado;
        }

        $resultado['codigo'] = $codigo;
        $resultado['valores'][(int) $pergunta->id] = $codigo;

        if ($descricao) {
            $resultado['valores'][(int) $descricao->id] = InstrumentoHelper::getDescricao(DwHelper::chaveInstrumento($fonte, $codigo));
        }

        return $resultado;
    }

    /** O usuário tem algum perfil liberado ou pedido (para orientar quem ainda não pediu). */
    public static function temPerfilOuPedido(User $user): bool
    {
        return self::ehMir($user) || (bool) self::getSolicitacoes((int) $user->id);
    }
}
