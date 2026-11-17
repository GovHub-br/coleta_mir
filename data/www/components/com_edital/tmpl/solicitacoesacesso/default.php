<?php
/**
 * Pedidos de acesso para o MIR liberar ou recusar.
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularios.css');

$cpf = static fn (string $c) => strlen($c) === 11 ? vsprintf('%s.%s.%s-%s', str_split($c, 3)) : $c;
$unidade = function ($s) {
	if (!$s->co_identificador)
	{
		return '';
	}

	return strtoupper($s->tp_identificador) . ' ' . $s->co_identificador . ' - '
		. ($s->no_unidade ?: 'não encontrada nos instrumentos do DW');
};
?>
<div class="formularios-container">
	<h2 class="section-title">Solicitações de acesso</h2>

	<?php if (!$this->pendentes) : ?>
		<div class="alert alert-info">Nenhuma solicitação aguardando liberação.</div>
	<?php endif; ?>

	<div class="formularios-list">
		<?php foreach ($this->pendentes as $s) : ?>
			<div class="formulario-card">
				<div class="card-body">
					<h3 class="formulario-title"><?php echo $this->escape(AcessoHelper::PERFIS[$s->tp_perfil]['rotulo'] ?? $s->tp_perfil); ?></h3>
					<p class="mb-1"><strong><?php echo $this->escape($s->no_usuario); ?></strong>
						· CPF <?php echo $this->escape($cpf($s->cpf)); ?> · <?php echo $this->escape($s->email); ?></p>
					<?php if ($unidade($s) !== '') : ?>
						<p class="mb-1"><?php echo $this->escape($unidade($s)); ?></p>
					<?php endif; ?>
					<?php if ($s->ds_justificativa) : ?>
						<p class="mb-1 text-muted">"<?php echo $this->escape($s->ds_justificativa); ?>"</p>
					<?php endif; ?>
					<p class="small text-muted">Pedido em <?php echo HTMLHelper::_('date', $s->dt_solicitacao, 'd/m/Y H:i'); ?></p>
					<form method="post" action="<?php echo Route::_('index.php?option=com_edital&task=solicitacaoacesso.decidir'); ?>" class="d-flex flex-wrap gap-2 align-items-center">
						<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
						<?php echo HTMLHelper::_('form.token'); ?>
						<button type="submit" name="decisao" value="aprovar" class="btn btn-sm btn-primary">Liberar</button>
						<input type="text" name="motivo" class="form-control form-control-sm" style="max-width: 24rem;" placeholder="Motivo (obrigatório para recusar)" aria-label="Motivo">
						<button type="submit" name="decisao" value="recusar" class="btn btn-sm btn-outline-danger">Recusar</button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ($this->decididas) : ?>
		<h3 class="section-title mt-4">Decididas recentemente</h3>
		<ul class="list-group">
			<?php foreach ($this->decididas as $s) : ?>
				<li class="list-group-item small">
					<span class="badge <?php echo $s->st_solicitacao === 'aprovada' ? 'badge-success' : 'badge-danger'; ?>"><?php echo $s->st_solicitacao === 'aprovada' ? 'Liberado' : 'Recusado'; ?></span>
					<?php echo $this->escape((AcessoHelper::PERFIS[$s->tp_perfil]['rotulo'] ?? $s->tp_perfil) . ' · ' . $s->no_usuario . ($unidade($s) !== '' ? ' · ' . $unidade($s) : '')); ?>
					· por <?php echo $this->escape($s->no_decisor ?? '—'); ?> em <?php echo HTMLHelper::_('date', $s->dt_decisao, 'd/m/Y'); ?>
					<?php if ($s->ds_motivo) : ?> · <?php echo $this->escape($s->ds_motivo); ?><?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
