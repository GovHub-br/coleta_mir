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

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Comedital\Component\Edital\Site\Model\RespostaModel;
use \Comedital\Component\Edital\Site\Model\AdesaorespostasModel;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Session\Session;
use \Joomla\CMS\Uri\Uri;
use stdClass;

/**
 * Class PerguntaFrontendHelper
 *
 * @since  1.0.0
 */
class PerguntaHelper
{
    /** Entrada de ds_opcoes (nome/valor) que habilita a alternativa "Outro" em radio e checkbox. */
    public const OUTRO_PARAM = 'permite_outro';

    /** Valor enviado pelo radio/checkbox "Outro"; o texto livre vai em pergunta_outro[id]. */
    public const OUTRO_VALOR = '__outro__';

    public const OUTRO_PREFIXO = 'Outro: ';

    /** Origem virtual da condição: o tipo escolhido em Solicitante no formulário de adesão. */
    public const FONTE_SOLICITANTE = 'solicitante';

    /** Valores de tp_esfera_governamental aceitos na condição. */
    public const ESFERAS_SOLICITANTE = ['estadual', 'municipal', 'consorcio'];

    public static function opcoesSolicitante(): array
    {
        return [
            ['nome' => Text::_('COM_EDITAL_EDITAIS_TP_ESFERA_SOLICITANTE_OPTION_ESTADUAL'), 'valor' => 'estadual'],
            ['nome' => Text::_('COM_EDITAL_EDITAIS_TP_ESFERA_SOLICITANTE_OPTION_MUNICIPAL'), 'valor' => 'municipal'],
            ['nome' => Text::_('COM_EDITAL_EDITAIS_TP_ESFERA_SOLICITANTE_OPTION_CONSORCIO'), 'valor' => 'consorcio'],
        ];
    }

    public static function normalizarEsferaSolicitante($valor): string
    {
        $valor = is_scalar($valor) ? trim((string) $valor) : '';

        return in_array($valor, self::ESFERAS_SOLICITANTE, true) ? $valor : '';
    }

    public static function filtrarEsferasSolicitante($valor): array
    {
        return array_values(array_filter(
            self::valoresCondicao($valor),
            static fn ($item) => in_array($item, self::ESFERAS_SOLICITANTE, true)
        ));
    }

    public static function ehParametroOutro($opcao): bool
    {
        return is_object($opcao) && ($opcao->nome ?? null) === self::OUTRO_PARAM;
    }

    /** Entradas de ds_opcoes que configuram a pergunta e não são alternativas. */
    public static function ehParametro($opcao): bool
    {
        return self::ehParametroOutro($opcao)
            || (is_object($opcao) && in_array($opcao->nome ?? null, DwHelper::PARAMETROS, true));
    }

    /** Valor de um parâmetro de ds_opcoes (ex.: fonte_dw), ou '' se ausente. */
    public static function getParametro($pergunta, string $nome): string
    {
        $decoded = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes) : null;

        foreach ((is_array($decoded) || is_object($decoded)) ? $decoded : [] as $opcao) {
            if (is_object($opcao) && ($opcao->nome ?? null) === $nome) {
                return trim((string) ($opcao->valor ?? ''));
            }
        }

