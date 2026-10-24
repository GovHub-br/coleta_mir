<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

//insert css
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularios.css');


$app = Factory::getApplication();
$user = Factory::getUser();

include __DIR__ . '/../parts/_menu_formularios_ente.php';

// Preenchimento abre sob o item de menu "Meus Formulários" (título e breadcrumb)
$itemidMeusFormularios = \Comedital\Component\Edital\Site\Helper\EditalHelper::getItemidMeusFormularios();
$itemidPreenchimento   = $itemidMeusFormularios ? '&Itemid=' . $itemidMeusFormularios : '';
?>

<div class="formularios-container">

	<!-- Formulários Disponíveis -->
	<?php if (!empty($this->available)) : ?>
		<section class="formularios-section">
			<h2 class="section-title">
				<?php echo Text::_('COM_EDITAL_FORMULARIOS_DISPONIVEIS'); ?>
			</h2>

			<div class="formularios-list">
				<?php foreach ($this->available as $item) : ?>
					<div class="formulario-card">
						<div class="card-header">
							<div class="badge-group">
								<span class="badge badge-success">
									<i class="fa fa-check-circle"></i> Aberto para preenchimento
								</span>
                                <?php
                                    if(!empty($item->adesao)):
                                ?>
                                        <span class="badge badge-warning">
                                            <i class="fa fa-clock-o"></i> Em preenchimento
                                        </span>
                                <?php
                                    endif;
                                ?>
								<?php if (!empty($item->ds_versao)) : ?>
									<span class="formulario-version"><?php echo $this->escape($item->ds_versao); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="card-body">
							<h3 class="formulario-title">
								<?php echo $this->escape($item->no_edital); ?>
							</h3>

							<?php if (!empty($item->ds_edital)) : ?>
								<p class="formulario-description">
									<?php echo substr(strip_tags($item->ds_edital), 0, 200); ?>
									<?php if (strlen(strip_tags($item->ds_edital)) > 200) : ?>
										...
									<?php endif; ?>
								</p>
							<?php endif; ?>

                            <?php 
								$now = Factory::getDate();
								$closing = Factory::getDate($item->dt_encerramento);
								$daysRemaining = $closing->diff($now)->days;
							?>
							<div class="formulario-meta">
								<div class="meta-row">
									<i class="far fa-calendar"></i>
									<span>
										<?php echo Text::_('COM_EDITAL_FORMULARIO_PRAZO'); ?>: 
										<?php echo HTMLHelper::_('date', $item->dt_abertura, Text::_('d/m/Y')); ?>
										a 
										<?php echo HTMLHelper::_('date', $item->dt_encerramento, Text::_('d/m/Y')); ?>
									</span>
                                    <div class="formulario-days-remaining">
                                        <strong><?php echo $daysRemaining; ?></strong> <?php echo Text::plural('COM_EDITAL_FORMULARIO_DIAS_RESTANTES', $daysRemaining); ?>
                                    </div>
                                    <i class="far fa-file"></i>
									<span>
                                        <?php echo $item->tamanho['qtAgrupamentos']; ?> seções - <?php echo $item->tamanho['qtPerguntas']; ?> quesitos
                                    </span>
								</div>
							</div>

							<!-- Dias Restantes -->
							
							

							<?php
							$total = (int) ($item->progresso->total ?? 0);
							$respondidos = (int) ($item->progresso->respondidos ?? 0);
							$pct = $total > 0 ? (int) round(($respondidos / $total) * 100) : 0;
							?>
							<div class="formulario-progress">
								<div class="progress-bar">
									<div class="progress-fill" style="width: <?php echo $pct; ?>%"></div>
								</div>
								<div class="progress-text">
									<span><?php echo $pct; ?>% concluído · <?php echo $respondidos; ?> de <?php echo $total; ?> quesitos respondidos</span>
								</div>
							</div>

							
						</div>

						<div class="card-footer">
                            <?php
                                if(empty($item->adesao)):
                            ?>
                                <a href="<?php echo Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . $item->id . $itemidPreenchimento); ?>" class="btn btn-primary btn-action">
									<i class="fas fa-play" aria-hidden="true"></i>
                                    Iniciar Preenchimento
                                </a>
                            <?php else: ?>
                                <a href="<?php echo Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . $item->id . $itemidPreenchimento); ?>" class="btn btn-primary btn-action">
									<i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    <?php echo Text::_('COM_EDITAL_FORMULARIO_CONTINUAR'); ?>
                                </a>
                            <?php
                                endif;
                            ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Formulários Encerrados -->
	<?php if (!empty($this->closed)) : ?>
		<section class="formularios-section">
			<h2 class="section-title">
				<?php echo Text::_('COM_EDITAL_FORMULARIOS_ENCERRADOS'); ?>
			</h2>

			<div class="formularios-list">
				<?php foreach ($this->closed as $item) : ?>
					<div class="formulario-card closed">
						<div class="card-header">
							<div class="badge-group">
								<span class="badge badge-info">
									<i class="fa fa-clock"></i> <?php echo Text::_('COM_EDITAL_FORMULARIO_ENCERRADO'); ?>
								</span>
                                <?php
                                    if (!empty($item->adesao) && $item->adesao->st_adesao != 'rascunho'):
                                ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> <?php echo Text::_('COM_EDITAL_FORMULARIO_ENVIADO'); ?>
                                </span>
                                <?php
                                    endif;
                                ?>
								<?php if (!empty($item->ds_versao)) : ?>
									<span class="formulario-version"><?php echo $this->escape($item->ds_versao); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<div class="card-body">
							<h3 class="formulario-title">
								<?php echo $this->escape($item->no_edital); ?>
							</h3>

							<?php if (!empty($item->ds_edital)) : ?>
								<p class="formulario-description">
									<?php echo substr(strip_tags($item->ds_edital), 0, 200); ?>
									<?php if (strlen(strip_tags($item->ds_edital)) > 200) : ?>
										...
									<?php endif; ?>
								</p>
							<?php endif; ?>

							<div class="formulario-meta">
								<div class="meta-row">
									<i class="far fa-calendar"></i>
									<span>
										<?php echo Text::_('COM_EDITAL_FORMULARIO_PRAZO'); ?>:
										<?php echo HTMLHelper::_('date', $item->dt_abertura, Text::_('d/m/Y')); ?>
										a
										<?php echo HTMLHelper::_('date', $item->dt_encerramento, Text::_('d/m/Y')); ?>
									</span>
									<i class="far fa-file"></i>
									<span>
										<?php echo $item->tamanho['qtAgrupamentos']; ?> seções - <?php echo $item->tamanho['qtPerguntas']; ?> quesitos
									</span>
								</div>
							</div>

							<?php if (!empty($item->adesao)) : ?>
								<?php
								$total = (int) ($item->progresso->total ?? 0);
								$respondidos = (int) ($item->progresso->respondidos ?? 0);
								$pct = $total > 0 ? (int) round(($respondidos / $total) * 100) : 0;
								$pendencias = max(0, $total - $respondidos);
								?>
								<div class="formulario-progress">
									<div class="progress-text progress-text-split">
										<span><?php echo $pct; ?>% concluído · <?php echo $respondidos; ?> de <?php echo $total; ?> quesitos respondidos</span>
										<?php if ($pendencias > 0) : ?>
											<span class="progress-pendencias"><?php echo $pendencias; ?> <?php echo $pendencias === 1 ? 'pendência' : 'pendências'; ?></span>
										<?php endif; ?>
									</div>
									<div class="progress-bar complete">
										<div class="progress-fill" style="width: <?php echo $pct; ?>%"></div>
									</div>
								</div>
							<?php endif; ?>
						</div>

                        <?php
                            if(!empty($item->adesao)){
                        ?>
						<div class="card-footer">
							<a href="<?php echo Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . $item->adesao->id); ?>" class="btn btn-secondary btn-action">
								<i class="fas fa-eye" aria-hidden="true"></i>
								<?php echo Text::_('COM_EDITAL_FORMULARIO_VISUALIZAR'); ?>
							</a>
							<a href="<?php echo Route::_('index.php?option=com_edital&task=formularios.pdfcomprovante&id=' . $item->adesao->id); ?>" class="btn btn-primary btn-action">
								<i class="fas fa-download" aria-hidden="true"></i>
								<?php echo Text::_('COM_EDITAL_FORMULARIO_BAIXAR_COMPROVANTE'); ?>
							</a>
						</div>
                        <?php
                            }
                        ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Empty State -->
	<?php if (empty($this->available) && empty($this->closed)) : ?>
		<div class="alert alert-info">
			<?php echo Text::_('COM_EDITAL_FORMULARIOS_NENHUM_DISPONIVEL'); ?>
		</div>
	<?php endif; ?>

</div>

