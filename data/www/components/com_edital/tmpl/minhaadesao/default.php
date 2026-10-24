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

use Comedital\Component\Edital\Site\Helper\AdesaoHelper;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

$canEdit = Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_edital.' . $this->item->id);

if (!$canEdit && Factory::getApplication()->getIdentity()->authorise('core.edit.own', 'com_edital' . $this->item->id)) {
	$canEdit = Factory::getApplication()->getIdentity()->id == $this->item->created_by;
}

// Add custom CSS
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/minhaadesao.css');

// Preenchimento de formulários abre sob o item de menu "Meus Formulários" (título e breadcrumb)
$itemidMeusFormularios = \Comedital\Component\Edital\Site\Helper\EditalHelper::getItemidMeusFormularios();
$itemidPreenchimento   = $itemidMeusFormularios ? '&Itemid=' . $itemidMeusFormularios : '';

//add js
$this->document->addScript(Uri::root() . 'media/com_edital/js/minhaadesao.js');

// Status configuration
$statusConfig = [
	'rascunho'    => ['label' => 'Rascunho',          'icon' => 'fa-pencil-alt',        'class' => 'secondary', 'desc' => 'Sua adesão ainda não foi enviada.'],
	'pendente'    => ['label' => 'Em análise',        'icon' => 'fa-clock',             'class' => 'status-analise', 'desc' => 'Sua documentação está sendo analisada pela equipe técnica do MIR.'],
	'avaliado'    => ['label' => 'Análise técnica',   'icon' => 'fa-clock',             'class' => 'warning', 'desc' => 'Sua documentação está sendo analisada pela equipe técnica do MIR.'],
	'recusado'    => ['label' => 'Recusada',          'icon' => 'fa-times-circle',      'class' => 'danger',  'desc' => 'Sua adesão foi recusada. Verifique as observações e providencie as correções.'],
	'recurso'     => ['label' => 'Em Recurso',        'icon' => 'fa-gavel',     		'class' => 'status-recurso',    'desc' => 'Seu recurso foi recebido e está sendo analisado.'],
	'aprovado'    => ['label' => 'Aprovada',          'icon' => 'fa-check-circle',      'class' => 'success', 'desc' => 'Parabéns! Sua adesão foi aprovada pela equipe do MIR.'],
	'indeferido'  => ['label' => 'Indeferida',        'icon' => 'fa-times-circle',               	'class' => 'status-indeferido',  'desc' => 'Sua adesão foi indeferida. Consulte o parecer técnico para mais detalhes.'],
	'deferido'    => ['label' => 'Deferida',          'icon' => 'fa-check-circle',      'class' => 'status-deferido', 'desc' => 'Sua adesão foi deferida com sucesso.'],
	'assinatura'  => ['label' => 'Assinatura',        'icon' => 'fa-file-signature',    'class' => 'info',    'desc' => 'Aguardando assinatura do termo de adesão.'],
	'publicacao'  => ['label' => 'Publicação',        'icon' => 'fa-newspaper',         'class' => 'info',    'desc' => 'Aguardando publicação no Diário Oficial.'],
	'complementacao' => ['label' => 'Em Complementação', 'icon' => 'fa-exclamation-circle', 'class' => 'warning', 'desc' => 'É necessário complementar a documentação enviada.'],
];

$esferaLabels = [
	'estadual'  => 'Estado',
	'municipal' => 'Município',
	'consorcio' => 'Consórcios/Associações',
];

$st     = $this->item->st_adesao ?? 'pendente';
$status = $statusConfig[$st] ?? ['label' => ucfirst($st), 'icon' => 'fa-question-circle', 'class' => 'secondary', 'desc' => ''];
$esfera = $esferaLabels[$this->item->tp_esfera_governamental ?? ''] ?? ($this->item->tp_esfera_governamental ?? '');
$esferaLabel = match ($this->item->tp_esfera_governamental ?? '') {
	'estadual'  => 'Estado',
	'municipal' => 'Município',
	'consorcio' => 'Consórcios/Associações',
	default     => 'Outro',
};
if ($this->item->tp_esfera_governamental == 'estadual' && $this->item->sg_estado) {
	$esferaLabelComplemento = $this->item->sg_estado;
}

