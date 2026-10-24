<?php
// No direct access
defined('_JEXEC') or die;
?>
<div class="historico-adesao-card">
	<h3 class="historico-title">Histórico da Adesão</h3>

	<div class="timeline">
		<!-- Item 1: Completo -->
		<div class="timeline-item <?php echo $this->item->st_adesao == 'rascunho' ? 'in-progress' : 'completed'; ?>">
			<div class="timeline-icon">
				<i class="fas fa-check"></i>
			</div>
			<div class="timeline-content">
				<h4 class="timeline-title">Formulário em preenchimento</h4>
				<p class="timeline-desc">Formulário de adesão em preenchimento</p>
				<span class="timeline-date"><?php echo date('d/m/Y', strtotime($this->item->dt_criacao)) ?></span>
			</div>
		</div>
		<?php if ($this->item->st_adesao == 'pendente') : ?>
			<!-- Item 2: Completo -->
			<div class="timeline-item completed">
				<div class="timeline-icon">
					<i class="fas fa-check"></i>
				</div>
				<div class="timeline-content">
					<h4 class="timeline-title">Documentação enviada</h4>
					<p class="timeline-desc">Documentos obrigatórios enviados para análise</p>
					<span class="timeline-date"><?php echo date('d/m/Y', strtotime($this->item->dt_criacao)) ?></span>
				</div>
			</div>

			<!-- Item 3: Estado da análise técnica -->
		
			<div class="timeline-item in-progress">
				<div class="timeline-icon">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="timeline-content">
					<h4 class="timeline-title">Análise técnica</h4>
					<p class="timeline-desc">Processo em análise pela equipe SEPPIR/MIR (prazo: 30 dias)</p>
					<span class="timeline-status">Em andamento</span>
				</div>
			</div>
		<?php elseif ($this->item->st_adesao == 'indeferido' || $this->item->st_adesao == 'recurso') : ?>
			<div class="timeline-item failed">
				<div class="timeline-icon">
					<i class="fas fa-x"></i>
				</div>
				<div class="timeline-content">
					<h4 class="timeline-title">Análise técnica</h4>
					<p class="timeline-desc">Indeferido na análise técnica</p>
					<span class="timeline-date"><?php echo date('d/m/Y', strtotime($this->item->marco_indeferido->dt_marco)); ?></span>
				</div>
			</div>
		<?php elseif ($this->item->st_adesao == 'deferido') : ?>
			<div class="timeline-item completed">
				<div class="timeline-icon">
					<i class="fas fa-check"></i>
				</div>
				<div class="timeline-content">
					<h4 class="timeline-title">Análise técnica</h4>
					<p class="timeline-desc">Aprovado na análise técnica.</p>
					<span class="timeline-date"><?php echo date('d/m/Y', strtotime($this->item->marco_deferido->dt_marco)); ?></span>
				</div>
			</div>
		<?php endif; ?>
		<?php if ($this->item->st_adesao == 'recurso') : ?>
			<div class="timeline-item in-progress">
				<div class="timeline-icon">
					<i class="fa-regular fa-clock"></i>
				</div>
				<div class="timeline-content">
					<h4 class="timeline-title">Análise de Recurso</h4>
					<p class="timeline-desc">Recurso sendo analisado</p>
					<span class="timeline-date"><?php echo date('d/m/Y', strtotime($this->item->marco_recurso->dt_marco)); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<!-- Item 5: Pendente -->
		<div class="timeline-item pending">
			<div class="timeline-icon">
				<i class="fas fa-circle"></i>
			</div>
			<div class="timeline-content">
				<h4 class="timeline-title">Termo de Adesão</h4>
				<p class="timeline-desc">Elaboração e assinatura via SEI</p>
			</div>
		</div>

		<!-- Item 7: Pendente -->
		<div class="timeline-item pending">
			<div class="timeline-icon">
				<i class="fas fa-circle"></i>
			</div>
			<div class="timeline-content">
				<h4 class="timeline-title">Assinatura MIR</h4>
				<p class="timeline-desc">Assinatura pelo Ministro/Secretário</p>
			</div>
		</div>

		<!-- Item 8: Pendente -->
		<div class="timeline-item pending">
			<div class="timeline-icon">
				<i class="fas fa-circle"></i>
			</div>
			<div class="timeline-content">
				<h4 class="timeline-title">Publicação no DOU</h4>
				<p class="timeline-desc">Publicação oficial da adesão</p>
			</div>
		</div>
	</div>
</div>
