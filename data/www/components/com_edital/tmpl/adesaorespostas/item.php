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
use Comedital\Component\Edital\Site\Helper\RespostaHelper;

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaorespostas.css');

//add js documentos
$this->document->addScript(Uri::root() . 'media/com_edital/js/adesaorespostas.js?v=' . filemtime(JPATH_ROOT . '/media/com_edital/js/adesaorespostas.js'));

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
    'complementacao' => ['label' => 'Em Complementação',  'cls' => 'ar-badge-complementacao', 'icon' => 'far fa-comment-alt'],
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

$esferaLabel = match($this->item->tp_esfera_governamental ?? '') {
    'estadual'  => 'Estado',
    'municipal' => 'Município',
    'consorcio' => 'Consórcios/Associações',
    default     => 'Outro',
};

if($this->item->tp_esfera_governamental == 'estadual' && $this->item->sg_estado ) {
    $esferaLabel = $this->item->sg_estado;
}

if($this->item->tp_esfera_governamental == 'municipal' && $this->item->ds_municipios ) {
    $esferaLabel = $this->item->ds_municipios;
}

if($this->item->tp_esfera_governamental == 'consorcio' && $this->item->ds_municipios ) {
    $esferaLabel = $this->item->ds_municipios;
}

$situacaoAdesao = strtolower($this->item->st_adesao ?? 'pendente');

$respostas = $this->item->respostas ?? [];
$finalizacao = $this->item->finalizacao ?? (object) [
    'totalDocs' => 0,
    'aprovados' => 0,
    'pendentes' => 0,
    'rejeitados' => 0,
    'complementacoes' => 0,
    'podeFinalizar' => false,
    'motivoBloqueio' => 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado.',
];

