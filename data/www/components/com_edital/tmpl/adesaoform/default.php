<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use \Joomla\CMS\Plugin\PluginHelper;

$app = Factory::getApplication();
$isPreview = $this->isPreviewMode();
$requestedMode = $app->input->getCmd('modo', $this->params->get('modo', ''));

// O edital 2 é a nova versão do formulário. Sem um modo explícito, exibe a página de entrada.
if (!$isPreview && $requestedMode !== 'iniciar' && (int) ($this->edital->id ?? 0) === 2) {
    require __DIR__ . '/entrada.php';
    return;
}

if (!$isPreview) {
    $wa = $this->document->getWebAssetManager();
    $wa->useScript('keepalive')
            ->useScript('form.validate');
    HTMLHelper::_('bootstrap.tooltip');
}

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaoform.css');

if ($isPreview) {
    $this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaopreview.css');
    $this->document->addScript(Uri::root() . 'media/com_edital/js/adesaopreview.js');
}

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_edital', JPATH_SITE);

// Get CSRF token for AJAX requests
$token = \Joomla\CMS\Session\Session::getFormToken();
$config = Factory::getConfig();
$captchaPlugin = $config->get('captcha', '0');

// Load the interactive form JavaScript only in filling mode.
if (!$isPreview) {
    // Versão = data do arquivo: o navegador não fica com um adesao.js antigo em cache depois de uma atualização.
    $this->document->addScript(Uri::root() . 'media/com_edital/js/adesao.js?v=' . (int) @filemtime(JPATH_ROOT . '/media/com_edital/js/adesao.js'));
}

$activeMenu = $app->getMenu()->getActive();
$itemIdQuery = $activeMenu ? '&Itemid=' . (int) $activeMenu->id : '';
$urlEntrada = Route::_('index.php?option=com_edital&view=adesaoform&layout=default' . $itemIdQuery);
$urlIniciar = Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar' . $itemIdQuery);

// Localidade vinda do link de acesso enviado ao ente: os campos ficam travados.
$prefill = $this->prefill;
$travado = !empty($prefill);
$esferaPrefill = $travado ? $prefill['esfera'] : '';

$mensagemErroCnpjPadrao = 'CNPJ já possui adesão cadastrada. Protocolo: {protocolo}. Caso o preenchimento do formulário de adesão já tenha sido iniciado, {login} com o CPF e a senha cadastrados para continuar o preenchimento. Em caso de dúvidas, entre em contato pelo e-mail <a href="mailto:sinapir@igualdaderacial.gov.br">sinapir@igualdaderacial.gov.br</a>.';
$mensagemErroCnpj = trim((string) $this->params->get('mensagem_erro_cnpj', ''));
$mensagemErroCnpjTexto = trim(html_entity_decode(strip_tags($mensagemErroCnpj), ENT_QUOTES, 'UTF-8'));

if ($mensagemErroCnpjTexto === '') {
    $mensagemErroCnpj = $mensagemErroCnpjPadrao;
}

$labelNomeOficial       = 'Nome Oficial do Solicitante';
$placeholderNomeOficial = 'Ex.: Governo do Estado...';
$emailsMunicipiosSalvos = [];
$exibirEmailsMunicipios = $travado && $esferaPrefill === 'consorcio' && !empty($prefill['municipios']);

if (!empty($this->municipios ?? [])) {
    foreach ($this->municipios as $municipioSalvo) {
        $emailsMunicipiosSalvos[(int) $municipioSalvo->co_ibge] = (string) ($municipioSalvo->ds_email ?? '');
    }
}

if ($travado) {
    if ($esferaPrefill === 'municipal') {
        $labelNomeOficial       = 'Nome do município';
        $placeholderNomeOficial = 'Ex: Prefeitura Municipal de São Paulo';
    } elseif ($esferaPrefill === 'consorcio') {
        $labelNomeOficial       = 'Nome dos Consórcios/Associações';
        $placeholderNomeOficial = 'Informe o nome oficial (Consórcios/Associações)';
    } else {
        $labelNomeOficial       = 'Nome Estado';
        $placeholderNomeOficial = 'Ex: Governo do Estado de São Paulo';
    }
}
?>

