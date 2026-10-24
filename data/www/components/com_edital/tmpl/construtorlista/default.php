<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css');
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formulariosgestao.css');

include __DIR__ . '/../parts/_menu_formularios_gestao.php';
?>
<div class="">
	
	<div class="d-flex justify-content-between mb-3">
		<a class="btn btn-light" href="<?php echo Route::_('index.php?option=com_edital&view=adesaogestao'); ?>">
			<i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
		</a>

		<a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_edital&view=construtoredital'); ?>">
			<i class="fas fa-plus" aria-hidden="true"></i> Novo Formulário
		</a>
	</div>

	<div class="formularios-gestao-list">
        <header class="formularios-gestao-header">
            <div>
                <p>Formulários disponíveis para construção e gerenciamento.</p>
            </div>
            <span class="formularios-gestao-count"><?php echo (int) $this->pagination->total; ?> formulários</span>
        </header>
		<?php foreach ((array) $this->items as $item) : ?>
			<article class="formulario-gestao-card">
				<?php $isRascunho = (int) $item->state !== 1; ?>
				<div class="formulario-gestao-card-top">
					<div>
						<span class="formulario-gestao-status <?php echo $isRascunho ? 'is-draft' : ($item->is_open ? 'is-open' : 'is-closed'); ?>">
							<i class="fas <?php echo $isRascunho ? 'fa-pencil-alt' : ($item->is_open ? 'fa-check-circle' : 'fa-clock'); ?>" aria-hidden="true"></i>
							<?php echo $isRascunho ? 'Rascunho' : ($item->is_open ? 'Aberto para preenchimento' : 'Encerrado'); ?>
						</span>
						<?php if (!empty($item->ds_versao)) : ?>
							<span class="formulario-gestao-version"><?php echo htmlspecialchars($item->ds_versao, ENT_QUOTES, 'UTF-8'); ?></span>
						<?php endif; ?>
					</div>
					<span class="formulario-gestao-state"><?php echo (int) $item->state === 1 ? 'Publicado' : 'Rascunho'; ?></span>
				</div>
				<h2><?php echo htmlspecialchars($item->no_edital, ENT_QUOTES, 'UTF-8'); ?></h2>
				<?php if (!empty($item->ds_edital)) : ?>
					<p class="formulario-gestao-description"><?php echo htmlspecialchars(mb_substr(strip_tags($item->ds_edital), 0, 180), ENT_QUOTES, 'UTF-8'); ?></p>
				<?php endif; ?>
				<div class="formulario-gestao-meta">
					<span><i class="far fa-calendar" aria-hidden="true"></i> <?php echo HTMLHelper::_('date', $item->dt_abertura, 'd/m/Y'); ?> a <?php echo HTMLHelper::_('date', $item->dt_encerramento, 'd/m/Y'); ?></span>
					<span><i class="far fa-file-alt" aria-hidden="true"></i> <?php echo (int) $item->tamanho['qtAgrupamentos']; ?> seções · <?php echo (int) $item->tamanho['qtPerguntas']; ?> quesitos</span>
				</div>

				<div class="formulario-gestao-card-actions mt-3">
                    <a class="btn btn-secondary" type="button" href="<?php echo Route::_('index.php?option=com_edital&view=construtoredital&id=' . (int) $item->id); ?>"><i class="fas fa-edit" aria-hidden="true"></i> Editar Formulário</a>
                    <a class="btn btn-primary" type="button" href="<?php echo Route::_('index.php?option=com_edital&view=construtorperguntas&id=' . (int) $item->id); ?>"><i class="fas fa-list" aria-hidden="true"></i> Editar seções e perguntas</a>
                </div>
			</article>
		<?php endforeach; ?>
	</div>

	<?php if (empty($this->items)) : ?>
		<div class="formularios-gestao-empty">Nenhum edital do tipo formulário foi encontrado.</div>
	<?php endif; ?>

	<?php if ($this->pagination->pagesTotal > 1) : ?>
		<div class="formularios-gestao-pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
	<?php endif; ?>
</div>
