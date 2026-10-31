<?php
/**
 * Respostas do usuário a um formulário (várias por formulário, ex.: uma por TED/convênio).
 *
 * Espera $item (formulário com ->respostas), $podeEditar (formulário no prazo) e
 * $itemidPreenchimento, definidos em default.php.
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="formulario-respostas">
	<?php if (empty($item->respostas)) : ?>
		<p class="formulario-respostas-vazio text-muted">Nenhum preenchimento ainda.</p>
	<?php else : ?>
		<p class="formulario-respostas-titulo">
			<strong><?php echo count($item->respostas); ?></strong>
			<?php echo count($item->respostas) === 1 ? 'preenchimento' : 'preenchimentos'; ?>
		</p>
		<ul class="list-group formulario-respostas-lista">
			<?php foreach ($item->respostas as $resposta) : ?>
				<?php
				$total = (int) ($resposta->progresso->total ?? 0);
				$respondidos = (int) ($resposta->progresso->respondidos ?? 0);
				$pct = $total > 0 ? (int) round(($respondidos / $total) * 100) : 0;
				$enviado = $resposta->st_adesao !== 'rascunho';
				?>
				<li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
					<div class="flex-grow-1">
						<div>
							<strong><?php echo $this->escape($resposta->rotulo ?: 'Preenchimento nº ' . (int) $resposta->id); ?></strong>
						</div>
						<small class="text-muted">
							<?php if ($enviado) : ?>
								<span class="badge badge-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo Text::_('COM_EDITAL_FORMULARIO_ENVIADO'); ?></span>
							<?php else : ?>
								<span class="badge badge-warning"><i class="fa fa-clock-o" aria-hidden="true"></i> Rascunho</span>
							<?php endif; ?>
							· nº <?php echo (int) $resposta->id; ?>
							· <?php echo $pct; ?>% concluído (<?php echo $respondidos; ?> de <?php echo $total; ?> quesitos)
							<?php if (!empty($resposta->dt_criacao)) : ?>
								· iniciado em <?php echo HTMLHelper::_('date', $resposta->dt_criacao, Text::_('d/m/Y')); ?>
							<?php endif; ?>
						</small>
					</div>
					<div class="d-flex gap-2">
						<?php if ($podeEditar) : ?>
							<a href="<?php echo Route::_('index.php?option=com_edital&view=adesaoform&layout=default&modo=iniciar&edital_id=' . (int) $item->id . '&adesao_id=' . (int) $resposta->id . $itemidPreenchimento); ?>" class="btn btn-sm btn-primary">
								<i class="fas fa-pen" aria-hidden="true"></i>
								<?php echo $enviado ? 'Atualizar' : Text::_('COM_EDITAL_FORMULARIO_CONTINUAR'); ?>
							</a>
						<?php endif; ?>
						<?php if ($enviado) : ?>
							<a href="<?php echo Route::_('index.php?option=com_edital&view=formulariosrespostas&id=' . (int) $resposta->id); ?>" class="btn btn-sm btn-secondary">
								<i class="fas fa-eye" aria-hidden="true"></i>
								<?php echo Text::_('COM_EDITAL_FORMULARIO_VISUALIZAR'); ?>
							</a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
