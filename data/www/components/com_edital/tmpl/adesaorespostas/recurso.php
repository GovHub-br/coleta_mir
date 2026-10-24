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
use \Joomla\CMS\Session\Session;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Comedital\Component\Edital\Site\Helper\RespostaHelper;

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaorespostas.css');

//add js documentos
$this->document->addScript(Uri::root() . 'media/com_edital/js/adesaorespostas_recurso.js');

// add inicializacao de js
$this->document->addScriptDeclaration("
    document.addEventListener('DOMContentLoaded', function() {
        AdesaoRespostas.token = '" . Session::getFormToken() . "';
        AdesaoRespostas.baseUrl = '" . Uri::root() . "';
        AdesaoRespostas.init();
    });
");

$protocolo = AdesaoHelper::getProtocoloAdesao($this->item->id);

$statusMap = [
    ''               => ['label' => 'Em análise',      'cls' => 'ar-badge-analise',        'icon' => 'far fa-clock'],
    'analise'        => ['label' => 'Em análise',      'cls' => 'ar-badge-analise',        'icon' => 'far fa-clock'],
    'complementacao' => ['label' => 'Complementação',  'cls' => 'ar-badge-complementacao', 'icon' => 'far fa-comment-alt'],
    'deferido'       => ['label' => 'Deferido',        'cls' => 'ar-badge-deferido',       'icon' => 'far fa-check-circle'],
    'indeferido'     => ['label' => 'Indeferido',      'cls' => 'ar-badge-indeferido',     'icon' => 'far fa-times-circle'],
    'recurso'        => ['label' => 'Recurso',         'cls' => 'ar-badge-recurso',        'icon' => 'fas fa-gavel'],
];

$tipoMap = [
    'migracao'    => ['cls' => 'ar-badge-migracao',    'icon' => 'fas fa-exchange-alt'],
    'nova_adesao' => ['cls' => 'ar-badge-nova-adesao', 'icon' => 'fas fa-plus-circle'],
];
$tipoSolicitacao = $tipoMap[$this->item->tp_solicitacao ?? ''] ?? null;

$stKey = $this->item->st_adesao ?? '';
$st = $statusMap[$stKey] ?? $statusMap[''];

$esferaLabel = match ($this->item->tp_esfera_governamental ?? '') {
    'estadual'  => 'Estado',
    'municipal' => 'Município',
    'consorcio' => 'Consórcios/Associações',
    default     => 'Outro',
};

if ($this->item->tp_esfera_governamental == 'estadual' && $this->item->sg_estado) {
    $esferaLabel = $this->item->sg_estado;
}

if ($this->item->tp_esfera_governamental == 'municipal' && $this->item->ds_municipios) {
    $esferaLabel = $this->item->ds_municipios;
}

if ($this->item->tp_esfera_governamental == 'consorcio' && $this->item->ds_municipios) {
    $esferaLabel = $this->item->ds_municipios;
}

$situacaoAdesao = strtolower($this->item->st_adesao ?? 'pendente');

$respostas = $this->item->respostas ?? [];
$totalDocs = 0;
$aprovados = 0;
$pendentes = 0;
$rejeitados = 0;
$complementacoes = 0;

foreach ($respostas as $r) {
    if (($r->pergunta_tipo ?? '') === 'informativo') continue;
    $totalDocs++;
    $sit = strtolower($r->st_situacao ?? 'pendente');
    if ($sit === 'aprovada' || $sit === 'aprovado') $aprovados++;
    elseif ($sit === 'reprovada' || $sit === 'rejeitado' || $sit === 'rejeitada') $rejeitados++;
    elseif ($sit === 'complementacao') $complementacoes++;
    else $pendentes++;
}
?>

<input type="hidden" id="urlVoltar" value="<?php echo $this->getGestaoUrl(); ?>">
<form id="formAvaliacaoRecurso" action="<?php echo Route::_('index.php?option=com_edital&task=adesaorespostas.avaliarRecurso'); ?>" method="post">
    <input type="hidden" name="id_adesao" value="<?php echo (int)$this->item->id; ?>">
    <div class="ar-page">
        <input type="hidden" id="ar-adesao-id" value="<?php echo (int)$this->item->id; ?>">
        <!-- Voltar -->
        <a href="<?php echo $this->getGestaoUrl(); ?>" class="btn-voltar">
            <i class="fas fa-arrow-left"></i> Voltar à lista
        </a>

        <!-- Info Card -->
        <div class="ar-info-card">
            <div class="ar-info-left">
                <div class="ar-proto-row">
                    <span class="ar-protocolo"><?php echo htmlspecialchars($protocolo); ?></span>
                    <span class="ar-badge <?php echo $st['cls']; ?>">
                        <i class="<?php echo $st['icon']; ?>"></i> <?php echo $st['label']; ?>
                    </span>
                    <?php if ($tipoSolicitacao): ?>
                        <span class="ar-badge <?php echo $tipoSolicitacao['cls']; ?>">
                            <i class="<?php echo $tipoSolicitacao['icon']; ?>"></i> <?php echo AdesaoHelper::getTipoSolicitacaoLabel($this->item->tp_solicitacao); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="ar-ente-nome"><?php echo htmlspecialchars($this->item->no_oficial ?? '—'); ?></div>
                <div class="ar-meta-row">
                    <i class="fas fa-city"></i> <?php echo htmlspecialchars($esferaLabel); ?>
                </div>
                <div class="ar-meta-row">
                    <i class="fas fa-user-tie"></i> Modalidade: <strong><?php echo !empty($this->item->ds_modalidade) ? $this->item->ds_modalidade_label : 'Não definida'; ?></strong>
                </div>
                <div class="ar-meta-row">
                    <i class="far fa-clock"></i> Enviado em: <?php echo HTMLHelper::_('date', $this->item->created_at, 'd/m/Y'); ?>
                </div>
            </div>
            <div class="ar-info-right">
                <div class="ar-dados-titulo">Dados do Ente</div>
                <div class="ar-dado-row">CNPJ: <span><?php echo htmlspecialchars($this->item->ds_cnpj ?? '—'); ?></span></div>
                <!-- <div class="ar-dado-row">Responsável: <span><?php echo htmlspecialchars($this->item->no_responsavel ?? '—'); ?></span></div> -->
                <div class="ar-dado-row">E-mail: <span><?php echo htmlspecialchars($this->item->ds_email_responsavel ?? '—'); ?></span></div>
            </div>
        </div>

        <?php if (($this->item->tp_esfera_governamental ?? '') === 'consorcio'): ?>
            <!-- Municipios -->
            <div class="ar-municipios-wrapper">
                <select>
                    <option>Todos os Municípios (<?php echo htmlspecialchars($this->item->tp_esfera_governamental === 'consorcio' ? 'Consórcios/Associações' : 'município'); ?>)</option>
                    <?php if (!empty($this->item->ds_municipios)):
                        $munis = explode(',', $this->item->ds_municipios);
                        foreach ($munis as $m): ?>
                            <option><?php echo htmlspecialchars(trim($m)); ?></option>
                    <?php endforeach;
                    endif; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (!empty($this->item->ds_resposta)): ?>
            <!-- <div class="ar-motivo-indeferimento">
            <div class="ar-motivo-titulo">Motivo original do indeferimento:</div>
            <div class="ar-motivo-texto"><?php echo $this->item->ds_resposta; ?></div>
        </div> -->
        <?php endif; ?>

        <!-- Documentos enviados -->
        <div class="ar-docs-section">

            <h3 class="ar-docs-titulo"><i class="far fa-file-alt"></i> Recurso Enviado</h3>
            <div class="ar-docs-list">
                <div class="ar-doc-card ar-doc-rejeitado">
                    <div class="ar-doc-body">
                        <div class="ar-doc-header">
                            <span class="ar-doc-titulo">
                                Motivo original do indeferimento:
                        </div>
                        <div class="ar-doc-meta">
                            <i class="far fa-clock"></i> Indeferido em: <?php echo HTMLHelper::_('date', $this->marco_indeferido, 'd/m/Y'); ?>
                        </div>

                        <div class="ar-doc-preview">
                            <?php echo $this->item->ds_resposta; ?>
                        </div>
                    </div>
                </div>


                <?php if (!empty($respostas)):
                    foreach ($respostas as $resposta):
                        if (($resposta->pergunta_tipo ?? '') === 'informativo') continue;

                        $sit = strtolower($resposta->st_situacao ?? 'pendente');
                        if ($sit === 'aprovada' || $sit === 'aprovado') {
                            $docCls = 'ar-doc-aprovado';
                            $badgeCls = 'ar-doc-badge-aprovado';
                            $badgeLabel = 'Aprovado';
                        } elseif ($sit === 'reprovada' || $sit === 'rejeitado' || $sit === 'rejeitada') {
                            $docCls = 'ar-doc-rejeitado';
                            $badgeCls = 'ar-doc-badge-rejeitado';
                            $badgeLabel = 'Rejeitado';
                        } elseif ($sit === 'complementacao') {
                            $docCls = 'ar-doc-complementacao';
                            $badgeCls = 'ar-doc-badge-complementacao';
                            $badgeLabel = 'Complementação';
                        } else {
                            $docCls = 'ar-doc-pendente';
                            $badgeCls = 'ar-doc-badge-pendente';
                            $badgeLabel = 'Pendente';
                        }

                        $arquivoUrl = '';
                        if (($resposta->pergunta_tipo ?? '') === 'arquivo' && !empty($resposta->ds_resposta)) {
                            $arquivoUrl = $this->convertToWebPath($resposta->id);
                            $nomeArquivo = $resposta->arquivo_nome ?? (!empty($resposta->ds_resposta) ? basename($resposta->ds_resposta) : '');
                        } else {
                            $arquivoUrl = '';
                            $nomeArquivo = '';
                        }

                        $obrigatoria = ($resposta->pergunta_obrigatoria ?? '') === 'sim';
                ?>
                        <div class="ar-doc-card <?php echo $docCls; ?>">
                            <div class="ar-doc-body">
                                <div class="ar-doc-header">
                                    <span class="ar-doc-titulo">
                                        <?php echo htmlspecialchars($resposta->pergunta_titulo ?? ''); ?>
                                        <?php if ($obrigatoria): ?><span class="ar-doc-obrigatoria"> *</span><?php endif; ?>
                                    </span>
                                    <!-- <span class="ar-doc-badge <?php echo $badgeCls; ?>"><?php echo $badgeLabel; ?></span> -->
                                </div>
                                <div class="ar-doc-meta">
                                    <?php if (!empty($nomeArquivo)): ?>
                                        <i class="far fa-file"></i> <?php echo htmlspecialchars($nomeArquivo); ?>
                                        <span class="ar-doc-sep">&middot;</span>
                                        <?php echo $resposta->arquivo_tamanho_formatado ?>
                                        <span class="ar-doc-sep">&middot;</span>
                                    <?php else: ?>
                                        <i class="far fa-comment"></i> Resposta textual
                                    <?php endif; ?>

                                    <?php if (!empty($resposta->dt_criacao)): ?>
                                        Enviado: <?php echo HTMLHelper::_('date', $resposta->dt_criacao, 'd/m/Y'); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($resposta->dt_analise)): ?>
                                        <span class="ar-doc-sep">&middot;</span>
                                        Analisado: <?php echo HTMLHelper::_('date', $resposta->dt_analise, 'd/m/Y'); ?>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($nomeArquivo)): ?>
                                    <div class="ar-doc-preview">
                                        <?php if (($resposta->pergunta_tipo ?? '') === 'municipio'): ?>
                                            <?php echo RespostaHelper::renderResposta($resposta); ?>
                                        <?php else: ?>
                                            <?php echo $resposta->ds_resposta; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (($sit === 'reprovada' || $sit === 'rejeitado' || $sit === 'rejeitada') && !empty($resposta->ds_comentario_avaliador)): ?>
                                    <div class="ar-motivo-rejeicao">
                                        <div class="ar-motivo-titulo">Motivo da rejeição:</div>
                                        <div class="ar-motivo-texto"><?php echo htmlspecialchars($resposta->ds_comentario_avaliador); ?></div>
                                    </div>
                                <?php elseif ($sit === 'complementacao' && !empty($resposta->ds_comentario_avaliador)): ?>
                                    <div class="ar-motivo-rejeicao ar-motivo-complementacao">
                                        <div class="ar-motivo-titulo">Solicitação de complementação:</div>
                                        <div class="ar-motivo-texto"><?php echo htmlspecialchars($resposta->ds_comentario_avaliador); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="ar-doc-acoes">
                                <?php if (!empty($arquivoUrl)): ?>
                                    <a href="<?php echo htmlspecialchars($arquivoUrl); ?>" class="ar-btn-baixar" target="_blank">
                                        <i class="fas fa-download"></i> Baixar
                                    </a>
                                    <a href="<?php echo htmlspecialchars($arquivoUrl . '&inline=1'); ?>" class="ar-btn-visualizar" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> Visualizar
                                    </a>
                                <?php endif; ?>

                                <?php if (($sit === 'pendente' || $sit === 'complementacao') && $situacaoAdesao == 'pendente'): ?>
                                    <div class="ar-eval-acoes">
                                        <button class="ar-btn-eval ar-btn-aprovar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Aprovar"><i class="fas fa-thumbs-up"></i></button>
                                        <?php if ($sit === 'pendente'): ?>
                                            <button class="ar-btn-eval ar-btn-complementar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Complementação"><i class="fas fa-exclamation-triangle"></i></button>
                                        <?php endif; ?>
                                        <button class="ar-btn-eval ar-btn-rejeitar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Rejeitar"><i class="fas fa-thumbs-down"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach;
                else: ?>
                    <div class="ar-vazio">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhum documento encontrado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>



        <?php
        if (!empty($this->perguntas)) {
            $questionIndex = 1; // Initialize question counter
            foreach ($this->perguntas as $pergunta) {
                // Fetch existing resposta if available
                $resposta = '';
                // Only increment index for non-informativo questions
                $currentIndex = ($pergunta->tp_pergunta !== 'informativo') ? $questionIndex : 0;

                echo PerguntaHelper::renderPerguntaField($pergunta, $resposta, $currentIndex);

                // Increment counter only for non-informativo questions
                if ($pergunta->tp_pergunta !== 'informativo') {
                    $questionIndex++;
                }
            }
        } else {
            echo '<p>Nenhuma pergunta disponível para este edital.</p>';
        }
        ?>


    </div><!-- /.ar-page -->


    <!-- Bottom bar -->
    <div class="ar-bottom-bar">
        <a href="<?php echo $this->getGestaoUrl(); ?>" class="btn-voltar" style="margin-bottom:0">
            <i class="fas fa-arrow-left"></i> Voltar à lista
        </a>
        <div class="ar-bottom-right">
            <button class="ar-btn-deferir" id="btn-deferir-recurso" name="acao" type="submit" value="deferir"><i class="far fa-check-circle"></i> Deferir solicitação</button>
            <button class="ar-btn-indeferir" id="btn-indeferir-recurso" name="acao" type="submit" value="indeferir"><i class="far fa-times-circle"></i> Indeferir solicitação</button>
        </div>

    </div>
</form>

<!-- Modal de confirmação de ação do recurso -->
<div class="modal fade" id="modalConfirmacaoRecurso" tabindex="-1" aria-labelledby="modalConfirmacaoRecursoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmacaoRecursoLabel">Confirmar ação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnConfirmarAcaoRecurso">Confirmar</button>
            </div>
        </div>
    </div>
</div>