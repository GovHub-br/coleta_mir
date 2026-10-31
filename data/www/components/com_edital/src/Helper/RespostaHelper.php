<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Comedital\Component\Edital\Site\Helper;

defined('_JEXEC') or die;

/**
 * Class RespostaHelper
 *
 * Renders the answered value of a resposta (read-only display), based on the
 * related pergunta's type. Mirrors the set of types handled by
 * PerguntaHelper::renderPerguntaField(), but outputs a display-only
 * representation instead of an editable form field.
 *
 * @since  1.0.0
 */
class RespostaHelper
{
    /**
     * Renders the display value of a resposta based on the related pergunta type.
     *
     * @param   object  $resposta  The resposta row, expected to contain
     *                             `pergunta_tipo`, `pergunta_opcoes` and `ds_resposta`
     *                             (as returned by AdesaorespostasModel::getRespostasByAdesao()).
     *
     * @return  string  The rendered HTML
     */
    public static function renderResposta($resposta)
    {
        $tipo  = $resposta->pergunta_tipo ?? '';
        $valor = $resposta->ds_resposta ?? '';

        switch ($tipo) {
            case 'lista':
            case 'radio':
                return self::renderOpcaoUnica($resposta, $valor);
            case 'checkbox':
                return self::renderOpcaoMultipla($resposta, $valor);
            case 'distribuicao_numerica':
                return self::renderDistribuicaoNumerica($resposta, $valor);
            case 'texto':
                return self::renderTextoMultilinha($valor);
            case 'arquivo':
                return self::renderArquivo($resposta);
            case 'informativo':
                return '';
            case 'linha':
            case 'cep':
            case 'telefone':
                return self::renderTelefone($valor);
            case 'telefones':
                return self::renderTelefones($valor);
            case 'cnpj':
            case 'email':
            case 'url':
            case 'numero':
            case 'uf':
                return self::renderTextoSimples($valor);
            case 'municipio':
                return self::renderMunicipio($valor);
            default:
                return self::renderTextoSimples($valor);
        }
    }

    /**
     * Normaliza as opções nome/valor de uma pergunta a partir de `pergunta_opcoes`.
     * Suporta o formato JSON (nome/valor) e o legado separado por vírgulas.
     *
     * @return  array
     */
    protected static function getOpcoes($resposta): array
    {
        $dsOpcoes = $resposta->pergunta_opcoes ?? '';

        if (empty($dsOpcoes)) {
            return [];
        }

        $decoded = json_decode($dsOpcoes);
        $options = [];

        if (is_array($decoded) || is_object($decoded)) {
            foreach ($decoded as $opcao) {
                if (!isset($opcao->nome) && !isset($opcao->valor)) {
                    continue;
                }

                if (PerguntaHelper::ehParametro($opcao)) {
                    continue;
                }

                $nome = trim((string) ($opcao->nome ?? $opcao->valor));
                $valor = trim((string) ($opcao->valor ?? $nome));

                if ($nome !== '' || $valor !== '') {
                    $options[] = ['nome' => $nome ?: $valor, 'valor' => $valor ?: $nome];
                }
            }

            return $options;
        }

        foreach (explode(',', $dsOpcoes) as $option) {
            $option = trim($option);
            if ($option !== '') {
                $options[] = ['nome' => $option, 'valor' => $option];
            }
        }

        return $options;
    }

    /**
     * Renders a plain single-line answer (linha, cep, telefone, cnpj, email, url, numero, uf).
     */
    protected static function renderTextoSimples($valor)
    {
        if ($valor === null || $valor === '') {
            return '<span class="text-muted">Não informado</span>';
        }

        return htmlspecialchars((string) $valor);
    }

    /** Exibe o co_ibge como Nome do Município - Sigla UF. */
    protected static function renderMunicipio($valor)
    {
        $rotulo = PerguntaHelper::getRotuloMunicipio($valor);

        if ($rotulo === '') {
            return self::renderTextoSimples($valor);
        }

        return htmlspecialchars($rotulo);
    }

    /** Renders one telephone value, preserving compatibility with legacy answers. */
    protected static function renderTelefone($valor)
    {
        return self::renderTextoSimples($valor);
    }