if ($this->item->tp_esfera_governamental == 'municipal' && $this->item->ds_municipios) {
	$esferaLabelComplemento = $this->item->ds_municipios;
}

if ($this->item->tp_esfera_governamental == 'consorcio' && $this->item->ds_municipios) {
	$esferaLabelComplemento = $this->item->ds_municipios;
}

$countAdesoes    = count($this->items);
$modalidadeLabel = $this->item->ds_modalidade_label ?? '';


$hasPendingAction = ($this->temDocPendenteCorrecao && $this->item->st_adesao == 'complementacao') ||
	($this->item->st_adesao == 'indeferido' && $this->item->dt_limite_situacao && (strtotime($this->item->dt_limite_situacao) > time()));

$docsComplementacao = $this->docsComplementacao ?? [];
?>

<div class="selecao-adesao-container">
	<?php if (empty($this->items)) : ?>
		<p>Você ainda não realizou nenhuma adesão.</p>
	<?php else : ?>
		<label for="adesoes_realizadas" class="form-label">
			<i class="fas fa-folder-open me-1"></i>
			Visualizando adesão
		</label>
		<div class="input-group">
			<span class="input-group-text"><i class="fas fa-exchange-alt"></i></span>
			<select name="adesoes_realizadas" id="adesoes_realizadas" class="form-select">
				<?php foreach ($this->items as $item) : ?>
					<option value="<?php echo (int) $item->id; ?>" <?php echo ($item->id == $this->item->id) ? 'selected' : ''; ?>>
						<?php echo AdesaoHelper::getProtocoloAdesao($item->id); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ($countAdesoes > 1) : ?>
			<div class="form-text">Você possui <?php echo $countAdesoes; ?> adesões. Selecione uma para visualizar os detalhes.</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
