<?php

/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use Comedital\Component\Edital\Site\Helper\RespostaHelper;

// Add CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formulariosrespostas.css');

$protocolo = AdesaoHelper::getProtocoloAdesao($this->item->id);

$esferaLabel = match($this->item->tp_esfera_governamental ?? '') {
    'estadual'  => $this->item->sg_estado ?? 'Estado',
    'municipal' => $this->item->ds_municipios ?? 'Município',
    'consorcio' => $this->item->ds_municipios ?? 'Consórcios/Associações',
    default     => 'Outro',
};
$respostas = $this->item->respostas ?? [];

if(!$this->ehAdmin){
    include __DIR__ . '/../parts/_menu_formularios_ente.php';
}


// Status configuration
$statusConfig = [
		'rascunho'    => ['label' => 'Rascunho',          'icon' => 'fa-pencil',        'class' => 'status-rascunho', 'desc' => 'Seu formulário ainda não foi enviado.'],
		'pendente'    => ['label' => 'Enviado',        'icon' => 'fa-check',             'class' => 'status-deferido', 'desc' => '.']
];
?>

<div class="fr-page">
	<!-- Back Button -->
	
	<div class="fr-header">
		<?php
			if($this->ehAdmin){
		?>
			<a href="<?php echo Route::_('index.php?view=formularioadesoes&item_id=' . $this->item->co_edital_id); ?>" class="btn-voltar">
				<i class="fas fa-arrow-left"></i> Voltar
			</a>
		<?php
			}else{ ?>
				<a href="<?php echo Route::_('index.php?view=formularios'); ?>" class="btn-voltar">
					<i class="fas fa-arrow-left"></i> Voltar aos formulários
				</a>
		<?php
			}
		?>

		<?php
		
		if(strtotime($this->item->edital->dt_encerramento) > time() || $this->item->st_adesao === 'pendente'){
		?>
			<span class="adesao-status <?php echo $statusConfig[$this->item->st_adesao]['class'] ?? ''; ?>">
				<i class="fa <?php echo $statusConfig[$this->item->st_adesao]['icon'] ?? ''; ?>"></i> 
				<?php echo $statusConfig[$this->item->st_adesao]['label'] ?? ''; ?>
			</span>
		<?php 
		}else{
			?>
			<span class="adesao-status status-encerrado"><i class="far fa-clock"></i> Encerrado</span>
		<?php
		}
		
		?>
</div> <!-- Close fr-header -->

	<!-- Header Info Card -->
	<div class="fr-info-card">
		<div class="fr-info-left">
			<div class="fr-proto-row">
				<span class="fr-protocolo"><?php echo htmlspecialchars($protocolo); ?></span>
			</div>
			<div class="fr-ente-nome"><?php echo htmlspecialchars($this->item->edital->no_edital ?? '—'); ?></div>
			<div class="fr-meta-row">
				<i class="far fa-clock"></i> Enviado em: <?php echo HTMLHelper::_('date', $this->item->created_at ?? $this->item->dt_criacao, 'd/m/Y'); ?>
			</div>
		</div>
		<div class="fr-info-right">
			<div class="fr-dados-titulo">Dados do Responsável</div>
			<?php
				$documento = preg_replace('/\D/', '', (string) ($this->item->ds_cnpj ?? ''));
				$documentoMascarado = match (strlen($documento)) {
					14 => '**.***.***/****-' . substr($documento, -2),
					11 => '***.***.***-' . substr($documento, -2),
					default => $documento !== ''
						? str_repeat('*', max(0, strlen($documento) - 2)) . substr($documento, -2)
						: '—',
				};
			?>
			<div class="fr-dado-row">Nome: <span><?php echo htmlspecialchars($this->item->no_responsavel ?? '—'); ?></span></div>
			<div class="fr-dado-row">Documento: <span><?php echo htmlspecialchars($documentoMascarado); ?></span></div>
			<div class="fr-dado-row">E-mail: <span><?php echo htmlspecialchars($this->item->ds_email_responsavel ?? '—'); ?></span></div>
		</div>
	</div>

	<!-- Responses by Section -->
		<?php if (!empty($respostas)):
			$agrupamentoAtual = false;
			foreach ($respostas as $k=>$resposta):
				// Skip informational questions
				if (($resposta->pergunta_tipo ?? '') === 'informativo') continue;

				$coAgrupamento = $resposta->co_agrupamento ?? null;
				if ($coAgrupamento !== $agrupamentoAtual):
					$agrupamentoAtual = $coAgrupamento;
					if($k>0):?>
						</div> <!-- Close previous agrupamento-separador -->
					<?php 
					endif;
		?>
		<div class="fr-docs-section">
			<div class="fr-agrupamento-separador">
				<h3 class="fr-docs-titulo"><i class="far fa-file-alt"></i> <?php echo htmlspecialchars($resposta->no_agrupamento ?? 'Respostas'); ?></h3>
			</div>
		<?php endif;

			$perguntaTipo = $resposta->pergunta_tipo ?? '';
			$obrigatoria = ($resposta->pergunta_obrigatoria ?? '') === 'sim';
			$nomeArquivo = '';
			$arquivoUrl = '';

			if ($perguntaTipo === 'arquivo' && !empty($resposta->ds_resposta)) {
				$arquivoUrl = $this->convertToWebPath($resposta->id);
				$nomeArquivo = basename($resposta->ds_resposta);
			}
		?>
			<div class="fr-resposta-card">
				<div class="fr-resposta-header">
					<span class="fr-resposta-titulo">
						<?php echo htmlspecialchars($resposta->pergunta_titulo ?? ''); ?>
						<?php if ($obrigatoria): ?><span class="fr-obrigatoria"> *</span><?php endif; ?>
					</span>
				</div>

				<div class="fr-resposta-meta">
					<?php if ($perguntaTipo === 'arquivo' && !empty($nomeArquivo)): ?>
						<i class="far fa-file"></i> <?php echo htmlspecialchars($nomeArquivo); ?>
						<?php if (!empty($resposta->arquivo_tamanho_formatado)): ?>
							<span class="fr-meta-sep">&middot;</span>
							<?php echo $resposta->arquivo_tamanho_formatado; ?>
						<?php endif; ?>
					<?php else: ?>
						<i class="far fa-comment"></i> Resposta
					<?php endif; ?>

				
				</div>

				<?php if ($perguntaTipo !== 'arquivo' || empty($nomeArquivo)): ?>
				<div class="fr-resposta-conteudo">
					<?php echo RespostaHelper::renderResposta($resposta); ?>
				</div>
				<?php endif; ?>

				<?php if (!empty($arquivoUrl)): ?>
				<div class="fr-resposta-acoes">
					<a href="<?php echo htmlspecialchars($arquivoUrl); ?>" class="fr-btn-download" target="_blank">
						<i class="fas fa-download"></i> Baixar
					</a>
					<a href="<?php echo htmlspecialchars($arquivoUrl . '&inline=1'); ?>" class="fr-btn-visualizar" target="_blank">
						<i class="fas fa-eye"></i> Visualizar
					</a>
				</div>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
		<?php else: ?>
			<div class="alert alert-info">
				<?php echo Text::_('COM_EDITAL_NENHUMA_RESPOSTA'); ?>
			</div>
		<?php endif; ?>
	</div>
</div>

