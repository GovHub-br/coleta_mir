<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

/**
 * Instrumento (TED ou convênio) de uma resposta: a resposta da pergunta do formulário que
 * escolhe o instrumento (chave_dw "instrumento" com fonte_dw de instrumentos), que guarda só o
 * código (nr_instrumento). A listagem de formulários agrupa as respostas por instrumento.
 *
 * @since  1.0.10
 */
class InstrumentoHelper
{
    private const ROTULOS_TIPO = ['ted' => 'TED', 'convenio' => 'Convênio'];

    private static $perguntas = [];

    /** Pergunta que escolhe o instrumento no formulário, ou null. */
    public static function getPergunta(int $editalId): ?object
    {
        if (!array_key_exists($editalId, self::$perguntas)) {
            self::$perguntas[$editalId] = null;

            foreach (PerguntaHelper::getPerguntasByEdital($editalId, 'adesao') ?: [] as $pergunta) {
                if (PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_CHAVE) === 'instrumento'
                    && DwHelper::tipoInstrumento(PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_FONTE)) !== '') {
                    self::$perguntas[$editalId] = $pergunta;
                    break;
                }
            }
        }

        return self::$perguntas[$editalId];
    }

    /**
     * Instrumento respondido: ['chave' => 'ted:931295', 'tipo' => 'ted', 'rotuloTipo' => 'TED',
     * 'codigo' => '931295'], ou null quando o formulário não escolhe instrumento ou ainda não foi respondido.
     */
    public static function getDaResposta(int $adesaoId, int $editalId): ?array
    {
        $pergunta = self::getPergunta($editalId);

        if (!$pergunta) {
            return null;
        }

        $resposta = PerguntaHelper::getRespostaByAdesaoPergunta($adesaoId, (int) $pergunta->id);
        $codigo = is_object($resposta) ? trim((string) $resposta->ds_resposta) : '';

        return $codigo !== '' ? self::montar(PerguntaHelper::getParametro($pergunta, DwHelper::PARAM_FONTE), $codigo) : null;
    }

    /** Dados do instrumento a partir da fonte da pergunta e do código. */
    public static function montar(string $fonte, string $codigo): array
    {
        $tipo = DwHelper::tipoInstrumento($fonte);
        $chave = DwHelper::chaveInstrumento($fonte, $codigo);

        return [
            'chave'      => $chave,
            'tipo'       => $tipo,
            'rotuloTipo' => self::ROTULOS_TIPO[$tipo] ?? $tipo,
            'codigo'     => $codigo,
        ];
    }

    /** Descrição (parceiro - objeto) do instrumento no espelho do DW, ou vazio. */
    public static function getDescricao(string $chave): string
    {
        return (string) (EspelhoDwHelper::getRegistro('instrumento', $chave)['descricao'] ?? '');
    }
}