if (!empty($this->item->id)) : ?>
	<div class="minha-adesao-container">

		<!-- Header -->
		<div class="adesao-header">
			<div class="adesao-breadcrumb">

			</div>
			<div class="adesao-badges">
				<span class="badge-status <?php echo $status['class']; ?>">
					<i class="fas <?php echo $status['icon']; ?>"></i>
					<?php echo $status['label']; ?>
				</span>
				<?php if ($modalidadeLabel) : ?>
					<span class="badge-modalidade">
						<i class="fas fa-award"></i>
						<?php echo $modalidadeLabel; ?>
					</span>
				<?php endif; ?>
				<?php if ($esferaLabel) : ?>
					<span class="badge-esfera">
						<i class="fas fa-building"></i>
						<?php echo $esferaLabel; ?>
						<?php if (isset($esferaLabelComplemento)) : ?>
							- <?php echo $esferaLabelComplemento; ?>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<!-- Status Card -->
		<div class="status-card">
			<div class="status-icon-wrapper <?php echo $status['class']; ?>">
				<i class="fas <?php echo $status['icon']; ?>"></i>
			</div>
			<?php
			// if ($this->item->st_adesao == 'pendente') {
			?>
			<div class="status-info">
				<h3 class="status-title">Status Atual: <?php echo $status['label']; ?></h3>
				<p class="status-desc"><?php echo $status['desc']; ?></p>
				<?php
				if ($this->item->st_adesao == 'indeferido') {
				?>
					<p class="status-indeferido-resposta">
						<?php echo $this->item->ds_resposta; ?>
					</p>
				<?php
				}

				if ($this->item->st_adesao == 'rascunho') {
					$editalId = (int) ($this->item->co_edital_id ?? 0);

					$menu = Factory::getApplication()->getMenu();
					$itemId = null;
					foreach ($menu->getItems('component', 'com_edital') as $menuItem) {
						if (($menuItem->query['view'] ?? '') === 'adesaoform' && (int) $menuItem->getParams()->get('edital_id') === $editalId) {
							$itemId = $menuItem->id;
							break;
						}
					}

					$link = 'index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar';
					if ($itemId) {
						$link .= '&Itemid=' . $itemId;
					}

					echo '<p>Continue o preenchimento do formulário. <a href="' . Route::_($link) . '">Editar agora.</a></p>';
				}
				?>
				<div class="status-meta">
					<span class="status-meta-item">
						<i class="fas fa-calendar-alt"></i>
						Última atualização: <?php echo date('d/m/Y', strtotime($this->item->dt_alterado ?? $this->item->dt_criacao )); ?>
					</span>				
				</div>
			</div>
			<?php
			// }
			?>
		</div>

		<?php if (!empty($this->item->st_adesao) && ($this->item->st_adesao == 'deferido' || $this->item->tp_solicitacao == 'migracao')) : ?>
			<div class="status-card">
						<div class="status-icon-wrapper <?php echo $status['class']; ?>">
							<i class="fas fa-file"></i>
						</div>
						<div class="status-info">
							<h3 class="status-title">Meus Formulários</h3>
								<p class="status-desc">Consulte o formulário de adesão e outros</p>
						</div>
						<div class="status-action">
							<a href="<?php echo Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . $this->item->id); ?>" class="btn btn-primary">
								Ver formulário de Adesão <i class="fas fa-arrow-right"></i>
							</a>
							<a href="<?php echo Route::_('index.php?option=com_edital&view=formularios'); ?>" class="btn btn-primary">
								Ver outros formulários <i class="fas fa-arrow-right"></i>
							</a>
						</div>
				</div>
		<?php endif; ?>

		<?php if (!empty($this->formularios_abertos)) : ?>
			<?php foreach ($this->formularios_abertos as $formulario) :
				if (empty($formulario->adesao) || $formulario->adesao->st_adesao == 'rascunho') :
				?>
				<div class="status-card">
						<div class="status-icon-wrapper <?php echo $status['class']; ?>">
							<i class="fas <?php echo $status['icon']; ?>"></i>
						</div>
						<div class="status-info">
							<h3 class="status-title">Preenchimento do <?php echo htmlspecialchars($formulario->no_edital); ?></h3>
								<p class="status-desc">Preencha o formulário eletrônico, salve rascunhos e acompanhe o comprovante de envio</p>
							<span class="acao-prazo">
								<i class="fas fa-calendar-alt"></i>
								Prazo: <?php echo date('d/m/Y', strtotime($formulario->dt_encerramento ?? '')) . ($formulario->dt_encerramento ? ' (' . ((int) floor((strtotime($formulario->dt_encerramento) - time()) / 86400)) . ' dia' . (((int) floor((strtotime($formulario->dt_encerramento) - time()) / 86400)) > 1 ? 's' : '') . ')' : ''); ?>
							</span>
						</div>
						<div class="status-action">
							<a href="<?php echo Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . $formulario->id . $itemidPreenchimento); ?>" class="btn btn-primary">
								Preencher <i class="fas fa-arrow-right"></i>
							</a>
						</div>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>

		<!-- Ações Pendentes -->
		<?php if ($hasPendingAction) { ?>
			<div class="acoes-pendentes-section">
				<h3 class="acoes-pendentes-title">
					<i class="fas fa-exclamation-circle"></i>
					Ações Pendentes
				</h3>
				<?php
				if ($this->temDocPendenteCorrecao && $this->item->st_adesao == 'complementacao') {
				?>
					<div class="acao-card">
						<div class="acao-icon">
							<i class="fas fa-exclamation-triangle"></i>
						</div>
						<div class="acao-content">
							<strong class="acao-title">Documento(s) pendente(s) de correção</strong>
							<?php if (!empty($docsComplementacao)) : ?>
								<div class="acao-complementacao-list">
									<?php foreach ($docsComplementacao as $doc) : ?>
										<div class="acao-complementacao-item">
											<p class="acao-desc"><strong>Solicitação de correção:</strong> <?php echo htmlspecialchars($doc->pergunta_titulo ?? 'Documento'); ?></p>
											<?php if (!empty($doc->ds_comentario_avaliador)) : ?>
												<p class="acao-desc"><strong>Justificativa:</strong> <?php echo htmlspecialchars($doc->ds_comentario_avaliador); ?></p>
											<?php endif; ?>
											<?php if (!empty($doc->dt_limite)) : ?>
												<?php
												$limiteTimestamp = strtotime($doc->dt_limite);
												$diasRestantes = (int) floor(($limiteTimestamp - time()) / 86400);
												$textoPrazo = '';

												if ($diasRestantes > 0) {
													$textoPrazo = ' (' . $diasRestantes . ' dia' . ($diasRestantes > 1 ? 's' : '') . ')';
												} elseif ($diasRestantes === 0) {
													$textoPrazo = ' (vence hoje)';
												} else {
													$textoPrazo = ' (prazo encerrado)';
												}
												?>
												<span class="acao-prazo">
													<i class="fas fa-calendar-alt"></i>
													Prazo: <?php echo date('d/m/Y', $limiteTimestamp) . $textoPrazo; ?>
												</span>
											<?php endif; ?>
										</div>
										<br/>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<div class="acao-action">
							<a href="#" class="btn btn-warning btn-resolver" id="btn-resolver-documento">
								Resolver agora <i class="fas fa-arrow-right"></i>
							</a>
						</div>
					</div>
				<?php } ?>
			</div>

			<?php
			if ($this->item->st_adesao == 'indeferido' && $this->item->dt_limite_situacao && (strtotime($this->item->dt_limite_situacao) > time())) {
			?>
				<div class="acao-card">
					<div class="acao-icon">
						<i class="fas fa-gavel"></i>
					</div>
					<div class="acao-content">
						<strong class="acao-title">Recurso administrativo disponível</strong>
						<p class="acao-desc">Você pode entrar com um recurso administrativo para contestar a decisão de indeferimento. Aproveite para anexar documentos ou informações que possam fortalecer seu recurso.</p>
						<span class="acao-prazo">
							<i class="fas fa-calendar-alt"></i>
							Prazo para recurso: <?php echo date('d/m/Y', strtotime($this->item->dt_limite_situacao)); ?>
						</span>
					</div>
					<div class="acao-action">
						<a href="#" class="btn btn-warning btn-resolver" id="btn-entrar-recurso">
							Entrar com recurso <i class="fas fa-arrow-right"></i>
						</a>
					</div>
				</div>

			<?php } ?>
		<?php } ?>



		<div class="row">
			<div class="col-lg-7">
				<!-- Tabs Navigation -->
				<ul class="nav nav-tabs adesao-tabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="visao-geral-tab" data-bs-toggle="tab" data-bs-target="#visao-geral" type="button" role="tab">
							<i class="fas fa-list-ul"></i>
							Visão Geral
						</button>
					</li>
					<?php if ($this->item->st_adesao != 'rascunho') : ?>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">
							<i class="fas fa-file-alt"></i>
							Documentos
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="recurso-tab" data-bs-toggle="tab" data-bs-target="#recurso" type="button" role="tab">
							<i class="fas fa-gavel"></i>
							Recurso
						</button>
					</li>
					<?php endif; ?>
				</ul>
				<!-- Tabs Content -->
				<div class="tab-content adesao-tab-content">

					<!-- Visão Geral Tab -->
					<div class="tab-pane fade show active" id="visao-geral" role="tabpanel">
						<?php include __DIR__ . '/_tab_visaogeral.php'; ?>
					</div>

					<?php if ($this->item->st_adesao != 'rascunho') : ?>
					<!-- Documentos Tab -->
					<div class="tab-pane fade" id="documentos" role="tabpanel">
						<?php include __DIR__ . '/_tab_documentos.php'; ?>
					</div>

					<!-- Recurso Tab -->
					<div class="tab-pane fade" id="recurso" role="tabpanel">
						<?php include __DIR__ . '/_tab_recurso.php'; ?>
					</div>
					<?php endif; ?>
				</div>
			</div>



			<div class="col-lg-5">

				<!-- Coluna Direita: Cards -->
				<!-- Dados do Ente -->
				<div class="info-card dados-ente-card">
					<h3 class="info-card-title">Dados do Ente</h3>
					<div class="info-card-body">
						<div class="info-item">
							<i class="fas fa-id-card"></i>
							<div class="info-content">
								<span class="info-label">CNPJ</span>
								<span class="info-value"><?php echo $this->item->ds_cnpj ?></span>
							</div>
						</div>
						<!-- <div class="info-item">
									<i class="fas fa-user"></i>
									<div class="info-content">
										<span class="info-label">Responsável</span>
										<span class="info-value">Maria da Silva</span>
									</div>
								</div> -->
						<div class="info-item">
							<i class="fas fa-envelope"></i>
							<div class="info-content">
								<span class="info-label">E-mail</span>
								<span class="info-value"><?php echo $this->item->ds_email_responsavel ?></span>
							</div>
						</div>
						<!-- <div class="info-item">
									<i class="fas fa-phone"></i>
									<div class="info-content">
										<span class="info-label">Telefone</span>
										<span class="info-value">(11) 3333-0000</span>
									</div>
								</div> -->
						<?php
						if (!empty($this->item->ds_modalidade)) {
						?>
							<div class="info-item">
								<i class="fas fa-award"></i>
								<div class="info-content">
									<span class="info-label">Modalidade</span>
									<span class="info-value"><?php echo $this->item->ds_modalidade_label ?></span>
								</div>
							</div>
						<?php
						}
						?>
					</div>
				</div>

				<!-- Precisa de ajuda? -->
				<div class="help-card">
					<h3 class="help-card-title">Precisa de ajuda?</h3>
					<p class="help-card-text">Nossa equipe está disponível para auxiliar em qualquer etapa do processo.</p>
					<div class="help-contact">
						<a href="mailto:sinapir@igualdaderacial.gov.br" class="help-link">
							sinapir@igualdaderacial.gov.br
						</a>
						<div class="help-phones">
							<a href="tel:6120273101" class="help-link">
								61 2027 3101
							</a>
							<span class="help-separator">/</span>
							<a href="https://wa.me/5561996361292" class="help-link">
								61 99636 1292 (whatsapp)
							</a>
						</div>

						<?php
						if($this->item->st_adesao != 'rascunho') {
						?>
							<!-- Formulário de Adesão em PDF -->
							<div class="action-card">
								<h3 class="action-card-title">Formulário de Adesão</h3>
								<p class="action-card-text">Baixe as informações registradas no Formulário de Adesão em PDF: o formulário completo ou somente os campos do Anexo II.</p>
								<a href="<?php echo Route::_('index.php?option=com_edital&task=adesaorespostas.formulariopdf&id=' . (int) $this->item->id . '&' . Session::getFormToken() . '=1'); ?>" class="btn btn-primary btn-action">
									<i class="far fa-file-pdf"></i> Formulário completo
								</a>
								<a href="<?php echo Route::_('index.php?option=com_edital&task=adesaorespostas.formulariopdf&modelo=anexo2&id=' . (int) $this->item->id . '&' . Session::getFormToken() . '=1'); ?>" class="btn btn-primary btn-action">
									<i class="far fa-file-pdf"></i> Anexo II
								</a>
							</div>

							<!-- Recurso Administrativo -->
							<div class="action-card">
								<?php
								if ($this->item->st_adesao == 'indeferido' && strtotime($this->item->dt_limite_situacao) > time()) {
									$btnClass = 'btn-primary';
									$btnDisabled = '';
									$btnText = 'Enviar Recurso';
								} else {
									$btnClass = 'btn-action';
									$btnDisabled = 'disabled';
									$btnText = 'Recurso indisponível';
								}
								?>

								<h3 class="action-card-title">Recurso Administrativo</h3>
								<p class="action-card-text">Em caso de indeferimento, você tem direito a recurso no prazo de 30 dias corridos.</p>
								<button class="btn <?php echo $btnClass; ?> btn-action" id="btn-recurso" <?php echo $btnDisabled; ?> onclick="jQuery('#recurso-tab').trigger('click')">
									<?php echo $btnText; ?>
								</button>
							</div>

						<?php
						}
						?>

						<!-- Reabrir Adesão -->
						<!-- <div class="action-card">
							<h3 class="action-card-title">Reabrir Adesão</h3>
							<p class="action-card-text">Após o deferimento você poderá alterar a modalidade, se desejar.</p>
							<button class="btn btn-action" disabled>
								Não disponível
							</button>
						</div> -->
					</div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>