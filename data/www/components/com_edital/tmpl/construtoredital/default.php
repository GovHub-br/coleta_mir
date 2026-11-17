<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css');
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formulariosgestao.css');

include __DIR__ . '/../parts/_menu_formularios_gestao.php';

$userId = Factory::getApplication()->getIdentity()->id;
?>
<div class="formularios-gestao-container">
	<form id="adminForm" name="adminForm" method="post" action="<?php echo Route::_('index.php?option=com_edital&task=construtoredital.save'); ?>" class="form-horizontal">
		
		<?php if ($this->item->id) : ?>
			<a class="btn btn-light" href="<?php echo Route::_('index.php?option=com_edital&view=construtorlista'); ?>">
				<i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
			</a>
		<?php endif; ?>

		<div class="formularios-gestao-list">
			<header class="formularios-gestao-header">
				<div>
					<h1><?php echo $this->item->id ? 'Editar Formulário' : 'Criar Novo Formulário'; ?></h1>
					<p><?php echo $this->item->id ? 'Modifique os dados do formulário abaixo.' : 'Preencha os dados para criar um novo formulário.'; ?></p>
				</div>
			</header>

			<div class="formulario-form">
				
				<!-- Título -->
				<div class="form-group row mb-3">
					<label for="no_edital" class="col-md-3 col-form-label">
						Título <span class="text-danger">*</span>
					</label>
					<div class="col-md-9">
						<input 
							type="text" 
							id="no_edital" 
							name="no_edital" 
							class="form-control" 
							placeholder="Digite o título do formulário"
							value="<?php echo htmlspecialchars($this->item->no_edital, ENT_QUOTES, 'UTF-8'); ?>"
							required
						/>
					</div>
				</div>

				<!-- Data de início -->
				<div class="form-group row mb-3">
					<label for="dt_abertura" class="col-md-3 col-form-label">
						Data de início <span class="text-danger">*</span>
					</label>
					<div class="col-md-9">
						<?php 
						echo HTMLHelper::_('calendar', $this->item->dt_abertura, 'dt_abertura', 'dt_abertura', '%d/%m/%Y %H:%M', array(
							'class' => 'form-control',
							'required' => 'required'
						));
						?>
					</div>
				</div>

				<!-- Data de término -->
				<div class="form-group row mb-3">
					<label for="dt_encerramento" class="col-md-3 col-form-label">
						Data de término <span class="text-danger">*</span>
					</label>
					<div class="col-md-9">
						<?php 
						echo HTMLHelper::_('calendar', $this->item->dt_encerramento, 'dt_encerramento', 'dt_encerramento', '%d/%m/%Y %H:%M', array(
							'class' => 'form-control',
							'required' => 'required'
						));
						?>
					</div>
				</div>

				<!-- Descrição -->
				<div class="form-group row mb-3">
					<label for="ds_edital" class="col-md-3 col-form-label">
						Descrição
					</label>
					<div class="col-md-9">
						<textarea 
							id="ds_edital" 
							name="ds_edital" 
							class="form-control" 
							rows="6"
							placeholder="Descreva o formulário (opcional)"
						><?php echo htmlspecialchars($this->item->ds_edital, ENT_QUOTES, 'UTF-8'); ?></textarea>
					</div>
				</div>

				<!-- Versão do Edital -->
				<div class="form-group row mb-3">
					<label for="ds_versao" class="col-md-3 col-form-label">
						Versão
					</label>
					<div class="col-md-9">
						<input 
							type="text" 
							id="ds_versao" 
							name="ds_versao" 
							class="form-control" 
							value="<?php echo htmlspecialchars($this->item->ds_versao ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
							placeholder="Exemplo: v1.0 - ano de referência 2026"
						/>
					</div>
				</div>

				<!-- Perfis que podem iniciar o formulário pelo botão "Novo instrumento" -->
				<?php $gruposMarcados = \Comedital\Component\Edital\Site\Helper\PerfilHelper::getGruposFormulario($this->item); ?>
				<div class="form-group row mb-3">
					<span class="col-md-3 col-form-label">Perfis que podem iniciar</span>
					<div class="col-md-9">
						<input type="hidden" name="co_grupos" value="" />
						<?php foreach (\Comedital\Component\Edital\Site\Helper\PerfilHelper::getGruposPerfil() as $grupo) : ?>
							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="co_grupos[]" id="co_grupos_<?php echo (int) $grupo->id; ?>"
									value="<?php echo (int) $grupo->id; ?>"<?php echo in_array((int) $grupo->id, $gruposMarcados, true) ? ' checked' : ''; ?>>
								<label class="form-check-label" for="co_grupos_<?php echo (int) $grupo->id; ?>"><?php echo htmlspecialchars($grupo->title, ENT_QUOTES, 'UTF-8'); ?></label>
							</div>
						<?php endforeach; ?>
						<small class="form-text text-muted">Quem está nesses grupos vê este formulário no botão "Novo instrumento" da lista de formulários. Sem nenhum marcado, o formulário não aparece no botão.</small>
					</div>
				</div>

				<!-- Hidden fields -->
				<?php $isPublicado = (int) ($this->item->state ?? 0) === 1; ?>
				<input type="hidden" name="state" value="<?php echo $isPublicado ? 1 : 0; ?>" />
				<input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>" />
				<input type="hidden" name="tp_edital" value="formulario" />
				<input type="hidden" name="created_by" value="<?php echo (int) $userId; ?>" />
				<input type="hidden" id="tp_esfera_solicitante" name="tp_esfera_solicitante" class="form-control" value="<?php echo htmlspecialchars($this->item->tp_esfera_solicitante ?? '', ENT_QUOTES, 'UTF-8'); ?>">
				<?php echo HTMLHelper::_('form.token'); ?>

			</div>

			<!-- Action buttons -->
			<div class="formulario-cadastro-actions mt-4" style="justify-content: space-between;">
				<div>
					<?php if ($this->item->id) : ?>
						<a href="<?php echo Route::_('index.php?option=com_edital&view=construtorperguntas&id=' . (int) $this->item->id); ?>" class="btn btn-outline-primary">
							<i class="fas fa-list" aria-hidden="true"></i> Seções e perguntas
						</a>
					<?php 
						endif; 
					 // Segundo submit com o mesmo name sobrescreve o hidden "state". 
					if ($isPublicado) : ?>
						<button type="submit" name="state" value="0" class="btn btn-warning">
							<i class="fas fa-undo" aria-hidden="true"></i> Voltar para rascunho
						</button>
					<?php else : ?>
						<button type="submit" name="state" value="1" class="btn btn-success">
							<i class="fas fa-check" aria-hidden="true"></i> Publicar
						</button>
					<?php endif; ?>
				</div>
				<div>
					<a href="<?php echo Route::_('index.php?option=com_edital&view=construtorlista'); ?>" class="btn btn-secondary">
						<i class="fas fa-times" aria-hidden="true"></i> Cancelar
					</a>
					<button type="submit" class="btn btn-primary">
						<i class="fas fa-save" aria-hidden="true"></i> Salvar dados
					</button>
					
				</div>
			</div>

		</div>
	</form>
</div>