$totalDocs = (int) ($finalizacao->totalDocs ?? 0);
$aprovados = (int) ($finalizacao->aprovados ?? 0);
$pendentes = (int) ($finalizacao->pendentes ?? 0);
$rejeitados = (int) ($finalizacao->rejeitados ?? 0);
$complementacoes = (int) ($finalizacao->complementacoes ?? 0);
$podeFinalizar = !empty($finalizacao->podeFinalizar);
$motivoBloqueio = (string) ($finalizacao->motivoBloqueio ?? '');
?>

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
            <?php
                if(empty($this->municipios)){
            ?>
                <div class="ar-meta-row">
                    <i class="fas fa-city"></i> <?php echo htmlspecialchars($esferaLabel); ?>
                </div>
            <?php
                }else{
                    foreach ($this->municipios as $municipio) {
                        echo '<div class="ar-meta-row"><i class="fas fa-city"></i> ';
                        echo htmlspecialchars($municipio->no_municipio . ' (' . $municipio->sg_estado . ')');
                        if (!empty($municipio->ds_email)) {
                            echo ' -  ' . htmlspecialchars($municipio->ds_email);
                        }
                        echo '</div>';
                    }
                }
            ?>
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
            <div class="ar-dado-row mt-2 d-flex flex-wrap gap-2">
                <a href="<?php echo Route::_('index.php?option=com_edital&task=adesaorespostas.formulariopdf&id=' . (int) $this->item->id . '&' . Session::getFormToken() . '=1'); ?>"
                   class="ar-btn-baixar" title="Baixar o Formulário de Adesão completo para anexar ao processo SEI">
                    <i class="far fa-file-pdf"></i> Formulário completo (PDF)
                </a>
                <a href="<?php echo Route::_('index.php?option=com_edital&task=adesaorespostas.formulariopdf&modelo=anexo2&id=' . (int) $this->item->id . '&' . Session::getFormToken() . '=1'); ?>"
                   class="ar-btn-baixar" title="Baixar somente os campos previstos no Anexo II (estrutura e capacidade de execução orçamentária do órgão)">
                    <i class="far fa-file-pdf"></i> Anexo II (PDF)
                </a>
            </div>
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

    <!-- Stats -->
    <div class="ar-stats">
        <div class="ar-stat ar-stat-total">
            <span class="ar-stat-num"><?php echo $totalDocs; ?></span>
            <span class="ar-stat-label">Total</span>
        </div>
        <div class="ar-stat ar-stat-ap">
            <span class="ar-stat-num"><?php echo $aprovados; ?></span>
            <span class="ar-stat-label">Aprovados</span>
        </div>
        <div class="ar-stat ar-stat-pend">
            <span class="ar-stat-num"><?php echo $pendentes; ?></span>
            <span class="ar-stat-label">Pendentes</span>
        </div>
        <div class="ar-stat ar-stat-comp">
            <span class="ar-stat-num"><?php echo $complementacoes; ?></span>
            <span class="ar-stat-label">Em Complementação</span>
        </div>
        <div class="ar-stat ar-stat-rej">
            <span class="ar-stat-num"><?php echo $rejeitados; ?></span>
            <span class="ar-stat-label">Rejeitados</span>
        </div>
    </div>

    <!-- Tags -->
    <!-- <div class="ar-tags">
        <span class="ar-tag"><i class="fas fa-check-circle"></i> Órgão de Igualdade Racial</span>
        <span class="ar-tag"><i class="fas fa-check-circle"></i> Conselho de Igualdade Racial</span>
    </div> -->

    <!-- Documentos enviados -->
    <div class="ar-docs-section">
        <h3 class="ar-docs-titulo"><i class="far fa-file-alt"></i> Documentos enviados</h3>

        <div class="ar-tabs">
            <button class="ar-tab active">Todos (<?php echo $totalDocs; ?>)</button>
            <button class="ar-tab">Pendentes (<?php echo $pendentes; ?>)</button>
            <button class="ar-tab">Aprovados (<?php echo $aprovados; ?>)</button>
            <button class="ar-tab">Rejeitados (<?php echo $rejeitados; ?>)</button>
            <button class="ar-tab">Complementação (<?php echo $complementacoes; ?>)</button>
        </div>

        <div class="ar-docs-list">
            <?php if (!empty($respostas)):
                $agrupamentoAtual = false;
                foreach ($respostas as $resposta):
                    if (($resposta->pergunta_tipo ?? '') === 'informativo') continue;

                    $coAgrupamento = $resposta->co_agrupamento ?? null;
                    if ($coAgrupamento !== $agrupamentoAtual):
                        $agrupamentoAtual = $coAgrupamento;
            ?>
                <div class="ar-agrupamento-separador mt-4">
                    <h4 class="ar-agrupamento-titulo"><?php echo htmlspecialchars($resposta->no_agrupamento ?? 'Outros'); ?></h4>
                </div>
            <?php endif;

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
                        $badgeLabel = 'Em Complementação';
                    } else {
                        $docCls = 'ar-doc-pendente';
                        $badgeCls = 'ar-doc-badge-pendente';
                        $badgeLabel = 'Pendente';
                    }

                    $arquivoUrl = '';
                    if (($resposta->pergunta_tipo ?? '') === 'arquivo' && !empty($resposta->ds_resposta)) {
                        $arquivoUrl = $this->convertToWebPath($resposta->id);
                        $nomeArquivo = $resposta->arquivo_nome ?? (!empty($resposta->ds_resposta) ? basename($resposta->ds_resposta) : '');
                    }else{
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
                            <span class="ar-doc-badge <?php echo $badgeCls; ?>"><?php echo $badgeLabel; ?></span>
                        </div>
                        <div class="ar-doc-meta">
                            <?php if (!empty($nomeArquivo)): ?>
                                <i class="far fa-file"></i> <?php echo htmlspecialchars($nomeArquivo); ?>
                                <span class="ar-doc-sep">&middot;</span>
                                <?php echo $resposta->arquivo_tamanho_formatado?>
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
                            <?php echo RespostaHelper::renderResposta($resposta); ?>
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

                        <?php if (
                                    ($sit === 'pendente') && $situacaoAdesao == 'pendente'
                                    || ($sit === 'pendente' && $situacaoAdesao == 'complementacao')
                                    ): ?>
                            <div class="ar-eval-acoes">
                                <button class="ar-btn-eval ar-btn-aprovar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Aprovar"><i class="fas fa-thumbs-up"></i></button>
                                <?php if ($sit === 'pendente'): ?>
                                <button class="ar-btn-eval ar-btn-complementar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Complementação"><i class="fas fa-exclamation-triangle"></i></button>
                                <?php endif; ?>
                                <button class="ar-btn-eval ar-btn-rejeitar" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Rejeitar"><i class="fas fa-thumbs-down"></i></button>
                            </div>
                        <?php endif;
                        if ($sit === 'rejeitado' && $resposta->modified_by == $this->idUsuarioRejeicaoAutomatica && ($situacaoAdesao == 'pendente' || $situacaoAdesao == 'complementacao')): ?>
                            <button class="btn btn-warning ar-btn-reabrir" data-resposta-id="<?php echo (int)$resposta->id; ?>" title="Voltar para pendente"><i class="fas fa-undo"></i> Voltar para pendente</button>
<?php
                        endif;

                        ?>
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

</div><!-- /.ar-page -->

<!-- Modal Aprovação -->
<div class="modal fade" id="ar-modal-aprovacao" tabindex="-1" aria-labelledby="arModalAprovacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start">
                <div>
                    <h5 class="modal-title" id="arModalAprovacaoLabel">Aprovar Documento</h5>
                    <p class="small mb-0">Confirme a aprovação deste documento. Esta ação não poderá ser desfeita.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="ar-aprovacao-confirmar">
                    <i class="fas fa-thumbs-up"></i> Confirmar Aprovação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Rejeição -->
<div class="modal fade" id="ar-modal-rejeicao" tabindex="-1" aria-labelledby="arModalRejeicaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="arModalRejeicaoLabel">Rejeitar Documento</h5>
                    <p class="small mb-0">Informe o motivo da rejeição do documento. O ente federativo será notificado.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="small fw-semibold text-uppercase text-muted mb-2">Motivos padronizados</p>
                <div class="ar-motivos-lista">
                    <div class="ar-motivo-opcao">Documento ilegível ou com baixa qualidade de digitalização.</div>
                    <div class="ar-motivo-opcao">Documento fora do prazo de validade.</div>
                    <div class="ar-motivo-opcao">Documento não corresponde ao solicitado.</div>
                    <div class="ar-motivo-opcao">Documento incompleto ou faltando páginas.</div>
                    <div class="ar-motivo-opcao">Assinatura ou carimbo ausente.</div>
                </div>

                <p class="small fw-semibold text-uppercase text-muted mb-2">Motivo da Rejeição</p>
                <textarea id="ar-motivo-texto" class="form-control" rows="4" placeholder="Informe o motivo da rejeição do documento..."></textarea>
                <div class="invalid-feedback">O motivo é obrigatório.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="ar-modal-confirmar">
                    <i class="fas fa-times-circle"></i> Confirmar Rejeição
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Complementação -->
<div class="modal fade" id="ar-modal-complementacao" tabindex="-1" aria-labelledby="arModalComplementacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="arModalComplementacaoLabel">Solicitar Complementação</h5>
                    <p class="text-primary small mb-0">Envie uma mensagem ao ente federativo solicitando documentos ou informações adicionais.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="small fw-semibold text-muted mb-2">Mensagens padronizadas</p>
                <div class="ar-motivos-lista">
                    <div class="ar-motivo-opcao">Solicitamos a complementação do Plano de Promoção da Igualdade Racial, que deve conter metas e indicadores alinhados ao PLANAPIR.</div>
                    <div class="ar-motivo-opcao">É necessário apresentar ata de reunião do Conselho com data mais recente (últimos 6 meses).</div>
                    <div class="ar-motivo-opcao">O documento de comprovação orçamentária precisa estar atualizado para o exercício vigente.</div>
                    <div class="ar-motivo-opcao">Favor apresentar decreto de nomeação do gestor atual do órgão de igualdade racial.</div>
                </div>

                <p class="small fw-semibold text-muted mb-2">Mensagem personalizada</p>
                <textarea id="ar-complementacao-texto" class="form-control" rows="4" placeholder="Digite a mensagem de solicitação..."></textarea>
                <div class="invalid-feedback">A mensagem é obrigatória.</div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-warning" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning text-white" id="ar-complementacao-confirmar">
                    <i class="fas fa-paper-plane"></i> Enviar solicitação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reabrir Resposta -->
<div class="modal fade" id="ar-modal-reabrir" tabindex="-1" aria-labelledby="arModalReabrirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="arModalReabrirLabel">Voltar resposta para pendente</h5>
                    <p class="small mb-0">Confirme se deseja mover esta resposta para pendente novamente.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Essa ação vai limpar a situação atual da resposta e permitir nova análise.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning text-white" id="ar-reabrir-confirmar">
                    <i class="fas fa-undo"></i> Confirmar retorno para pendente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bottom bar -->
<div class="ar-bottom-bar">
    <a href="<?php echo $this->getGestaoUrl(); ?>" class="btn-voltar" style="margin-bottom:0">
        <i class="fas fa-arrow-left"></i> Voltar à lista
    </a>
    <?php
    if (!in_array($situacaoAdesao, ['deferido', 'indeferido', 'recurso'], true)) {
        $tooltipAttrs = '';
        if (!$podeFinalizar && $motivoBloqueio !== '') {
            $tooltipText = htmlspecialchars($motivoBloqueio, ENT_QUOTES, 'UTF-8');
            $tooltipAttrs = ' tabindex="0" data-bs-toggle="tooltip" title="' . $tooltipText . '" data-bs-title="' . $tooltipText . '"';
        }

        echo '<div class="ar-bottom-right">';
        echo '    <span class="ar-action-wrapper ar-action-wrapper-deferir"' . $tooltipAttrs . '><button type="button" class="ar-btn-deferir"' . ($podeFinalizar ? '' : ' disabled aria-disabled="true"') . '><i class="far fa-check-circle"></i> Deferir solicitação</button></span>';
        echo '    <span class="ar-action-wrapper ar-action-wrapper-indeferir"' . $tooltipAttrs . '><button type="button" class="ar-btn-indeferir"' . ($podeFinalizar ? '' : ' disabled aria-disabled="true"') . '><i class="far fa-times-circle"></i> Indeferir solicitação</button></span>';
        echo '</div>';
    }
    ?>
</div>

<?php
// Incluir modal de indeferimento
include JPATH_COMPONENT . '/tmpl/_modals/indeferir.php';

// Adicionar script do modal
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-indeferir.js?v=' . filemtime(JPATH_ROOT . '/media/com_edital/js/modal-indeferir.js'));

// Incluir modal de deferimento
include JPATH_COMPONENT . '/tmpl/_modals/deferir.php';
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-deferir.js?v=' . filemtime(JPATH_ROOT . '/media/com_edital/js/modal-deferir.js'));

// Incluir modal de complementação
include JPATH_COMPONENT . '/tmpl/_modals/complementacao.php';
$this->document->addScript(Uri::root() . 'media/com_edital/js/modal-complementacao.js?v=' . filemtime(JPATH_ROOT . '/media/com_edital/js/modal-complementacao.js'));
?>
