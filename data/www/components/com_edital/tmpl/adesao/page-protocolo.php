<?php
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Session\Session;
?>

<div class="page-protocolo">
	<div class="aviso-sucesso-container">
		<i class="fas fa-check"></i>
		<h2>Envio realizado com sucesso!</h2>
		<p>Recebemos seus documentos. O processo de adesão foi iniciado e segue para a etapa de análise técnica.</p>
		<a href="<?php echo Route::_('index.php?option=com_edital&task=adesao.generatepdf&id=' . $this->item->id . '&' . Session::getFormToken() . '=1'); ?>" class="btn-comprovante">
			Baixar comprovante
		</a>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="detalhe-protocolo-container">
				<h3><i class="fas fa-file-alt"></i> Detalhes do Protocolo</h3>
				<div class="protocolo-details">
					<div class="protocolo-card protocolo-card-numero">
						<div class="protocolo-label">Número do Protocolo</div>
						<div class="protocolo-value">SINAPIR-<?php echo HTMLHelper::_('date', $this->item->created_at, 'Y'); ?>-<?php echo str_pad($this->item->id, 4, '0', STR_PAD_LEFT); ?></div>
					</div>
					<div class="protocolo-card protocolo-card-status">
						<div class="protocolo-label">Status Atual</div>
						<div class="protocolo-value status-<?php echo strtolower(str_replace(' ', '-', $this->item->st_situacao)); ?>">Aguardando análise</div>
					</div>
					<div class="protocolo-card full-width">
						<div class="protocolo-label">Data e Hora do Envio</div>
						<div class="protocolo-value"><?php echo HTMLHelper::_('date', $this->item->created_at, 'd \d\e F \d\e Y, \à\s H:i'); ?></div>
					</div>
				</div>
			</div>
			<div class="instrucoes-finais-container">
				<h3>Próximos Passos</h3>
				<p>Sua solicitação passará por uma verificação técnica para assegurar que todos os documentos atendam aos requisitos legais do SINAPIR.</p>
					<ul>
						<li>Prazo estimado para primeira resposta: 30 dias corridos.</li>
						<li>Fique atento ao seu e-mail cadastrado para notificações.</li>
						<li>Você pode complementar a documentação caso seja solicitado.</li>
					</ul>
					
					
					
			</div>
		</div>
		<div class="col-md-4">
			<div class="timeline-container">
				<div class="timeline-title">Linha do Tempo</div>
				<div class="timeline">
					<div class="timeline-item">
						<div class="timeline-icon completed">
							<i class="fas fa-check"></i>
						</div>
						<div class="timeline-content">
							<h4>Envio de Documentação</h4>
							<p class="timeline-status completed">Concluído</p>
						</div>
					</div>
					
					<div class="timeline-item">
						<div class="timeline-icon current">
							<i class="fas fa-circle" style="font-size: 8px;"></i>
						</div>
						<div class="timeline-content">
							<h4>Análise Técnica</h4>
							<p class="timeline-status current">Em andamento</p>
						</div>
					</div>
					
					<div class="timeline-item">
						<div class="timeline-icon pending">
						</div>
						<div class="timeline-content">
							<h4>Parecer Preliminar</h4>
							<p class="timeline-status pending">Aguardando</p>
						</div>
					</div>
					
					<div class="timeline-item">
						<div class="timeline-icon pending">
						</div>
						<div class="timeline-content">
							<h4>Homologação Final</h4>
							<p class="timeline-status pending">Aguardando</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>