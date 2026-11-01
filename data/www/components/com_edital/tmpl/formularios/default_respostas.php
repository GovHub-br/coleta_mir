<?php
/**
 * Respostas do usuário dentro de um instrumento (ou de um formulário, quando ainda sem instrumento).
 *
 * Espera $respostas (objetos montados por HtmlView::montarResposta), definido em default.php.
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>
<ul class="list-group formulario-respostas-lista">
	<?php foreach ($respostas as $resposta) : ?>
		<li class="list-group-item">
			<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
				<div class="flex-grow-1">
					<div><strong><?php echo $this->escape($resposta->formulario); ?></strong></div>
					<?php if ($resposta->projeto !== '') : ?>
						<div class="small">Projeto: <?php echo $this->escape($resposta->projeto); ?></div>
					<?php endif; ?>
					<small class="text-muted">
						<?php if ($resposta->enviado) : ?>
							<span class="badge badge-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo Text::_('COM_EDITAL_FORMULARIO_ENVIADO'); ?></span>
						<?php else : ?>
							<span class="badge badge-warning"><i class="fa fa-clock-o" aria-hidden="true"></i> Rascunho</span>
						<?php endif; ?>
						· nº <?php echo $resposta->id; ?>
						· <?php echo $resposta->pct; ?>% concluído (<?php echo $resposta->respondidos; ?> de <?php echo $resposta->total; ?> quesitos)
						<?php if (!empty($resposta->dt_criacao)) : ?>
							· iniciado em <?php echo HTMLHelper::_('date', $resposta->dt_criacao, 'd/m/Y'); ?>
						<?php endif; ?>
					</small>
				</div>
				<div class="d-flex gap-2">
					<?php if ($resposta->urlEditar) : ?>
						<a href="<?php echo $resposta->urlEditar; ?>" class="btn btn-sm btn-primary">
							<i class="fas fa-pen" aria-hidden="true"></i>
							<?php echo $resposta->enviado ? 'Atualizar' : Text::_('COM_EDITAL_FORMULARIO_CONTINUAR'); ?>
						</a>
					<?php endif; ?>
					<?php if ($resposta->urlVer) : ?>
						<a href="<?php echo $resposta->urlVer; ?>" class="btn btn-sm btn-secondary">
							<i class="fas fa-eye" aria-hidden="true"></i>
							<?php echo Text::_('COM_EDITAL_FORMULARIO_VISUALIZAR'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php if ($resposta->execucoes) : ?>
				<details class="mt-2">
					<summary class="small">Execução por projeto (<?php echo count($resposta->execucoes); ?>)</summary>
					<ul class="small mb-0 mt-1">
						<?php foreach ($resposta->execucoes as $execucao) : ?>
							<li>
								<?php echo $this->escape($execucao->rotulo); ?><?php echo $execucao->responsavel !== '' ? ' (responsável: ' . $this->escape($execucao->responsavel) . ')' : ''; ?>:
								<a href="<?php echo $this->escape($execucao->url); ?>">preencher a execução</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