    /** Renders telephone values stored by the repeatable field as a JSON array. */
    protected static function renderTelefones($valor)
    {
        $telefones = json_decode((string) $valor, true);

        if (!is_array($telefones)) {
            return self::renderTextoSimples($valor);
        }

        $telefones = array_filter($telefones, 'is_scalar');
        if (empty($telefones)) {
            return '<span class="text-muted">Não informado</span>';
        }

        return implode('<br>', array_map(
            static fn ($telefone) => htmlspecialchars((string) $telefone),
            $telefones
        ));
    }

    /**
     * Renders a multi-line answer (texto/textarea), preserving line breaks.
     */
    protected static function renderTextoMultilinha($valor)
    {
        if ($valor === null || $valor === '') {
            return '<span class="text-muted">Não informado</span>';
        }

        return nl2br(htmlspecialchars((string) $valor));
    }

    /**
     * Renders a single-choice answer (lista/radio), resolving the option label from its value.
     */
    protected static function renderOpcaoUnica($resposta, $valor)
    {
        if ($valor === null || $valor === '') {
            return '<span class="text-muted">Não informado</span>';
        }

        $options = self::getOpcoes($resposta);

        foreach ($options as $option) {
            if ((string) $option['valor'] === (string) $valor) {
                return htmlspecialchars($option['nome']);
            }
        }

        return htmlspecialchars((string) $valor);
    }

    /**
     * Renders a multiple-choice answer (checkbox), stored as a JSON-encoded array of values.
     */
    protected static function renderOpcaoMultipla($resposta, $valor)
    {
        $selectedValues = [];

        if (is_array($valor)) {
            $selectedValues = array_map('strval', $valor);
        } elseif (is_string($valor) && $valor !== '') {
            $decoded = json_decode($valor, true);
            $selectedValues = is_array($decoded) ? array_map('strval', $decoded) : [];
        }

        $selectedValues = array_values(array_filter($selectedValues, static fn ($v) => $v !== ''));

        if (empty($selectedValues)) {
            return '<span class="text-muted">Não informado</span>';
        }

        $options = self::getOpcoes($resposta);
        $labels = [];

        foreach ($selectedValues as $value) {
            $label = $value;
            foreach ($options as $option) {
                if ((string) $option['valor'] === (string) $value) {
                    $label = $option['nome'];
                    break;
                }
            }
            $labels[] = $label;
        }

        $html = '<ul class="ar-resposta-lista mb-0 ps-3">';
        foreach ($labels as $label) {
            $html .= '<li>' . htmlspecialchars($label) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Renders a distribuicao_numerica answer, stored as a JSON object keyed by option alias.
     */
    protected static function renderDistribuicaoNumerica($resposta, $valor)
    {
        $respostaObj = is_string($valor) && $valor !== '' ? json_decode($valor) : null;

        if (empty($respostaObj)) {
            return '<span class="text-muted">Não informado</span>';
        }

        $options = self::getOpcoes($resposta);

        if (empty($options)) {
            return '<span class="text-muted">Não informado</span>';
        }

        $soma = 0;

        $html = '<ul class="ar-resposta-lista mb-0 ps-3">';
        foreach ($options as $option) {
            $chave = PerguntaHelper::getValueAlias($option['valor']);
            $valorNumerico = $respostaObj->$chave ?? null;

            if ($valorNumerico === null || $valorNumerico === '') {
                continue;
            }

            $soma += (float) $valorNumerico;
            $html .= '<li>' . htmlspecialchars($option['nome']) . ': <strong>' . htmlspecialchars($valorNumerico) . '</strong></li>';
        }
        $html .= '<li>Total: <strong>' . htmlspecialchars((string) $soma) . '</strong></li>';
        $html .= '</ul>';

        return $html;
    }

    /**
     * Renders an arquivo answer as a filename (used as a fallback outside of dedicated file-download UI).
     */
    protected static function renderArquivo($resposta)
    {
        $nomeArquivo = $resposta->arquivo_nome ?? (!empty($resposta->ds_resposta) ? basename($resposta->ds_resposta) : '');

        if (empty($nomeArquivo)) {
            return '<span class="text-muted">Nenhum arquivo enviado</span>';
        }

        return htmlspecialchars($nomeArquivo);
    }
}