<?php if (!$isPreview) : ?>
    <script>
        // Pass PHP variables to JavaScript
        window.adesaoConfig = {
            baseUrl: '<?php echo Uri::base(); ?>',
            token: '<?php echo $token; ?>',
            hasCaptcha: <?php echo ($captchaPlugin && $captchaPlugin !== '0') ? 'true' : 'false'; ?>,
            prefillTravado: <?php echo $travado ? 'true' : 'false'; ?>,
            mensagemErroCnpj: <?php echo json_encode($mensagemErroCnpj, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        };

        // Municípios já salvos (retomada de rascunho), usados para recriar as tags no carregamento
        window.municipiosSalvos = <?php echo json_encode(!empty($this->municipios) ? $this->municipios : []); ?>;

        jQuery(document).ready(function ($) {
            // Initialize the adesao form JavaScript
            AdesaoForm.init();
        });
    </script>
<?php endif; ?>

<?php if ($this->tituloNoTemplate) : ?>
    <?php // Título e breadcrumb vêm do template (item de menu "Meus Formulários") ?>
<?php elseif ($this->params->get('show_page_heading')) : ?>
    <div class="page-header">
        <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
    </div>

<?php else : ?>
    <div class="page-header">
        <h1> <?php echo $this->escape($this->edital->no_edital); ?> </h1>
    </div>

<?php endif; ?>

<?php if ($isPreview) : ?>
    <div class="adesao-preview-heading" role="note">
        <div>
            <span class="adesao-preview-badge">Somente visualização</span>
            <h2>Conheça todos os campos da adesão</h2>
            <p>Use o menu para navegar pelas etapas. Nenhuma informação será salva nesta página.</p>
        </div>
        <div class="adesao-preview-actions">
            <a href="<?php echo $urlEntrada; ?>" class="btn btn-outline-primary">Voltar</a>
            <a href="<?php echo $urlIniciar; ?>" class="btn btn-primary">Iniciar adesão</a>
        </div>
    </div>
<?php endif; ?>

<?php if (!$isPreview && isset($this->avisoMinhaAdesao)): ?>
    <div class="alert alert-warning" role="alert">
        <?php echo $this->avisoMinhaAdesao; ?>
    </div>
<?php else : ?>
    <?php if (!empty($this->edital->ds_edital)): ?>
        <div class="edital-descricao-edital">
            <p><?php echo $this->edital->ds_edital; ?></p>
        </div>
    <?php endif; ?>

<div class="tp-<?php echo $this->edital->tp_edital; ?> edital-adesaoform<?php echo $this->params->get('pageclass_sfx'); ?><?php echo $isPreview ? ' is-preview' : ''; ?>">
    <form id="form-adesao"
          action="<?php echo $isPreview ? '' : Route::_('index.php?option=com_edital&task=adesaoform.saveComRespostas'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">
        <?php if (!$isPreview) : ?>
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" id="co_edital" name="co_edital" value="<?php echo isset($this->edital->id) ? $this->edital->id : ''; ?>"/>
        <input type="hidden" id="id_usuario_logado" name="id_usuario_logado" value="<?php echo isset($this->user->id) ? $this->user->id : ''; ?>"/>
        <input type="hidden" id="co_adesao" name="co_adesao" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>"/>
        <input type="hidden" id="municipi_salvo" name="municipi_salvo" value="<?php echo isset($this->item->ds_municipios) ? $this->item->ds_municipios : ''; ?>"/>
        <?php if ($this->origemKey !== '') : ?>
        <input type="hidden" name="origem_key" value="<?php echo $this->escape($this->origemKey); ?>"/>
        <input type="hidden" name="projeto" value="<?php echo (int) $this->projeto; ?>"/>
        <?php endif; ?>
        <?php endif; ?>

        <?php
        $titulosEtapas = [
            'Identificação do Solicitante',
        ];
        $iconesEtapas = [
            'fa-solid fa-building',
        ];
        $tabsAgrupamentos = [
            [
                'chave' => 'solicitante',
                'titulo' => $titulosEtapas[0],
                'icone' => $iconesEtapas[0],
            ],
        ];

        if (!empty($this->perguntas)) {
            $agrupamentoIdNav = 0;
            foreach ($this->perguntas as $pergunta) {
                if ($agrupamentoIdNav != $pergunta->agrupamento_id) {
                    $tabsAgrupamentos[] = [
                        'chave' => $this->getChaveByTitulo($pergunta->agrupamento_titulo),
                        'titulo' => $pergunta->agrupamento_no_titulo_tab,
                        'icone' => $pergunta->agrupamento_ds_classe_icone,
                    ];
                    $agrupamentoIdNav = $pergunta->agrupamento_id;
                }
            }
        }
        ?>

        <div class="adesao-workspace">
        <aside class="adesao-sidebar" aria-label="Etapas da solicitação">
            <div class="adesao-progress-header">
                <span>Progresso</span>
                <strong id="adesao-progress-value">0%</strong>
            </div>
            <div class="progress adesao-progress" role="progressbar" aria-label="Progresso da solicitação"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <div id="adesao-progress-bar" class="progress-bar" style="width: 0%"></div>
            </div>
        <ul class="nav-tabs-agrupamento nav" role="tablist">
            <?php foreach ($tabsAgrupamentos as $indexTab => $tab): ?>
                <li class="nav-item" role="presentation">
                    <button type="button"
                            class="nav-link-agrupamento <?php echo $indexTab === 0 ? 'active' : ''; ?>"
                            data-tab-target="<?php echo $tab['chave']; ?>"
                            data-tab-index="<?php echo $indexTab; ?>"
                            role="tab">
                        <i class="<?php echo $tab['icone']; ?>"></i>
                        <span><?php echo $this->escape($tab['titulo']); ?></span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        </aside>
        <main class="adesao-main">
            <div class="adesao-required-info" role="note">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>Todos os campos marcados com * são obrigatórios. Os dados serão validados antes do envio para análise da equipe do MIR.</span>
            </div>

        <?php if ($isPreview) : ?>
            <fieldset class="adesao-preview-fieldset" disabled>
        <?php endif;
        
            include __DIR__ . '/solicitante_formulario.php';
        ?>

        

        <?php
        if (!empty($this->perguntas)) {
            $questionIndex = 1; // Initialize question counter
            $agrupamentoID = 0;
            
            foreach ($this->perguntas as $pergunta) { 
                if($agrupamentoID != $pergunta->agrupamento_id) {
                    if($agrupamentoID != 0) {
                        echo "</div></div>"; // Close previous agrupamento div and card-body
                    }
                    $chaveAgrupamento = $this->getChaveByTitulo($pergunta->agrupamento_titulo);
                    echo "<div class='card tab-agrupamento " . $chaveAgrupamento . "' data-tab-chave='" . $chaveAgrupamento . "' data-agrupamento-id='" . (int) $pergunta->agrupamento_id . "'>"; // Separator between agrupamentos
                    echo '<div class="card-body p-4">';
                    $tituloAgrupamento = $pergunta->agrupamento_titulo;
                    $iconeAgrupamento = $pergunta->agrupamento_ds_classe_icone ?: 'fa-solid fa-folder-open';
                    echo '<h4 class="etapa-titulo mt-0 mb-3"><i class="' . $iconeAgrupamento . '"></i> ' . $this->escape($tituloAgrupamento) . '</h4>';
                    $agrupamentoID = $pergunta->agrupamento_id;
                }

                ?>


                <?php
                // Fetch existing resposta if available
                $resposta = '';
                if (!empty($this->item->id)) {
                    // Assuming a method to get resposta by pergunta ID exists
                    $respostaObj = PerguntaHelper::getRespostaByAdesaoPergunta($this->item->id, $pergunta->id);
                    
                    if ($respostaObj) {
                        if($respostaObj->pergunta_tipo == 'arquivo' && !empty($respostaObj->ds_resposta)) {
                            $resposta = $respostaObj->url_download; // Pass the entire object for file type questions
                        }else{
                            $resposta = $respostaObj->ds_resposta;
                        }
                    }
                }

                // Only increment index for non-informativo questions
                $currentIndex = ($pergunta->tp_pergunta !== 'informativo') ? $questionIndex : 0;

                if ((int) $pergunta->agrupamento_id === 3 && (int) $pergunta->id === 36) {
                    echo '<div class="etapa-subtitulo">Substituto do Titular</div>';
                }
                if ((int) $pergunta->agrupamento_id === 3 && (int) $pergunta->id === 39) {
                    echo '<div class="etapa-subtitulo">Anexos obrigatórios</div>';
                }

                // Formulário de execução: valor vindo do formulário de origem, travado.
                if (array_key_exists((int) $pergunta->id, $this->valoresOrigem)) {
                    $html = PerguntaHelper::renderPerguntaField($pergunta, $this->valoresOrigem[(int) $pergunta->id], $currentIndex);
                    echo preg_replace('/^<div class="pergunta-field /', '<div class="pergunta-field pergunta-travada ', $html, 1);
                } else {
                    echo PerguntaHelper::renderPerguntaField($pergunta, $resposta, $currentIndex);
                }

                // Increment counter only for non-informativo questions
                if ($pergunta->tp_pergunta !== 'informativo') {
                    $questionIndex++;
                }
            }
            echo "</div></div>"; // Close last agrupamento div and card-body
        } else {
            echo '<p>Nenhuma pergunta disponível para este edital.</p>';
        }

        if (!empty($this->perguntasAjuda)) {
        ?>

            <section class="adesao-help" aria-labelledby="adesao-help-title">
                <h3 id="adesao-help-title">Precisa de ajuda?</h3>
                <?php
                
                
                foreach ($this->perguntasAjuda as $perguntaAjuda) {
                    echo PerguntaHelper::renderPerguntaField($perguntaAjuda, '', 0);
                }
                
                ?>
                
            </section>
            <?php
        }

         if ($isPreview) : ?>
            </fieldset>
        <?php endif; ?>

        <?php if (!$isPreview) : ?>
        <div class="adesao-navigation">
            <button type="button" class="btn btn-outline-primary" id="btn-etapa-anterior">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar
            </button>
            <button type="submit" formnovalidate class="btn btn-link adesao-save-draft"
                    name="btnSalvar" value="rascunho">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar rascunho
            </button>
            <?php 
                if($this->edital->tp_edital == 'formulario' && $this->item->id > 0 ){?>
                    <a href="<?php echo Route::_('index.php?option=com_edital&view=formulariosrespostas&layout=default&id=' . $this->item->id); ?>" class="btn btn-primary" id="btn-revisar" >
                        Revisar Respostas
                    </a>
            <?php
                }
            ?>
            <button type="submit" formnovalidate class="btn btn-primary" id="btn-proxima-etapa" value="rascunho" data-action="proxima-etapa">
                Salvar e avançar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
            <button type="submit" formnovalidate class="btn btn-primary" id="btn-enviar-adesao" value="enviar">
                Enviar para Análise
            </button>
        </div>
        <?php endif; ?>

        <?php
        //resumo e captcha desativados temporariamente, nao faze sentido no passo a passo
        if (!$isPreview && false) : ?>
        <div class="resumo-adesao ms-md-5 me-md-2 ps-md-4">
            <div class="card mt-4 mb-5 w-75 mx-auto">
                <div class="card-body">
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <h4>Resumo da Adesão</h4>
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td>Total de Documentos</td>
                                        <td><span id="total-documentos" class="numero-documentos default-color">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Enviados</td>
                                        <td><span id="documentos-enviados" class="numero-documentos success-color">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Pendentes</td>
                                        <td><span id="documentos-pendentes" class="numero-documentos warning-color">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Com Erro</td>
                                        <td><span id="documentos-erro" class="numero-documentos danger-color">0</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                        <!-- Captcha Section if enabled -->
                    <?php
                    if ($captchaPlugin && $captchaPlugin !== '0') {
                        // Import captcha plugin
                        PluginHelper::importPlugin('captcha', $captchaPlugin);
                        
                        // Get captcha field
                        $app = Factory::getApplication();
                        $captchaFields = $app->triggerEvent('onInit', ['jform_captcha']);
                        $captchaDisplay = $app->triggerEvent('onDisplay', ['jform_captcha', 'jform-captcha', 'form-control']);
                        
                        if (!empty($captchaDisplay[0])) :
                    ?>
                    <div class="captcha-section mb-4 text-center">
                        <!-- <h5><i class="fas fa-shield-alt"></i> Verificação de Segurança</h5> -->
                        <div class="captcha-container">
                            <?php echo $captchaDisplay[0]; ?>
                        </div>
                        <small class="form-text text-muted">Complete a verificação de segurança acima para continuar.</small>
                    </div>
                    <?php 
                        endif;
                    }
                    ?>
                        
                            <div class="control-group">
                                <div class="controls m-0 justify-content-center mx-auto text-center">

                                    <?php if ($this->canSave): ?>
                                        <button type="submit" class="validate btn btn-primary rounded-5 ps-4 pe-4" disabled id="btn-enviar-adesao">
                                            <?php echo 'Enviar para Análise'; ?>
                                            <i class="fa-solid fa-arrow-right-long"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-center mt-2 w-50 mx-auto">
                                <p>Você precisa preencher todos os campos obrigatórios para prosseguir.</p>
                            </div>
                        </div>
                    </div>               
                </div>
            </div>
        </div>
        <?php endif; ?>
        </main>
        </div>
        <input type="hidden" name="option" value="com_edital"/>
        <input type="hidden" name="task"
            value="adesaoform.saveComRespostas"/>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>
