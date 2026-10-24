<?php
// No direct access
defined('_JEXEC') or die;

$respostas = $this->item->respostas ?? [];
$totalDocs       = 0;
$aprovados       = 0;
$pendentes       = 0;
$rejeitados      = 0;
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

$statusMap = [
	''               => ['label' => 'Em análise',            'cls' => 'doc-badge-analise',        'icon' => 'far fa-clock'],
	'analise'        => ['label' => 'Em análise',            'cls' => 'doc-badge-analise',        'icon' => 'far fa-clock'],
	'complementacao' => ['label' => 'Em Complementação',        'cls' => 'doc-badge-complementacao', 'icon' => 'far fa-comment-alt'],
	'aprovada'       => ['label' => 'Aprovado',              'cls' => 'doc-badge-aprovado',       'icon' => 'far fa-check-circle'],
	'aprovado'       => ['label' => 'Aprovado',              'cls' => 'doc-badge-aprovado',       'icon' => 'far fa-check-circle'],
	'reprovada'      => ['label' => 'Rejeitado',             'cls' => 'doc-badge-rejeitado',      'icon' => 'far fa-times-circle'],
	'rejeitado'      => ['label' => 'Rejeitado',             'cls' => 'doc-badge-rejeitado',      'icon' => 'far fa-times-circle'],
	'rejeitada'      => ['label' => 'Rejeitado',             'cls' => 'doc-badge-rejeitado',      'icon' => 'far fa-times-circle'],
	'pendente'       => ['label' => 'Enviado - Em análise',  'cls' => 'doc-badge-analise',        'icon' => 'far fa-clock'],
];
?>

<div class="docs-header">
	<div class="docs-header-top">
		<div>
			<h3 class="docs-title">Documentação Obrigatória</h3>
			<p class="docs-subtitle">Acompanhe a situação de cada documento enviado</p>
		</div>
		<div class="docs-header-stats">
			<span class="docs-stat-pill docs-stat-aprovado"><i class="fas fa-check-circle"></i> <?php echo $aprovados; ?> aprovados</span>
			<span class="docs-stat-pill docs-stat-analise"><i class="far fa-clock"></i> <?php echo $pendentes; ?> em análise</span>
			<?php if ($complementacoes > 0) : ?>
				<span class="docs-stat-pill docs-stat-complementacao"><i class="fas fa-exclamation-circle"></i> <?php echo $complementacoes; ?> em complementação</span>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Lista de Documentos -->
<div class="docs-list">
	<?php if (!empty($respostas)) : ?>
		<?php foreach ($respostas as $resposta) : ?>
			<?php
			if (($resposta->pergunta_tipo ?? '') === 'informativo') continue;

			$sit        = strtolower($resposta->st_situacao ?? 'pendente');
			$statusInfo = $statusMap[$sit] ?? $statusMap[''];
			$hasFile    = !empty($resposta->arquivo_url);
			$hasComment = !empty($resposta->ds_comentario_avaliador);
			?>
			<div class="doc-item <?php echo $sit ?>">
				<div class="doc-item-header">
					<div class="doc-item-left">
						<?php if ($sit === 'aprovada' || $sit === 'aprovado') : ?>
							<span class="doc-status-icon doc-status-icon-aprovado"><i class="fa fa-check"></i></span>
						<?php elseif ($sit === 'reprovada' || $sit === 'rejeitado' || $sit === 'rejeitada') : ?>
							<span class="doc-status-icon doc-status-icon-rejeitado"><i class="fa fa-circle-info"></i></span>
						<?php elseif ($sit === 'complementacao') : ?>
							<span class="doc-status-icon doc-status-icon-complementacao"><i class="fas fa-exclamation-circle"></i></span>
						<?php else : ?>
							<span class="doc-status-icon doc-status-icon-analise"><i class="far fa-file-alt"></i></span>
						<?php endif; ?>
						<div class="doc-item-info">
							<h4 class="doc-item-title"><?php echo htmlspecialchars($resposta->pergunta_titulo ?? 'Sem título'); ?></h4>
							<?php if (!empty($resposta->pergunta_descricao)) : ?>
								<p class="doc-item-desc"><?php echo htmlspecialchars($resposta->pergunta_descricao); ?></p>
							<?php endif; ?>
							<span class="doc-inline-status doc-inline-<?php echo $sit; ?>"><?php echo htmlspecialchars($statusInfo['label']); ?></span>
						</div>
					</div>
					<?php if ($sit === 'complementacao') : ?>
						<div class="doc-item-right">
							<a href="#"
							   class="btn-doc-enviar btn-enviar-resposta"
							   data-resposta-id="<?php echo (int) $resposta->id; ?>"
							   data-doc-title="<?php echo htmlspecialchars($resposta->pergunta_titulo ?? '', ENT_QUOTES); ?>"
							   data-bs-toggle="modal"
							   data-bs-target="#modalEnviarDoc">
								<i class="fas fa-upload"></i> Enviar
							</a>
						</div>
					<?php endif; ?>
				</div>

				<?php if ($sit === 'complementacao') : ?>
					<div class="doc-item-alert">
						<strong>Documento precisa de correção</strong><br>
						<span><?php echo $hasComment ? nl2br(htmlspecialchars($resposta->ds_comentario_avaliador)) : 'Verifique as orientações e envie novamente.'; ?></span>
						<span>
							<?php if (!empty($resposta->dt_limite)) : ?>
								<br><strong>Data limite para envio:</strong> <?php echo htmlspecialchars((new DateTime($resposta->dt_limite))->format('d/m/Y H:i')); ?>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ($sit === 'rejeitado') : ?>
					<div class="doc-item-alert">
						<strong>Documento rejeitado</strong><br>
						<span><?php echo $hasComment ? nl2br(htmlspecialchars($resposta->ds_comentario_avaliador)) : 'Verifique as orientações e envie novamente.'; ?></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php else : ?>
		<div class="empty-state">
			<i class="fas fa-file-alt"></i>
			<p>Nenhum documento encontrado</p>
		</div>
	<?php endif; ?>
</div>

<!-- Modal: Enviar Documento -->
<div class="modal fade" id="modalEnviarDoc" tabindex="-1" aria-labelledby="modalEnviarDocLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="modalEnviarDocLabel"><i class="fas fa-upload me-2"></i>Enviar Documento</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
			</div>
			<div class="modal-body">
				<p id="modal-doc-title" class="fw-semibold mb-3 text-dark"></p>
				<div class="mb-3">
					<label for="input-doc-arquivo" class="form-label">Selecione o arquivo</label>
					<input type="file" class="form-control" id="input-doc-arquivo" />
					<div class="form-text">Formatos aceitos: PDF</div>
				</div>
				<div id="modal-upload-alert" class="alert d-none" role="alert"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" id="btn-cancelar-envio" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="btn-confirmar-envio">
					<i class="fas fa-upload"></i> Enviar
				</button>
				<button type="button" class="btn btn-success d-none" id="btn-fechar-envio" data-bs-dismiss="modal">
					<i class="fas fa-check"></i> Fechar
				</button>
			</div>
		</div>
	</div>
</div>
<!-- /Modal -->
