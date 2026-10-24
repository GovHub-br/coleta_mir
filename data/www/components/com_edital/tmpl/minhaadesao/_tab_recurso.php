<?php

use Comedital\Component\Edital\Site\Helper\PerguntaHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

// No direct access
defined('_JEXEC') or die;

$qtDiasRestante = $this->item->dt_limite_situacao ? max(0, ceil((strtotime($this->item->dt_limite_situacao) - time()) / 86400)) : null;
$prazo = strtotime($this->item->dt_limite_situacao) - strtotime($this->item->marco_indeferido->dt_marco);
$prazoTotal = $prazo / 86400;
if ($prazoTotal > 0) {
    $percentPrazo = min(100, round((($prazoTotal - $qtDiasRestante) / $prazoTotal) * 100));
} else {
    $percentPrazo = 100;
}
// $percentPrazo = min(100, round(($qtDiasRestante / $prazoTotal) * 100));
?>

<!-- Cabeçalho do Recurso -->
<div class="recurso-header-card">
    <div class="recurso-header-left">
        <div class="recurso-icon-wrapper">
            <i class="fas fa-gavel"></i>
        </div>
        <div>
            <h4 class="recurso-header-title">Recurso administrativo</h4>
            <p class="recurso-header-sub">Prazo legal de 30 dias corridos</p>
        </div>
    </div>
    <?php
    if ($this->item->st_adesao == 'indeferido' && $qtDiasRestante > 0) {
        $badgeClass = 'recurso-disponivel-badge';
        $badgeIcon = 'fa-exclamation-circle';
        $badgeText = 'Recurso disponível';
    } else {
        $badgeClass = 'recurso-indisponivel-badge';
        $badgeIcon = 'fa-times-circle';
        $badgeText = 'Recurso indisponível';
    }
    ?>
    <span class="<?php echo $badgeClass; ?>">
        <i class="fa <?php echo $badgeIcon; ?>"></i> <?php echo $badgeText; ?>
    </span>
</div>

<!-- Motivo do Indeferimento -->
<?php

if ($this->item->st_adesao == 'recurso') {
?>
    <div class="recurso-motivo-alert recurso-em-analise">
        <div class="recurso-motivo-icon">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="recurso-motivo-body">
            <p class="recurso-motivo-title">Recurso em análise</p>
            <p class="recurso-motivo-text">
                Seu recurso administrativo foi recebido e está em processo de análise pela equipe técnica. Você será notificado sobre a decisão final.
            </p>
        </div>
    </div>
<?php
}

if ($this->item->st_adesao == 'indeferido') {

?>
    <div class="recurso-motivo-alert">
        <div class="recurso-motivo-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="recurso-motivo-body">
            <p class="recurso-motivo-title">Motivo do indeferimento:</p>
            <p class="recurso-motivo-text">
                <?php echo $this->item->ds_resposta; ?>
            </p>
            <span class="recurso-motivo-data">
                <i class="far fa-clock"></i> Indeferido em: <?php echo date('d/m/Y', strtotime($this->item->marco_indeferido->dt_marco)); ?>
            </span>
        </div>
    </div>

    <!-- Prazo para Recurso -->
    <div class="recurso-prazo-section">
        <div class="recurso-prazo-header">
            <span class="recurso-prazo-label">
                <i class="far fa-clock"></i> Prazo para recurso
            </span>
            <span class="recurso-prazo-restante"><?php echo $qtDiasRestante; ?> dias restantes</span>
        </div>
        <div class="recurso-prazo-bar-track">
            <div class="recurso-prazo-bar" style="width: <?php echo $percentPrazo ?>%;"></div>
        </div>
        <p class="recurso-prazo-final">Prazo final: <?php echo date('d/m/Y', strtotime($this->item->dt_limite_situacao)); ?></p>
    </div>

    <form id="recurso-form" method="post" action="<?php echo Route::_('index.php?option=com_edital&task=minhaadesao.enviarRecurso&id=' . $this->item->id); ?>" enctype="multipart/form-data">
        <?php echo HTMLHelper::_('form.token'); ?>
        <input type="hidden" name="id_adesao" value="<?php echo $this->item->id; ?>">

        <?php

        if (!empty($this->perguntas_recurso)) {
            $questionIndex = 1; // Initialize question counter
            foreach ($this->perguntas_recurso as $pergunta) {
                // Fetch existing resposta if available
                $resposta = '';
                // if (!empty($this->item->id)) {
                // 	// Assuming a method to get resposta by pergunta ID exists
                // 	$resposta = PerguntaHelper::getRespostaByPergunta($this->item->id, $pergunta->id);
                // }

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

        if ($qtDiasRestante > 0) : ?>
            <div class="mt-4 text-center">
                <button type="submit" class="btn btn-recurso-enviar">
                    <i class="fas fa-paper-plane me-2"></i> Enviar recurso administrativo
                </button>
            </div>
            <br />
            <br />

        <?php endif; ?>
    </form>
<?php
}
?>