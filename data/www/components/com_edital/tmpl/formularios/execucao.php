<?php
/**
 * Novo instrumento do executor: escolhe o instrumento e o projeto cadastrado pelo gestor.
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularios.css');

$execucao = $this->execucao;

// Escolher o instrumento já mostra os projetos. Fica num script à parte: a política de segurança
// do site (script-src-attr 'none') bloqueia handlers inline como onchange="...".
$this->document->addScriptDeclaration(
	'document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("execucao-instrumento");'
	. 'if(s){s.addEventListener("change",function(){if(s.value){s.form.submit();}});}});'
);
$itemid = \Joomla\CMS\Factory::getApplication()->input->getInt('Itemid');
?>
<div class="formularios-container">
	<p><a href="<?php echo $execucao->urlVoltar; ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar aos meus instrumentos</a></p>
	<h2 class="section-title"><?php echo $this->escape($execucao->edital->no_edital); ?></h2>

	<?php if (!$execucao->instrumentos) : ?>
		<div class="alert alert-info">
			Nenhum instrumento disponível ainda: a execução fica disponível depois que o gestor envia o formulário dele com os projetos do instrumento.
		</div>
	<?php else : ?>
		<form method="get" action="<?php echo Route::_('index.php'); ?>" class="formulario-card mb-3">
			<div class="card-body">
				<input type="hidden" name="option" value="com_edital">
				<input type="hidden" name="view" value="formularios">
				<input type="hidden" name="layout" value="execucao">
				<input type="hidden" name="edital_id" value="<?php echo (int) $execucao->edital->id; ?>">
				<?php if ($itemid) : ?>
					<input type="hidden" name="Itemid" value="<?php echo $itemid; ?>">
				<?php endif; ?>
				<label for="execucao-instrumento" class="form-label"><strong>1. Escolha o instrumento</strong></label>
				<div class="d-flex flex-wrap gap-2">
					<select id="execucao-instrumento" name="instrumento" class="form-select" style="max-width: 40rem;">
						<option value="">Selecione o instrumento</option>
						<?php foreach ($execucao->instrumentos as $codigo => $instrumento) : ?>
							<option value="<?php echo $this->escape($codigo); ?>"<?php echo $execucao->escolhido === $instrumento ? ' selected' : ''; ?>>
								<?php echo $this->escape($instrumento->rotuloTipo . ' ' . $codigo . ($instrumento->descricao !== '' ? ' - ' . mb_strimwidth($instrumento->descricao, 0, 110, '…') : '')); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="btn btn-secondary">Ver projetos</button>
				</div>
			</div>
		</form>

		<?php if ($execucao->escolhido) : ?>
			<div class="formulario-card">
				<div class="card-body">
					<p><strong>2. Escolha o projeto</strong></p>
					<?php if ($execucao->escolhido->descricao !== '') : ?>
						<p class="formulario-description"><?php echo $this->escape($execucao->escolhido->descricao); ?></p>
					<?php endif; ?>
					<?php if (!$execucao->projetos) : ?>
						<div class="alert alert-info mb-0">O gestor deste instrumento ainda não cadastrou projetos para execução.</div>
					<?php else : ?>
						<ul class="list-group">
							<?php foreach ($execucao->projetos as $projeto) : ?>
								<li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
									<div>
										<strong><?php echo $this->escape($projeto->rotulo); ?></strong>
										<?php if ($projeto->responsavel !== '') : ?>
											<br><small class="text-muted">Responsável: <?php echo $this->escape($projeto->responsavel); ?></small>
										<?php endif; ?>
									</div>
									<a href="<?php echo $this->escape($projeto->url); ?>" class="btn btn-sm btn-primary">
										<i class="fas fa-pen" aria-hidden="true"></i> Preencher a execução
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