        return '';
    }

    /** Opções do DW da pergunta (fonte_dw), ou vazio para usar as opções fixas. */
    protected static function getOpcoesDw($pergunta): array
    {
        $fonte = self::getParametro($pergunta, DwHelper::PARAM_FONTE);

        return $fonte !== '' ? DwHelper::getOpcoes($fonte) : [];
    }

    protected static function atributoFiltro(array $opcao): string
    {
        return ($opcao['filtro'] ?? '') !== ''
            ? ' data-filtro="' . htmlspecialchars($opcao['filtro'], ENT_QUOTES, 'UTF-8') . '"'
            : '';
    }

    public static function permiteOutro($pergunta): bool
    {
        if (!in_array($pergunta->tp_pergunta ?? '', ['radio', 'checkbox'], true) || empty($pergunta->ds_opcoes)) {
            return false;
        }

        $decoded = json_decode($pergunta->ds_opcoes);

        if (!is_array($decoded) && !is_object($decoded)) {
            return false;
        }

        foreach ($decoded as $opcao) {
            if (self::ehParametroOutro($opcao) && ($opcao->valor ?? '') === 'sim') {
                return true;
            }
        }

        return false;
    }

    /** Opções de radio/checkbox sem as entradas de parâmetro; as do DW têm precedência. */
    protected static function getOpcoesComValor($pergunta): array
    {
        if ($opcoesDw = self::getOpcoesDw($pergunta)) {
            return array_map(static fn ($opcao) => (object) $opcao, $opcoesDw);
        }

        $options = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes) : [];

        return array_values(array_filter((array) $options, static fn ($opcao) => !self::ehParametro($opcao)));
    }

    protected static function renderOutroOpcao($pergunta, string $tipo, bool $marcado, string $texto, bool $obrigatorio): string
    {
        $fieldId = 'pergunta_' . (int) $pergunta->id . '_outro';
        $name = 'pergunta[' . (int) $pergunta->id . ']' . ($tipo === 'checkbox' ? '[]' : '');

        $html = '<div class="form-check">';
        $html .= '<input class="form-check-input pergunta-outro-toggle" type="' . $tipo . '"'
            . ' id="' . $fieldId . '" name="' . $name . '" value="' . self::OUTRO_VALOR . '"'
            . ($marcado ? ' checked' : '')
            . ($tipo === 'radio' && $obrigatorio ? ' required' : '') . ' />';
        $html .= '<label class="form-check-label" for="' . $fieldId . '">Outro</label></div>';
        $html .= '<input type="text" id="' . $fieldId . '_texto" name="pergunta_outro[' . (int) $pergunta->id . ']"'
            . ' class="form-control mt-1 pergunta-outro-texto' . ($marcado ? '' : ' d-none') . '"'
            . ' maxlength="255" placeholder="Especifique" aria-label="Especifique o outro"'
            . ' value="' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '"'
            . ($marcado ? ' required' : '') . ' />';

        return $html;
    }

    protected static function textoOutro(int $perguntaId, array $inputOutros): string
    {
        $texto = $inputOutros[$perguntaId] ?? '';

        return is_scalar($texto) ? mb_substr(trim((string) $texto), 0, 255) : '';
    }

    /**
     * Gets the files attached to an item
     *
     * @param   int     $pk     The item's id
     *
     * @param   string  $table  The table's name
     *
     * @param   string  $field  The field's name
     *
     * @return  array  The files
     */
    public static function getFiles($pk, $table, $field)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select($field)
            ->from($table)
            ->where('id = ' . (int) $pk);

        $db->setQuery($query);

        return explode(',', $db->loadResult());
    }
    /**
     * Gets perguntas by edital ID
     *
     * @param   int  $editalId  The edital ID
     * @param   int  $etapa     The etapa
     *
     * @return  mixed  The perguntas object list or null
     */
    public static function getPerguntasByEdital($editalId, $etapa = 'adesao')
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('p.*, 
            a.no_agrupamento AS agrupamento_titulo, 
            a.id AS agrupamento_id,
            a.no_titulo_tab AS agrupamento_no_titulo_tab,
            a.ds_classe_icone AS agrupamento_ds_classe_icone')
            ->from($db->quoteName('#__tb_pergunta', 'p'))
            ->leftJoin($db->quoteName('#__tb_agrupamento', 'a') . ' ON ' . $db->quoteName('p.co_agrupamento') . ' = ' . $db->quoteName('a.id'))
            ->where('p.co_edital = ' . (int) $editalId)
            ->where('p.tp_etapa = ' . $db->quote($etapa))
            ->where('p.state = 1')
            ->order('a.ordering asc, p.ordering ASC');

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Renders a pergunta field based on its type
     *
     * @param   object  $pergunta  The pergunta object
     * @param   mixed   $resposta  The current answer value
     * @param   int     $index     The question index (0 for informativo)
     *
     * @return  string  The rendered HTML
     */
    public static function renderPerguntaField($pergunta, $resposta = '', $index = 0)
    {
        $dsDependeAttr = self::getDependenciaAttr($pergunta);
        $html = '<div class="pergunta-field mb-4 mt-4"'
            . ' data-pergunta-id="' . (int) $pergunta->id . '"'
            . ' data-agrupamento-id="' . (int) ($pergunta->agrupamento_id ?? 0) . '"'
            . ' data-tipo-pergunta="' . htmlspecialchars($pergunta->tp_pergunta) . '"'
            . ' data-pergunta-chave="' . htmlspecialchars(self::getValueAlias($pergunta->ds_titulo)) . '"'
            . ' data-obrigatorio="' . ($pergunta->tp_requerida === 'sim' ? '1' : '0') . '"'
            . $dsDependeAttr . self::getIntegracaoDwAttr($pergunta) . '>';

        switch ($pergunta->tp_pergunta) {
            case 'linha':
                $html .= self::renderLinhaField($pergunta, $resposta, $index);
                break;
            case 'texto':
                $html .= self::renderTextoField($pergunta, $resposta, $index);
                break;
            case 'lista':
                $html .= self::renderListaField($pergunta, $resposta, $index);
                break;
            case 'arquivo':
                $html .= self::renderArquivoField($pergunta, $resposta, $index);
                break;
            case 'informativo':
                $html .= self::renderInformativoField($pergunta);
                break;
            case 'cep':
                $html .= self::renderCepField($pergunta, $resposta, $index);
                break;
            case 'telefone':
                $html .= self::renderTelefoneField($pergunta, $resposta, $index);
                break;
            case 'telefones':
                $html .= self::renderTelefonesField($pergunta, $resposta, $index);
                break;
            case 'cnpj':
                $html .= self::renderCnpjField($pergunta, $resposta, $index);
                break;
            case 'email':
                $html .= self::renderEmailField($pergunta, $resposta, $index);
                break;
            case 'url':
                $html .= self::renderUrlField($pergunta, $resposta, $index);
                break;
            case 'numero':
                $html .= self::renderNumeroField($pergunta, $resposta, $index);
                break;
            case 'radio':
                $html .= self::renderRadioField($pergunta, $resposta, $index);
                break;
            case 'checkbox':
                $html .= self::renderCheckboxField($pergunta, $resposta, $index);
                break;
            case 'distribuicao_numerica':
                $html .= self::renderDistribuicaoNumericaField($pergunta, $resposta, $index);
                break;
            case 'uf':
                $html .= self::renderUfField($pergunta, $resposta, $index);
                break;
            case 'municipio':
                $html .= self::renderMunicipioField($pergunta, $resposta, $index);
                break;
            default:
                $html .= '<p class="text-danger">Tipo de pergunta desconhecido: ' . htmlspecialchars($pergunta->tp_pergunta) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Texto de apoio exibido após o título e antes da resposta.
     * Usa a mesma marcação da descrição (form-text text-muted).
     *
     * @param   object  $pergunta  The pergunta object
     *
     * @return  string
     */
    protected static function renderInformacaoComplementar2($pergunta)
    {
        if (empty($pergunta->ds_informacao_complementar2)) {
            return '';
        }

        return '<small class="form-text text-muted d-block">' . $pergunta->ds_informacao_complementar2 . '</small>';
    }

    /**
     * Renders a linha (text input) field
     *
     * @param   object  $pergunta  The pergunta object
     * @param   string  $resposta  The current answer
     * @param   int     $index     The question index
     *
     * @return  string  The rendered HTML
     */
    protected static function renderLinhaField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio ? ' required' : '';
        $indexHtml = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $isIdentificacaoOrgao = (int) ($pergunta->agrupamento_id ?? 0) === 2;
        $titulo = trim($pergunta->ds_titulo);

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="pergunta_' . $pergunta->id . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        // Sugestões vindas do DW (sugestoes_dw): o adesao.js preenche o datalist.
        $listaSugestoes = self::getParametro($pergunta, DwHelper::PARAM_SUGESTOES) !== ''
            ? 'sugestoes_pergunta_' . (int) $pergunta->id
            : '';

        $html .= '<input type="text" '
        . 'id="pergunta_' . $pergunta->id . '" '
        . 'name="pergunta[' . $pergunta->id . ']" '
        . 'class="form-control" '
        . ($listaSugestoes ? 'list="' . $listaSugestoes . '" autocomplete="off" ' : '')
        . 'value="' . ($resposta) . '"'
        . $required . ' />';
        $html .= $listaSugestoes ? '<datalist id="' . $listaSugestoes . '"></datalist>' : '';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a texto (textarea) field
     *
     * @param   object  $pergunta  The pergunta object
     * @param   string  $resposta  The current answer
     * @param   int     $index     The question index
     *
     * @return  string  The rendered HTML
     */
    protected static function renderTextoField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio ? ' required' : '';
        $indexHtml = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $opcoes = json_decode($pergunta->ds_opcoes);
        $opcoesBase = [
            'rows' => 5,
            'placeholder' => '',
        ];

        if ($opcoes) {
            foreach ($opcoes as $opcao) {
                $opcoesBase[$opcao->nome] = $opcao->valor;
            }
        }

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="pergunta_' . $pergunta->id . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<textarea '
            . 'id="pergunta_' . $pergunta->id . '" '
            . 'name="pergunta[' . $pergunta->id . ']" '
            . 'class="form-control" '
            . 'rows="' . (int) $opcoesBase['rows'] . '" '
            . 'placeholder="' . htmlspecialchars($opcoesBase['placeholder']) . '" '
            . $required . '>'
            . htmlspecialchars($resposta)
            . '</textarea>'
            . (!empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' .($pergunta->ds_descricao) . '</small>' : '');
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Normaliza opções nome/valor e mantém suporte ao formato legado separado por vírgulas.
     * Com $incluirDw, as opções do DW (fonte_dw) substituem as fixas quando disponíveis;
     * o construtor de formulários passa false para editar só as alternativas fixas.
     */
    public static function getOpcoesSelecao($pergunta, bool $incluirDw = true): array
    {
        if ($incluirDw && ($opcoesDw = self::getOpcoesDw($pergunta))) {
            return $opcoesDw;
        }

        if (empty($pergunta->ds_opcoes)) {
            return [];
        }

        $decoded = json_decode($pergunta->ds_opcoes);
        $jsonValido = json_last_error() === JSON_ERROR_NONE;
        $options = [];

        if (is_array($decoded) || is_object($decoded)) {
            foreach ($decoded as $opcao) {
                if (!isset($opcao->nome) && !isset($opcao->valor)) {
                    continue;
                }

                if (self::ehParametro($opcao)) {
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

        if ($jsonValido || in_array(substr(ltrim($pergunta->ds_opcoes), 0, 1), ['{', '['], true)) {
            return [];
        }

        foreach (explode(',', $pergunta->ds_opcoes) as $option) {
            $option = trim($option);
            if ($option !== '') {
                $options[] = ['nome' => $option, 'valor' => $option];
            }
        }

        return $options;
    }

    /**
     * Renders a lista (select) field
     *
     * @param   object  $pergunta  The pergunta object
     * @param   string  $resposta  The current answer
     * @param   int     $index     The question index
     *
     * @return  string  The rendered HTML
     */
    protected static function renderListaField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio ? ' required' : '';
        $options = self::getOpcoesSelecao($pergunta);

        // Lista alimentada só pelo DW e sem opções (DW fora do ar): campo de texto para não travar o preenchimento.
        if (empty($options) && self::getParametro($pergunta, DwHelper::PARAM_FONTE) !== '') {
            return self::renderLinhaField($pergunta, htmlspecialchars((string) $resposta, ENT_QUOTES, 'UTF-8'), $index);
        }

        $indexHtml = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="pergunta_' . $pergunta->id . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        $html .= '<select '
            . 'id="pergunta_' . $pergunta->id . '" '
            . 'name="pergunta[' . $pergunta->id . ']" '
            . 'class="form-select"'
            . $required . '>';

        $html .= '<option value="">Selecione uma opção</option>';

        foreach ($options as $option) {
            $selected = ((string) $option['valor'] === (string) $resposta) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($option['valor']) . '"' . $selected . self::atributoFiltro($option) . '>'
                . htmlspecialchars($option['nome'])
                . '</option>';
        }

        $html .= '</select>';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . htmlspecialchars($pergunta->ds_descricao) . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders an arquivo (file upload) field
     *
     * @param   object  $pergunta  The pergunta object
     * @param   string  $resposta  The current file path
     * @param   int     $index     The question index
     *
     * @return  string  The rendered HTML
     */
    protected static function renderArquivoField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio && !$resposta ? ' required' : '';

        $opcoes = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes) : [];
        $opcoesMap = [];
        if (is_array($opcoes) || is_object($opcoes)) {
            foreach ($opcoes as $opcao) {
                if (isset($opcao->nome, $opcao->valor)) {
                    $opcoesMap[$opcao->nome] = $opcao->valor;
                }
            }
        }

        $extensoes = !empty($opcoesMap['extensao_permitida'])
            ? array_values(array_filter(array_map('trim', explode(';', $opcoesMap['extensao_permitida']))))
            : ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $tiposPermitidos = implode(',', array_map(static fn ($extensao) => '.' . ltrim($extensao, '.'), $extensoes));
        $formatosLabel = implode(', ', array_map('strtoupper', $extensoes));

        $indexHtml = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        if($opcoesMap['titulo-consorcio'] ?? false) {
          $dataTituloConsorcio = ' data-titulo-consorcio="' . htmlspecialchars($opcoesMap['titulo-consorcio']) . ($isObrigatorio ? ' <span class=&quot;text-danger&quot;>*</span>' : '') . '"';
        }
        $html .= '<label for="pergunta_' . $pergunta->id . '" class="form-label"' . ($dataTituloConsorcio ?? '') . '>'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        $html .= $pergunta->ds_subtitulo ? '<div class="mb-2"><small class="form-text text-muted">' . $pergunta->ds_subtitulo . '</small></div>' : '';

        // Create custom file upload area
        $html .= '<div class="file-upload-wrapper">';
        $html .= '<input type="file" '
            . 'id="pergunta_' . $pergunta->id . '" '
            . 'name="pergunta[' . $pergunta->id . ']" '
            . 'class="file-input-hidden"'
            . 'accept="' . $tiposPermitidos . '" '
            . 'data-pergunta-id="' . $pergunta->id . '"'
            . 'data-obrigatorio="' . ($isObrigatorio ? '1' : '0') . '"'
            . ($resposta ? ' data-tem-resposta-rascunho="Sim"' : '')
            . $required . ' />';

        $html .= '<div class="file-drop-area" data-file-input="pergunta_' . $pergunta->id . '">';
        $html .= '<div class="file-drop-icon"><i class="fas fa-upload"></i></div>';
        $html .= '<div class="file-drop-content">';
        $html .= '<div class="file-drop-message"><strong>Selecionar arquivo</strong>'
            . (!empty($formatosLabel) ? ' (' . htmlspecialchars($formatosLabel) . ')' : '') . '</div>';
        // $html .= '<p class="file-drop-hint">Formatos suportados: PDF, DOC, DOCX, JPG, PNG (Máx. 10MB)</p>';
        $html .= '<div class="file-selected-name" style="display:none;"></div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        if ($resposta) {
            $html .= '<div class="mt-2 file-current">'
                . '<p class="mb-1"><small>Arquivo atual:</small></p>'
                . '<a href="' . $resposta . '" '
                . 'class="btn btn-sm btn-outline-primary" '
                . 'target="_blank">'
                . '<i class="fas fa-download"></i> Download'
                . '</a>'
                . '';
            $html .= ''
                . ' <a href="' . $resposta . '&inline=1" '
                . 'class="btn btn-sm btn-outline-primary" '
                . 'target="_blank">'
                . '<i class="fas fa-eye"></i> Visualizar'
                . '</a>'
                . '</div>';
        }

        $html .= !empty($pergunta->ds_informacao_complementar) ? '<div class="form-text info d-flex align-items-center col-md-6">' . '<div class="me-2"><i class="fas fa-info-circle"></i></div> <div class="info-content">' . $pergunta->ds_informacao_complementar . '</div></div>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders an informativo (informational text) field
     *
     * @param   object  $pergunta  The pergunta object
     *
     * @return  string  The rendered HTML
     */
    protected static function renderInformativoField($pergunta)
    {
        $html = "";
        $opcoes = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes) : [];
        $opcoesMap = [];
        if (is_array($opcoes) || is_object($opcoes)) {
            foreach ($opcoes as $opcao) {
                if (isset($opcao->nome, $opcao->valor)) {
                    $opcoesMap[$opcao->nome] = $opcao->valor;
                }
            }
        }
        if (empty($opcoes)) {
            $html = '<div class="container-informativo" >';
            $html .=  $pergunta->ds_descricao;
            $html .= '</div>';
        } else {
            $classe = $opcoesMap['classe'] ?? $opcoesMap['class'] ?? 'info';
            $podeFechar = isset($opcoesMap['fechar']) && $opcoesMap['fechar'] === 'sim';
            $fechar = $podeFechar ? 'alert-dismissible' : '';

            // Define icon based on alert class
            $icone = '';
            switch ($classe) {
                case 'success':
                    $icone = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'warning':
                    $icone = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'danger':
                    $icone = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'info':
                default:
                    $icone = '<i class="fas fa-info-circle"></i>';
                    break;
            }

            $html  = '<div class="alert alert-' . $classe . ' ' . $fechar . ' d-flex align-items-center" role="alert">'
                . ($podeFechar ? '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>' : '')
                . '<div class="me-3">' . $icone . '</div>'
                . '<div>' . $pergunta->ds_descricao . '</div>'
                . '</div>';
        }

        return self::renderInformacaoComplementar2($pergunta) . $html;
    }


    /**
     * Renders a CEP field with mask 99999-999
     */
    protected static function renderCepField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="text" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control" maxlength="9" placeholder="00000-000"'
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';
        $html .= '<script>(function(){var el=document.getElementById("' . $fieldId . '");if(!el)return;'
            . 'el.addEventListener("input",function(e){'
            . 'var v=e.target.value.replace(/\D/g,"").substring(0,8);'
            . 'if(v.length>5)v=v.substring(0,5)+"-"+v.substring(5);'
            . 'e.target.value=v;});})();</script>';

        return $html;
    }

    /**
     * Renders a telefone field with mask (99) 99999-9999
     */
    protected static function renderTelefoneField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="tel" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control" maxlength="15" placeholder="(00) 00000-0000"'
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';
        $html .= '<script>(function(){var el=document.getElementById("' . $fieldId . '");if(!el)return;'
            . 'el.addEventListener("input",function(e){'
            . 'var v=e.target.value.replace(/\D/g,"").substring(0,11);'
            . 'if(v.length>10)v="("+v.substring(0,2)+") "+v.substring(2,7)+"-"+v.substring(7);'
            . 'else if(v.length>6)v="("+v.substring(0,2)+") "+v.substring(2,6)+"-"+v.substring(6);'
            . 'else if(v.length>2)v="("+v.substring(0,2)+") "+v.substring(2);'
            . 'else if(v.length>0)v="("+v;'
            . 'e.target.value=v;});})();</script>';

        return $html;
    }

    /**
     * Renders a repeatable telephone field. Values are stored as a JSON array.
     */
    protected static function renderTelefonesField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $fieldId = 'pergunta_' . $pergunta->id;
        $containerId = $fieldId . '_telefones';
        $telefones = json_decode((string) $resposta, true);

        if (!is_array($telefones)) {
            $telefones = trim((string) $resposta) !== '' ? [(string) $resposta] : [''];
        }

        $telefones = array_values(array_filter($telefones, 'is_scalar'));
        if (empty($telefones)) {
            $telefones = [''];
        }

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2"><div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<div id="' . $containerId . '" class="telefones-fields">';

        foreach ($telefones as $telefoneIndex => $telefone) {
            $inputId = $telefoneIndex === 0 ? $fieldId : $fieldId . '_' . $telefoneIndex;
            $required = $telefoneIndex === 0 && $isObrigatorio ? ' required' : '';
            $removeDisabled = count($telefones) === 1 ? ' disabled' : '';
            $html .= '<div class="input-group mb-2 telefone-field-row">';
            $html .= '<input type="tel" id="' . $inputId . '" name="pergunta[' . $pergunta->id . '][]"'
                . ' class="form-control telefone-field" maxlength="15" inputmode="tel" autocomplete="tel"'
                . ' placeholder="(00) 00000-0000" pattern="\\([0-9]{2}\\) [0-9]{4,5}-[0-9]{4}"'
                . ' value="' . htmlspecialchars((string) $telefone) . '"' . $required . ' />';
            $html .= '<button type="button" class="btn btn-outline-danger telefone-remove" aria-label="Remover telefone"' . $removeDisabled . '>-</button>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-outline-primary telefone-add" data-telefone-container="'
            . $containerId . '" data-telefone-name="pergunta[' . $pergunta->id . '][]">+</button>';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted d-block mt-1">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';
        $html .= '<script>(function(){var container=document.getElementById("' . $containerId . '");if(!container)return;'
            . 'function format(input){var v=input.value.replace(/\\D/g,"").substring(0,11);'
            . 'if(v.length>10)v="("+v.substring(0,2)+") "+v.substring(2,7)+"-"+v.substring(7);'
            . 'else if(v.length>6)v="("+v.substring(0,2)+") "+v.substring(2,6)+"-"+v.substring(6);'
            . 'else if(v.length>2)v="("+v.substring(0,2)+") "+v.substring(2);else if(v.length>0)v="("+v;input.value=v;}'
            . 'function syncRemoveButtons(){var disabled=container.querySelectorAll(".telefone-field-row").length===1;container.querySelectorAll(".telefone-remove").forEach(function(button){button.disabled=disabled;});}'
            . 'container.querySelectorAll(".telefone-field").forEach(format);'
            . 'container.addEventListener("input",function(e){if(e.target.classList.contains("telefone-field"))format(e.target);});'
            . 'var add=document.querySelector(".telefone-add[data-telefone-container=\"' . $containerId . '\"]");if(!add)return;add.addEventListener("click",function(){'
            . 'var row=document.createElement("div");row.className="input-group mb-2 telefone-field-row";'
            . 'var input=document.createElement("input");input.type="tel";input.name=this.dataset.telefoneName;input.className="form-control telefone-field";input.maxLength=15;input.inputMode="tel";input.autocomplete="tel";input.placeholder="(00) 00000-0000";input.pattern="\\\\([0-9]{2}\\\\) [0-9]{4,5}-[0-9]{4}";input.setAttribute("aria-label","Telefone adicional");'
            . 'var remove=document.createElement("button");remove.type="button";remove.className="btn btn-outline-danger telefone-remove";remove.setAttribute("aria-label","Remover telefone");remove.textContent="-";'
            . 'row.append(input,remove);container.appendChild(row);syncRemoveButtons();input.focus();});'
            . 'container.addEventListener("click",function(e){if(!e.target.classList.contains("telefone-remove"))return;var rows=container.querySelectorAll(".telefone-field-row");if(rows.length>1){e.target.closest(".telefone-field-row").remove();syncRemoveButtons();}});})();</script>';

        return $html;
    }

    /**
     * Renders a CNPJ field with mask 99.999.999/9999-99
     */
    protected static function renderCnpjField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml ;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="text" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control" maxlength="18" placeholder="00.000.000/0000-00"'
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';
        $html .= '<script>(function(){var el=document.getElementById("' . $fieldId . '");if(!el)return;'
            . 'el.addEventListener("input",function(e){'
            . 'var v=e.target.value.replace(/\D/g,"").substring(0,14);'
            . 'if(v.length>12)v=v.substring(0,2)+"."+v.substring(2,5)+"."+v.substring(5,8)+"/"+v.substring(8,12)+"-"+v.substring(12);'
            . 'else if(v.length>8)v=v.substring(0,2)+"."+v.substring(2,5)+"."+v.substring(5,8)+"/"+v.substring(8);'
            . 'else if(v.length>5)v=v.substring(0,2)+"."+v.substring(2,5)+"."+v.substring(5);'
            . 'else if(v.length>2)v=v.substring(0,2)+"."+v.substring(2);'
            . 'e.target.value=v;});})();</script>';

        return $html;
    }

    /**
     * Renders an e-mail field (native HTML5 validation)
     */
    protected static function renderEmailField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="email" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control" placeholder="exemplo@email.com"'
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a URL field (native HTML5 validation)
     */
    protected static function renderUrlField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="url" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control" placeholder="https://"'
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a numeric input. Supports optional min/max/step from ds_opcoes JSON.
     */
    protected static function renderNumeroField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required      = $isObrigatorio ? ' required' : '';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $fieldId       = 'pergunta_' . $pergunta->id;

        $opcoes    = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes, true) : [];
        $opcoesMap = [];
        if (is_array($opcoes)) {
            foreach ($opcoes as $item) {
                if (isset($item['nome'], $item['valor'])) {
                    $opcoesMap[$item['nome']] = $item['valor'];
                }
            }
        }
        $min  = isset($opcoesMap['min'])  ? ' min="'  . htmlspecialchars($opcoesMap['min'])  . '"' : '';
        $max  = isset($opcoesMap['max'])  ? ' max="'  . htmlspecialchars($opcoesMap['max'])  . '"' : '';
        $step = isset($opcoesMap['step']) ? ' step="' . htmlspecialchars($opcoesMap['step']) . '"' : ' step="1"';

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="' . $fieldId . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<input type="number" id="' . $fieldId . '" name="pergunta[' . $pergunta->id . ']"'
            . ' class="form-control"' . $min . $max . $step
            . ' value="' . htmlspecialchars($resposta) . '"' . $required . ' />';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a radio button group. Options sourced from ds_opcoes JSON (nome/valor pairs).
     */
    protected static function renderRadioField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $indexHtml     = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $options       = self::getOpcoesComValor($pergunta);
        $permiteOutro  = self::permiteOutro($pergunta);
        $outroMarcado  = false;
        $outroTexto    = '';

        if ($permiteOutro && is_string($resposta) && strncmp($resposta, self::OUTRO_PREFIXO, strlen(self::OUTRO_PREFIXO)) === 0) {
            $valoresOpcoes = array_map(static fn ($o) => (string) ($o->valor ?? $o->nome ?? ''), $options);

            if (!in_array($resposta, $valoresOpcoes, true)) {
                $outroMarcado = true;
                // A resposta de radio é gravada já escapada.
                $outroTexto   = htmlspecialchars_decode(substr($resposta, strlen(self::OUTRO_PREFIXO)), ENT_QUOTES);
            }
        }

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml ;
        $html .= '<div class="flex-grow-1">';
        $html .= '<p class="form-label mb-2">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</p>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        if (!empty($options)) {
            $html .= '<div class="pergunta-options pergunta-options-radio">';
            foreach ($options as $opcao) {
                $label   = isset($opcao->nome)  ? $opcao->nome  : (isset($opcao->valor) ? $opcao->valor : '');
                $value   = isset($opcao->valor) ? $opcao->valor : $label;
                $checked = ((string) $resposta === (string) $value) ? ' checked' : '';
                $fieldId = 'pergunta_' . $pergunta->id . '_' . md5($value);
                $html .= '<div class="form-check"' . self::atributoFiltro((array) $opcao) . '>';
                $html .= '<input class="form-check-input" type="radio"'
                    . ' id="' . $fieldId . '"'
                    . ' name="pergunta[' . $pergunta->id . ']"'
                    . ' value="' . htmlspecialchars($value) . '"'
                    . $checked . ($isObrigatorio ? ' required' : '') . ' />';
                $html .= '<label class="form-check-label" for="' . $fieldId . '">'
                    . htmlspecialchars($label) . '</label></div>';
            }
            if ($permiteOutro) {
                $html .= self::renderOutroOpcao($pergunta, 'radio', $outroMarcado, $outroTexto, $isObrigatorio);
            }
            $html .= '</div>';
        } else {
            $html .= '<p class="text-warning">Nenhuma opção configurada para esta pergunta.</p>';
        }

        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted d-block mt-1">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a checkbox group. Options sourced from ds_opcoes JSON (nome/valor pairs).
     * Answer stored as JSON-encoded array of selected values.
     */
    protected static function renderCheckboxField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio  = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $indexHtml      = $index > 0 ? '<div class="pergunta-index"><span>' . $index . '</span></div>' : '';
        $options        = self::getOpcoesComValor($pergunta);
        $permiteOutro   = self::permiteOutro($pergunta);
        $selectedValues = [];
        if (is_array($resposta)) {
            $selectedValues = array_map('strval', $resposta);
        } elseif (is_string($resposta) && !empty($resposta)) {
            $decoded = json_decode($resposta, true);
            $selectedValues = is_array($decoded) ? array_map('strval', $decoded) : [];
        }

        $outroMarcado = false;
        $outroTexto   = '';

        if ($permiteOutro) {
            $valoresOpcoes = array_map(static fn ($o) => (string) ($o->valor ?? $o->nome ?? ''), $options);

            foreach ($selectedValues as $selecionado) {
                if (strncmp($selecionado, self::OUTRO_PREFIXO, strlen(self::OUTRO_PREFIXO)) === 0 && !in_array($selecionado, $valoresOpcoes, true)) {
                    $outroMarcado = true;
                    $outroTexto   = substr($selecionado, strlen(self::OUTRO_PREFIXO));
                    break;
                }
            }
        }

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        // $html .= $indexHtml;
        $html .= '<div class="flex-grow-1">';
        $html .= '<p class="form-label mb-2">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</p>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        if (!empty($options)) {
            $html .= '<div class="pergunta-options pergunta-options-checkbox">';
            foreach ($options as $opcao) {
                $label   = isset($opcao->nome)  ? $opcao->nome  : (isset($opcao->valor) ? $opcao->valor : '');
                $value   = isset($opcao->valor) ? $opcao->valor : $label;
                $checked = in_array((string) $value, $selectedValues) ? ' checked' : '';
                $fieldId = 'pergunta_' . $pergunta->id . '_' . md5($value);
                $html .= '<div class="form-check"' . self::atributoFiltro((array) $opcao) . '>';
                $html .= '<input class="form-check-input" type="checkbox"'
                    . ' id="' . $fieldId . '"'
                    . ' name="pergunta[' . $pergunta->id . '][]"'
                    . ' value="' . htmlspecialchars($value) . '"'
                    . $checked . ' />';
                $html .= '<label class="form-check-label" for="' . $fieldId . '">'
                    . htmlspecialchars($label) . '</label></div>';
            }
            if ($permiteOutro) {
                $html .= self::renderOutroOpcao($pergunta, 'checkbox', $outroMarcado, $outroTexto, $isObrigatorio);
            }
            $html .= '</div>';
            // Hidden sentinel so the key is always present in $_POST even when nothing is checked
            $html .= '<input type="hidden" name="pergunta[' . $pergunta->id . '][]" value="" />';
        } else {
            $html .= '<p class="text-warning">Nenhuma opção configurada para esta pergunta.</p>';
        }

        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted d-block mt-1">' . $pergunta->ds_descricao . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Renders a UF (state) select field
     *
     * @param   object  $pergunta  The pergunta object
     * @param   string  $resposta  The current answer
     * @param   int     $index     The question index
     *
     * @return  string  The rendered HTML
     */
    protected static function renderUfField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio ? ' required' : '';
        $ufs = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
            'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
            'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];

        $html  = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        $html .= '<div class="flex-grow-1">';
        $html .= '<label for="pergunta_' . $pergunta->id . '" class="form-label">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</label>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<select id="pergunta_' . $pergunta->id . '" '
            . 'name="pergunta[' . $pergunta->id . ']" class="form-select"' . $required . '>'
            . '<option value="">UF</option>';
        foreach ($ufs as $uf) {
            $selected = $resposta === $uf ? ' selected' : '';
            $html .= '<option value="' . $uf . '"' . $selected . '>' . $uf . '</option>';
        }
        $html .= '</select>';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . htmlspecialchars($pergunta->ds_descricao) . '</small>' : '';
        $html .= '</div></div>';

        return $html;
    }

    /** Estado e depois município. A resposta gravada é o co_ibge. */
    protected static function renderMunicipioField($pergunta, $resposta, $index = 0)
    {
        static $buscaCarregada = false;

        if (!$buscaCarregada) {
            Text::script('JGLOBAL_SELECT_NO_RESULTS_MATCH');
            Text::script('JGLOBAL_SELECT_PRESS_TO_SELECT');
            Factory::getApplication()->getDocument()->getWebAssetManager()
                ->usePreset('choicesjs')
                ->useScript('webcomponent.field-fancy-select');
            $buscaCarregada = true;
        }

        $isObrigatorio = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $required = $isObrigatorio ? ' required' : '';
        $fieldId = 'pergunta_' . (int) $pergunta->id;
        $estadoId = $fieldId . '_estado';
        $coIbge = (int) $resposta;
        $municipioSelecionado = $coIbge > 0 ? self::getMunicipioPorIbge($coIbge) : null;
        $sgEstado = $municipioSelecionado->sg_estado ?? '';
        $estados = AdesaoHelper::getSgEstadoOptions() ?: [];

        $html = '<div class="pergunta-content d-flex gap-3 pt-2 pb-2">';
        $html .= '<div class="flex-grow-1">';
        $html .= '<p class="form-label mb-2">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '')
            . '</p>';
        $html .= self::renderInformacaoComplementar2($pergunta);
        $html .= '<div class="row g-2">';

        $html .= '<div class="col-md-4">';
        $html .= '<label for="' . $estadoId . '" class="form-label">Estado</label>';
        $html .= '<joomla-field-fancy-select style="display:block" search-placeholder="Buscar" placeholder="Selecione o estado">';
        $html .= '<select id="' . $estadoId . '" class="form-select" data-max-results="50"' . $required . '>';
        $html .= '<option value="">Selecione o estado</option>';
        foreach ($estados as $estado) {
            $selected = $sgEstado === $estado->value ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($estado->value) . '"' . $selected . '>'
                . htmlspecialchars($estado->text)
                . '</option>';
        }
        $html .= '</select></joomla-field-fancy-select></div>';

        $html .= '<div class="col-md-8">';
        $html .= '<label for="' . $fieldId . '" class="form-label">Município</label>';
        $html .= '<joomla-field-fancy-select style="display:block" search-placeholder="Buscar" placeholder="Selecione o município">';
        $html .= '<select id="' . $fieldId . '" name="pergunta[' . (int) $pergunta->id . ']" class="form-select" data-max-results="50"' . $required . '>';
        if ($municipioSelecionado) {
            $html .= '<option value="">Selecione o município</option>';
            $html .= '<option value="' . (int) $municipioSelecionado->co_ibge . '" selected>'
                . htmlspecialchars(self::formatarMunicipio($municipioSelecionado->no_municipio, $municipioSelecionado->sg_estado))
                . '</option>';
        } else {
            $html .= '<option value="">Selecione o estado primeiro</option>';
        }
        $html .= '</select></joomla-field-fancy-select></div>';
        $html .= '</div>';
        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted">' . htmlspecialchars($pergunta->ds_descricao) . '</small>' : '';
        $html .= '</div></div>';

        $ajaxUrl = json_encode(Uri::base() . 'index.php');
        $token = json_encode(Session::getFormToken());
        $html .= '<script>(function(){'
            . 'var estado=document.getElementById(' . json_encode($estadoId) . ');'
            . 'var municipio=document.getElementById(' . json_encode($fieldId) . ');'
            . 'if(!estado||!municipio)return;'
            . 'if(!window.semScrollMunicipio){window.semScrollMunicipio=true;var scrollNativo=Element.prototype.scrollIntoView;'
            . 'Element.prototype.scrollIntoView=function(){if(this.closest&&this.closest("joomla-field-fancy-select.sem-scroll-pagina")){var lista=this.closest(".choices__list");if(lista)lista.scrollTop=this.offsetTop;return;}return scrollNativo.apply(this,arguments);};}'
            . 'function travarScroll(select){var fancy=select.closest("joomla-field-fancy-select");if(!fancy||!fancy.choicesInstance||fancy.dataset.scrollTravado)return;fancy.dataset.scrollTravado="1";fancy.classList.add("sem-scroll-pagina");var inst=fancy.choicesInstance;[inst.containerOuter.element,inst.input.element].forEach(function(el){el.focus=function(){HTMLElement.prototype.focus.call(el,{preventScroll:true});};});}'
            . 'function prepararScroll(){travarScroll(estado);travarScroll(municipio);}'
            . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",prepararScroll);else prepararScroll();'
            . 'window.addEventListener("load",prepararScroll);'
            . 'function aplicar(select,opcoes,manter){'
            . 'var fancy=select.closest("joomla-field-fancy-select");'
            . 'if(fancy&&fancy.choicesInstance){'
            . 'fancy.choicesInstance.setChoices(opcoes,"value","label",true,true,true);'
            . 'if(manter)fancy.choicesInstance.setChoiceByValue(String(manter));'
            . 'return;}'
            . 'select.innerHTML="";'
            . 'opcoes.forEach(function(item){var opt=document.createElement("option");opt.value=item.value;opt.textContent=item.label;if(manter&&item.value===String(manter)){opt.selected=true;opt.setAttribute("selected","");}select.appendChild(opt);});'
            . '}'
            . 'function limpar(select){var fancy=select.closest("joomla-field-fancy-select");if(fancy&&fancy.choicesInstance)fancy.choicesInstance.removeActiveItems();select.value="";}'
            . 'var restaurar=municipio.value;'
            . 'var estadoInicial=estado.value;'
            . 'var pedido=0;'
            . 'estado.addEventListener("change",function(){'
            . 'var sg=estado.value;'
            . 'var manter=sg&&sg===estadoInicial?(municipio.value||restaurar):"";'
            . 'var atual=++pedido;'
            . 'if(!sg){aplicar(municipio,[{value:"",label:"Selecione o estado primeiro"}]);return;}'
            . 'if(!manter)limpar(municipio);'
            . 'var params=new URLSearchParams({option:"com_edital",task:"adesaoform.getMunicipios",co_estado:sg});'
            . 'params.append(' . $token . ',"1");'
            . 'fetch(' . $ajaxUrl . '+"?"+params.toString(),{credentials:"same-origin",headers:{"X-Requested-With":"XMLHttpRequest"}})'
            . '.then(function(r){return r.json();})'
            . '.then(function(data){'
            . 'if(atual!==pedido)return;'
            . 'if(!data||!data.success||!Array.isArray(data.data)){aplicar(municipio,[{value:"",label:"Erro ao carregar municípios"}]);return;}'
            . 'var opcoes=[{value:"",label:"Selecione o município"}];'
            . 'var encontrado="";'
            . 'data.data.forEach(function(item){var valor=String(item.co_ibge);if(manter&&valor===String(manter))encontrado=valor;opcoes.push({value:valor,label:item.no_municipio+" - "+sg});});'
            . 'aplicar(municipio,opcoes,encontrado);'
            . '})'
            . '.catch(function(){if(atual!==pedido)return;aplicar(municipio,[{value:"",label:"Erro ao carregar municípios"}]);});'
            . '});'
            . 'if(estado.value){estado.dispatchEvent(new Event("change"));}'
            . '})();</script>';

        return $html;
    }

    public static function formatarMunicipio($nome, $sigla)
    {
        return trim((string) $nome) . ' - ' . trim((string) $sigla);
    }

    public static function getRotuloMunicipio($coIbge)
    {
        $municipio = self::getMunicipioPorIbge((int) $coIbge);

        if (!$municipio) {
            return '';
        }

        return self::formatarMunicipio($municipio->no_municipio, $municipio->sg_estado);
    }

    public static function getMunicipioPorIbge($coIbge)
    {
        $coIbge = (int) $coIbge;

        if ($coIbge <= 0) {
            return null;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('m.co_ibge'),
                $db->quoteName('m.no_municipio'),
                $db->quoteName('e.sg_estado'),
            ])
            ->from($db->quoteName('#__tb_municipio', 'm'))
            ->join(
                'INNER',
                $db->quoteName('#__tb_estado', 'e')
                . ' ON ' . $db->quoteName('e.co_estado') . ' = ' . $db->quoteName('m.co_estado')
            )
            ->where($db->quoteName('m.co_ibge') . ' = ' . $coIbge);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Renders a checkbox group. Options sourced from ds_opcoes JSON (nome/valor pairs).
     * Answer stored as JSON-encoded array of selected values.
     */
    protected static function renderDistribuicaoNumericaField($pergunta, $resposta, $index = 0)
    {
        $isObrigatorio  = !empty($pergunta->tp_requerida) && $pergunta->tp_requerida == 'sim';
        $options        = !empty($pergunta->ds_opcoes) ? json_decode($pergunta->ds_opcoes) : [];
        $respostaObj = json_decode($resposta);        
        $html  = '';
        $html .= '<p class="form-label mb-2">'
            . htmlspecialchars($pergunta->ds_titulo)
            . ($isObrigatorio ? ' <span class="text-danger">*</span>' : '') . '</p>';
        $html .= self::renderInformacaoComplementar2($pergunta);

        $html .= '<div class="row">';
        if (!empty($options)) {
            foreach ($options as $opcao) {
                $label   = isset($opcao->nome)  ? $opcao->nome  : (isset($opcao->valor) ? $opcao->valor : '');
                $value   = isset($opcao->valor) ? $opcao->valor : $label;
                $value = self::getValueAlias($value);

                $fieldId = 'pergunta_' . $pergunta->id . '_' . md5($value);
                $html .= '<div class="col-sm-6 col-md-4 mb-2">';
                $html .= '<label class="form-check-label" for="' . $fieldId . '">'
                    . htmlspecialchars($label) . '</label>';
                $html .= '<input class="form-control" type="number"'
                    . ' id="' . $fieldId . '"'
                    . ' name="pergunta[' . $pergunta->id . '][' . $value . ']" ';
                if($respostaObj && isset($respostaObj->$value)) {
                    $html .= ' value="' . htmlspecialchars($respostaObj->$value) . '"';
                }
                if($isObrigatorio) {
                    $html .= ' required';
                }
                $html .= ' />';
                $html .= '</div>';
            }
        } else {
            $html .= '<p class="text-warning">Nenhuma opção configurada para esta pergunta.</p>';
        }
        $html .= '</div>';

        $html .= !empty($pergunta->ds_descricao) ? '<small class="form-text text-muted d-block mt-1">' . $pergunta->ds_descricao . '</small>' : '';
      

        return $html;
    }

    public static function saveResposta($adesaoId, $perguntaId, $resposta, $db = null)
    {
        try {
            // Use the provided database connection or get a new one
            if ($db === null) {
                $db = Factory::getContainer()->get('DatabaseDriver');
            }

            $model = new RespostaModel();
            // Set the database connection to ensure same transaction
            if (method_exists($model, 'setDbo')) {
                $model->setDbo($db);
            }
            $pergunta = PerguntaHelper::getPerguntasByID($perguntaId);
            if (!$pergunta) {
                throw new \Exception('Pergunta não encontrada.');
            }

            if ($pergunta->tp_pergunta === 'arquivo') {
                $resposta = PerguntaHelper::trataUploadArquivo($adesaoId, $pergunta, $resposta);
                $dsResposta = $resposta['ds_resposta'];
            } else {
                $dsResposta = $resposta;
            }

            // Check if resposta already exists
            $existingResposta = $model->getRespostaByAdesaoPergunta($adesaoId, $perguntaId);

            // Get the table instance
            $table = $model->getTable();

            // Set the database connection for the table to use the same transaction
            $table->setDbo($db);

            if ($existingResposta) {
                // Load existing resposta for update
                $table->load($existingResposta->id);
                $table->ds_resposta = $dsResposta;
            } else {
                // Create new resposta
                $table->co_adesao = $adesaoId;
                $table->co_pergunta = $perguntaId;
                $table->ds_resposta = $dsResposta;
                $table->state = 1;
                $table->dt_criacao = Factory::getDate()->toSql();
                $table->st_situacao = 'pendente';
            }

            // Save the resposta using the same database connection
            if (!$table->store()) {
                throw new \Exception($table->getError());
            }

            return true;
        } catch (\Exception $e) {
            //log joomla error
            \Joomla\CMS\Log\Log::add(
                "Erro ao salvar respostas para adesão ID: " . $adesaoId . " e pergunta ID: " . $perguntaId . ". " . $e->getMessage(),
                \Joomla\CMS\Log\Log::ERROR,
                'system'
            );
            // Don't do rollback here, let the controller handle it
            return false;
        }
    }

    public static function getPerguntasByID($idPergunta)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
            ->select('*')
            ->from($db->quoteName('#__tb_pergunta'))
            ->where('id = ' . (int) $idPergunta);

        $db->setQuery($query);

        return $db->loadObject();
    }

    public static function trataUploadArquivo($adesaoId, $pergunta, $resposta)
    {
        $uploadDir = (JPATH_SITE . '/../uploads/edital/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadDir = realpath(JPATH_SITE . '/../uploads/edital/');

        $uploadDir = $uploadDir . '/' . $pergunta->co_edital . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $originalFilename = $resposta['name'];
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $newFilename = 'adesao_' . $adesaoId . '_pergunta_' . $pergunta->id . '_' . time() . '.' . $fileExtension;
        $destination = $uploadDir . $newFilename;

        if (move_uploaded_file($resposta['tmp_name'], $destination)) {
            // Save the relative path to the resposta
            $resposta['ds_resposta'] = $destination;
        } else {
            throw new \Exception('Erro ao mover o arquivo enviado.');
        }

        return $resposta;
    }
    /**
     * Resolve dependencies recursively using the answers from the current request.
     */
    public static function resolverPerguntasAtivas(
        array $perguntas,
        array $inputPerguntas,
        array &$errosDependencias = [],
        string $esferaSolicitante = ''
    ): array
    {
        $perguntasPorId = [];
        $valoresPorId = [];
        $estado = [];
        $ativas = [];

        foreach ($perguntas as $pergunta) {
            $perguntasPorId[(int) $pergunta->id] = $pergunta;
        }

        foreach ($inputPerguntas as $perguntaId => $valor) {
            $valoresPorId[(int) $perguntaId] = self::normalizarValoresResposta($valor);
        }

        $esferaAtual = $esferaSolicitante !== '' ? [$esferaSolicitante] : [];

        $resolver = function (int $perguntaId) use (
            &$resolver,
            &$estado,
            &$ativas,
            &$errosDependencias,
            $perguntasPorId,
            $valoresPorId,
            $esferaAtual
        ): bool {
            if (($estado[$perguntaId] ?? 0) === 2) {
                return $ativas[$perguntaId];
            }

            if (($estado[$perguntaId] ?? 0) === 1) {
                $errosDependencias[] = 'Dependência circular envolvendo a pergunta ID ' . $perguntaId . '.';
                return false;
            }

            if (!isset($perguntasPorId[$perguntaId])) {
                $errosDependencias[] = 'A pergunta dependente ID ' . $perguntaId . ' não foi encontrada.';
                return false;
            }

            $estado[$perguntaId] = 1;
            $pergunta = $perguntasPorId[$perguntaId];
            $exibirSolicitante = (string) ($pergunta->depende_de_fonte ?? '') === self::FONTE_SOLICITANTE;
            $ocultarSolicitante = (string) ($pergunta->depende_de_ocultar_fonte ?? '') === self::FONTE_SOLICITANTE;
            $exibirDe = (int) ($pergunta->depende_de ?? 0);
            $exibirValores = $exibirSolicitante
                ? self::filtrarEsferasSolicitante($pergunta->depende_de_valor ?? '')
                : self::valoresCondicao($pergunta->depende_de_valor ?? '');
            $ocultarDe = (int) ($pergunta->depende_de_ocultar ?? 0);
            $ocultarValores = $ocultarSolicitante
                ? self::filtrarEsferasSolicitante($pergunta->depende_de_ocultar_valor ?? '')
                : self::valoresCondicao($pergunta->depende_de_ocultar_valor ?? '');
            $ativa = true;

            if ($exibirSolicitante && $exibirValores !== []) {
                $ativa = self::condicaoAtendida($exibirValores, $esferaAtual);
            } elseif ($exibirDe > 0 && $exibirValores !== []) {
                if (!isset($perguntasPorId[$exibirDe])) {
                    $errosDependencias[] = 'A pergunta ID ' . $perguntaId
                        . ' depende da pergunta inexistente ID ' . $exibirDe . '.';
                    $ativa = false;
                } else {
                    $ativa = $resolver($exibirDe)
                        && self::condicaoAtendida($exibirValores, $valoresPorId[$exibirDe] ?? []);
                }
            }

            if ($ativa && $ocultarSolicitante && $ocultarValores !== []) {
                if (self::condicaoAtendida($ocultarValores, $esferaAtual)) {
                    $ativa = false;
                }
            } elseif ($ativa && $ocultarDe > 0 && $ocultarValores !== []) {
                if (!isset($perguntasPorId[$ocultarDe])) {
                    $errosDependencias[] = 'A pergunta ID ' . $perguntaId
                        . ' oculta com base na pergunta inexistente ID ' . $ocultarDe . '.';
                    $ativa = false;
                } elseif ($resolver($ocultarDe) && self::condicaoAtendida($ocultarValores, $valoresPorId[$ocultarDe] ?? [])) {
                    $ativa = false;
                }
            }

            $estado[$perguntaId] = 2;
            $ativas[$perguntaId] = $ativa;

            return $ativa;
        };

        foreach (array_keys($perguntasPorId) as $perguntaId) {
            $resolver($perguntaId);
        }

        return $ativas;
    }

    protected static function normalizarValoresResposta($valor): array
    {
        $valores = is_array($valor) ? $valor : [$valor];
        $normalizados = [];

        foreach ($valores as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);
            if ($item !== '') {
                $normalizados[] = $item;
            }
        }

        return $normalizados;
    }

    /**
     * Validates answers considering only active conditional questions.
     */
    public static function validarRespostasPerguntas(
        $resPerguntas,
        $inputPerguntas,
        $inputFiles,
        $rascunho = false,
        array $respostasExistentes = [],
        ?array $inputOutros = null,
        ?string $esferaSolicitante = null
    ) {
        $inputOutros ??= Factory::getApplication()->input->get('pergunta_outro', [], 'array');
        $esferaSolicitante ??= (string) Factory::getApplication()->input->getString('tp_esfera_governamental', '');
        $errors = [];
        $respostas = [];
        $errosDependencias = [];
        $perguntasAtivas = self::resolverPerguntasAtivas(
            $resPerguntas,
            $inputPerguntas,
            $errosDependencias,
            self::normalizarEsferaSolicitante($esferaSolicitante)
        );
        $errors = array_merge($errors, $errosDependencias);

        foreach ($resPerguntas as $pergunta) {
            $perguntaId = (int) $pergunta->id;

            if (!($perguntasAtivas[$perguntaId] ?? false)) {
                continue;
            }

            // Informational blocks have no answer and must never be validated as fields.
            if ($pergunta->tp_pergunta === 'informativo') {
                continue;
            }

            $preenchida = false;

            if ($pergunta->tp_pergunta === 'arquivo') {
                $arquivo = is_array($inputFiles) && isset($inputFiles[$perguntaId])
                    ? $inputFiles[$perguntaId]
                    : null;
                $uploadError = is_array($arquivo) ? ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

                if ($uploadError === UPLOAD_ERR_OK) {
                    try {
                        self::validaOpcoesArquivo($pergunta, $arquivo, json_decode($pergunta->ds_opcoes ?: '[]'));
                        $respostas[$perguntaId] = $arquivo;
                        $preenchida = true;
                    } catch (\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Falha no envio do arquivo para a pergunta: ' . $pergunta->ds_titulo;
                } else {
                    $existente = $respostasExistentes[$perguntaId] ?? null;
                    $caminhoExistente = is_object($existente) ? ($existente->ds_resposta ?? '') : '';
                    $preenchida = $caminhoExistente !== '' && is_file($caminhoExistente);
                }
            } elseif ($pergunta->tp_pergunta === 'distribuicao_numerica') {
                $resp = isset($inputPerguntas[$perguntaId]) && is_array($inputPerguntas[$perguntaId])
                    ? $inputPerguntas[$perguntaId]
                    : [];
                $resp = array_map(static fn ($valor) => is_scalar($valor) ? trim((string) $valor) : '', $resp);
                $chavesEsperadas = array_map(
                    static fn ($opcao) => self::getValueAlias($opcao->valor ?? $opcao->nome ?? ''),
                    (array) json_decode($pergunta->ds_opcoes ?: '[]')
                );
                $chavesEsperadas = array_values(array_unique(array_filter($chavesEsperadas)));
                $preenchida = !empty($chavesEsperadas);
                foreach ($chavesEsperadas as $chave) {
                    $preenchida = $preenchida
                        && array_key_exists($chave, $resp)
                        && $resp[$chave] !== '';
                }
                $respostas[$perguntaId] = json_encode($resp, JSON_UNESCAPED_UNICODE);
            } elseif ($pergunta->tp_pergunta === 'radio') {
                $valorRecebido = $inputPerguntas[$perguntaId] ?? '';
                $resp = is_scalar($valorRecebido) ? trim((string) $valorRecebido) : '';

                if ($resp === self::OUTRO_VALOR) {
                    $texto = self::textoOutro($perguntaId, $inputOutros);

                    if (!self::permiteOutro($pergunta)) {
                        $resp = '';
                    } elseif ($texto !== '') {
                        $resp = self::OUTRO_PREFIXO . $texto;
                    } elseif (!$rascunho) {
                        $errors[] = 'Informe o texto da opção "Outro" para a pergunta: ' . $pergunta->ds_titulo;
                        continue;
                    } else {
                        $resp = '';
                    }
                }

                $preenchida = $resp !== '';
                $respostas[$perguntaId] = htmlspecialchars($resp, ENT_QUOTES, 'UTF-8');
            } elseif ($pergunta->tp_pergunta === 'checkbox') {
                $resp = self::normalizarValoresResposta($inputPerguntas[$perguntaId] ?? []);

                if (in_array(self::OUTRO_VALOR, $resp, true)) {
                    $resp = array_values(array_diff($resp, [self::OUTRO_VALOR]));

                    if (self::permiteOutro($pergunta)) {
                        $texto = self::textoOutro($perguntaId, $inputOutros);

                        if ($texto !== '') {
                            $resp[] = self::OUTRO_PREFIXO . $texto;
                        } elseif (!$rascunho) {
                            $errors[] = 'Informe o texto da opção "Outro" para a pergunta: ' . $pergunta->ds_titulo;
                            continue;
                        }
                    }
                }

                $preenchida = !empty($resp);
                $respostas[$perguntaId] = $preenchida ? json_encode($resp, JSON_UNESCAPED_UNICODE) : '';
            } elseif ($pergunta->tp_pergunta === 'telefones') {
                $resp = self::normalizarValoresResposta($inputPerguntas[$perguntaId] ?? []);
                $telefonesFormatados = [];
                $telefoneInvalido = false;

                foreach ($resp as $telefone) {
                    $digitos = preg_replace('/\D/', '', $telefone);
                    if (!preg_match('/^\d{10,11}$/', $digitos)) {
                        $errors[] = 'Telefone inválido para a pergunta: ' . $pergunta->ds_titulo;
                        $telefoneInvalido = true;
                        break;
                    }

                    $telefonesFormatados[] = strlen($digitos) === 11
                        ? '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7)
                        : '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 4) . '-' . substr($digitos, 6);
                }

                if ($telefoneInvalido) {
                    continue;
                }

                $preenchida = !empty($telefonesFormatados);
                $respostas[$perguntaId] = $preenchida ? json_encode($telefonesFormatados, JSON_UNESCAPED_UNICODE) : '';
            } elseif ($pergunta->tp_pergunta === 'municipio') {
                $valorRecebido = $inputPerguntas[$perguntaId] ?? '';
                $resp = is_scalar($valorRecebido) ? trim((string) $valorRecebido) : '';

                if ($resp !== '' && !preg_match('/^\d+$/', $resp)) {
                    $errors[] = 'Município inválido para a pergunta: ' . $pergunta->ds_titulo;
                    continue;
                }

                if ($resp !== '' && self::getMunicipioPorIbge((int) $resp) === null) {
                    $errors[] = 'Município inválido para a pergunta: ' . $pergunta->ds_titulo;
                    continue;
                }

                $preenchida = $resp !== '';
                $respostas[$perguntaId] = $resp;
            } else {
                $valorRecebido = $inputPerguntas[$perguntaId] ?? '';
                $resp = is_scalar($valorRecebido) ? trim((string) $valorRecebido) : '';
                $preenchida = $resp !== '';
                $respostas[$perguntaId] = htmlspecialchars($resp, ENT_QUOTES, 'UTF-8');
            }

            if (!$rascunho && !$preenchida && $pergunta->tp_requerida === 'sim') {
                $errors[] = 'Resposta obrigatória para a pergunta: ' . $pergunta->ds_titulo;
            }
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'respostas' => $respostas,
        ];
    }

	public static function validaOpcoesArquivo($pergunta, $arquivo, $opcoes)
	{
        $opcoesPadrao = array(
                            "tamanho_maximo" => 10, // em MB
                            "extensao_permitida" => "pdf;doc;docx;jpg;jpeg;png",
                            "mime_permitido" => "application/pdf;application/msword;application/vnd.openxmlformats-officedocument.wordprocessingml.document;image/jpeg;image/png"
                        );
		$opcoesMap = array();
		foreach ($opcoes as $key => $value) {
			$opcoesMap[$value->nome] = $value->valor;
		}

        $opcoesMap = array_merge($opcoesPadrao, $opcoesMap);

		// Verifica tamanho máximo
		if (isset($opcoesMap['tamanho_maximo']) && $opcoesMap['tamanho_maximo'] > 0) {
			$tamanhoMaximoBytes = $opcoesMap['tamanho_maximo'] * 1024 * 1024;
			if ($arquivo['size'] > $tamanhoMaximoBytes) {
				throw new \Exception('O arquivo para a pergunta "' . $pergunta->ds_titulo . '" excede o tamanho máximo permitido de ' . $opcoesMap['tamanho_maximo'] . ' MB.');
			}
		}

		// Verifica tipos de arquivo permitidos
		if (isset($opcoesMap['extensao_permitida']) && !empty($opcoesMap['extensao_permitida'])) {
			$tiposPermitidos = array_map('trim', explode(';', $opcoesMap['extensao_permitida']));
			$extensaoArquivo = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

			if (!in_array($extensaoArquivo, $tiposPermitidos)) {
				throw new \Exception('O tipo de arquivo para a pergunta "' . $pergunta->ds_titulo . '" não é permitido. Tipos permitidos: ' . implode(', ', $tiposPermitidos) . '.');
			}
		}

		if (isset($opcoesMap['mime_permitido']) && !empty($opcoesMap['mime_permitido'])) {
			$mimesPermitidos = array_map('trim', explode(';', $opcoesMap['mime_permitido']));
			$tipoMimeArquivo = mime_content_type($arquivo['tmp_name']);

			if (!in_array($tipoMimeArquivo, $mimesPermitidos)) {
				throw new \Exception('O tipo MIME do arquivo para a pergunta "' . $pergunta->ds_titulo . '" não é permitido. MIMEs permitidos: ' . implode(', ', $mimesPermitidos) . '.');
			}
		}
	}

    public static function getRespostaByAdesaoPergunta($adesaoId, $perguntaId)
    {
        $AdesaoRespostaModel = new AdesaorespostasModel();
        $respostas = $AdesaoRespostaModel->getRespostasByAdesao($adesaoId);


        foreach ($respostas as $resposta) {
            if ($resposta->co_pergunta == $perguntaId) {

                return $resposta;
            }
        }

        return false;
    }

    public static function valoresCondicao($valor): array
    {
        if (is_array($valor)) {
            $itens = $valor;
        } else {
            $texto = trim((string) $valor);

            if ($texto === '') {
                return [];
            }

            if ($texto[0] === '[') {
                $decoded = json_decode($texto, true);
                $itens = is_array($decoded) ? $decoded : [$texto];
            } else {
                $itens = [$texto];
            }
        }

        $valores = [];

        foreach ($itens as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $item = trim((string) $item);

            if ($item !== '') {
                $valores[] = $item;
            }
        }

        return $valores;
    }

    public static function condicaoAtendida(array $esperados, array $atuais): bool
    {
        foreach ($esperados as $esperado) {
            if (in_array($esperado, $atuais, true)) {
                return true;
            }
        }

        return false;
    }

    /** Atributos lidos pelo adesao.js para filtrar opções e pré-preencher a partir do DW. */
    public static function getIntegracaoDwAttr($pergunta): string
    {
        $atributos = '';

        foreach (array_combine(DwHelper::PARAMETROS, ['data-fonte-dw', 'data-chave-dw', 'data-campo-dw', 'data-sugestoes-dw', 'data-filtro-dw']) as $parametro => $atributo) {
            $valor = self::getParametro($pergunta, $parametro);

            if ($valor !== '') {
                $atributos .= ' ' . $atributo . '="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return $atributos;
    }

    public static function getDependenciaAttr($pergunta){
        return self::atributoCondicao(
            'depende-de',
            self::origemCondicao($pergunta, 'depende_de', 'depende_de_fonte'),
            $pergunta->depende_de_valor ?? ''
        ) . self::atributoCondicao(
            'depende-ocultar',
            self::origemCondicao($pergunta, 'depende_de_ocultar', 'depende_de_ocultar_fonte'),
            $pergunta->depende_de_ocultar_valor ?? ''
        );
    }

    protected static function origemCondicao($pergunta, string $colunaId, string $colunaFonte): string
    {
        if ((string) ($pergunta->$colunaFonte ?? '') === self::FONTE_SOLICITANTE) {
            return self::FONTE_SOLICITANTE;
        }

        $perguntaId = (int) ($pergunta->$colunaId ?? 0);

        return $perguntaId > 0 ? (string) $perguntaId : '';
    }

    protected static function atributoCondicao(string $nome, $origem, $valor): string
    {
        $origem = trim((string) $origem);
        $valores = $origem === self::FONTE_SOLICITANTE
            ? self::filtrarEsferasSolicitante($valor)
            : self::valoresCondicao($valor);

        if ($origem === '' || $origem === '0' || $valores === []) {
            return '';
        }

        $json = json_encode($valores, JSON_UNESCAPED_UNICODE);

        return ' data-' . $nome . '="' . htmlspecialchars($origem, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-' . $nome . '-valor="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"';
    }

    public static function getValueAlias($titulo){
		$chave = str_replace(' ', '-', mb_strtolower($titulo));
		//remove acentos e caracteres especiais
		$chave = preg_replace('/[áàãâä]/u', 'a', $chave);
		$chave = preg_replace('/[éèêë]/u', 'e', $chave);
		$chave = preg_replace('/[íìîï]/u', 'i', $chave);
		$chave = preg_replace('/[óòõôö]/u', 'o', $chave);
		$chave = preg_replace('/[úùûü]/u', 'u', $chave);
		$chave = preg_replace('/[ç]/u', 'c', $chave);
		$chave = preg_replace('/[^a-z0-9\-]/', '', $chave);
		return $chave;
	}
}
