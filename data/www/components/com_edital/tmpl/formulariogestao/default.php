<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css');
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formulariosgestao.css');

include __DIR__ . '/../parts/_menu_formularios_gestao.php';
?>
<div class="formularios-gestao-container">
	
<!-- 
	<form class="formularios-gestao-search" method="get" action="<?php echo Route::_('index.php'); ?>">
		<input type="hidden" name="option" value="com_edital">
		<input type="hidden" name="view" value="formulariogestao">
		<label for="formularios-gestao-search">Buscar edital</label>
		<div>
			<i class="fas fa-search" aria-hidden="true"></i>
			<input id="formularios-gestao-search" type="search" name="filter_search" value="<?php echo htmlspecialchars((string) $this->state->get('filter.search'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nome ou descrição">
			<button class="btn btn-primary" type="submit">Buscar</button>
		</div>
	</form> -->

	<div class="formularios-gestao-list">
        <header class="formularios-gestao-header">
            <div>
                <p>Formulários disponíveis para gestão e acompanhamento.</p>
            </div>
            <span class="formularios-gestao-count"><?php echo (int) $this->pagination->total; ?> formulários</span>
        </header>
		<?php foreach ((array) $this->items as $item) : ?>
			<article class="formulario-gestao-card">
				<div class="formulario-gestao-card-top">
					<div>
						<span class="formulario-gestao-status <?php echo $item->is_open ? 'is-open' : 'is-closed'; ?>">
							<i class="fas <?php echo $item->is_open ? 'fa-check-circle' : 'fa-clock'; ?>" aria-hidden="true"></i>
							<?php echo $item->is_open ? 'Aberto para preenchimento' : 'Encerrado'; ?>
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
                    <a class="btn btn-primary" type="button" href="<?php echo Route::_('index.php?option=com_edital&view=formularioadesoes&item_id=' . (int) $item->id); ?>">Ver respostas dos entes</a>
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