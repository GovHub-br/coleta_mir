<?php
/**
 * @version    CVS: 1.0.0
 * @package    Com_Edital
 * @author     Railson Rodrigues leite <railsonrdr@hotmail.com>
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularios.css');

// Menu do "Novo instrumento" quando o perfil tem mais de um formulário.
HTMLHelper::_('bootstrap.dropdown', '.dropdown-toggle');

include __DIR__ . '/../parts/_menu_formularios_ente.php';
?>

<div class="formularios-container">

	<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
		<h2 class="section-title mb-0">Meus instrumentos</h2>

		<?php if (count($this->novos) === 1) : ?>
			<a href="<?php echo $this->novos[0]->url; ?>" class="btn btn-primary">
				<i class="fas fa-plus" aria-hidden="true"></i> Novo instrumento
			</a>
		<?php elseif ($this->novos) : ?>
			<div class="dropdown">
				<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
					<i class="fas fa-plus" aria-hidden="true"></i> Novo instrumento
				</button>
				<ul class="dropdown-menu dropdown-menu-end">
					<?php foreach ($this->novos as $novo) : ?>
						<li><a class="dropdown-item" href="<?php echo $novo->url; ?>"><?php echo $this->escape($novo->titulo); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<?php if (empty($this->instrumentos) && empty($this->outrasRespostas)) : ?>
		<div class="alert alert-info">
			<?php if ($this->novos) : ?>
				Você ainda não preencheu nenhum instrumento. Clique em <strong>Novo instrumento</strong> para começar.
			<?php else : ?>
				Nenhum instrumento por aqui ainda. Quando o gestor de um TED ou convênio cadastrar o seu CPF como responsável de um projeto, ele aparece nesta página para você preencher a execução.
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="formularios-list">
		<?php foreach ($this->instrumentos as $instrumento) : ?>
			<div class="formulario-card">
				<div class="card-body">
					<h3 class="formulario-title">
						<?php echo $this->escape($instrumento->rotuloTipo . ' ' . $instrumento->codigo); ?>
					</h3>
					<?php if ($instrumento->descricao !== '') : ?>
						<p class="formulario-description"><?php echo $this->escape($instrumento->descricao); ?></p>
					<?php endif; ?>
					<?php if ($instrumento->projetos) : ?>
						<p class="mb-1"><strong>Projetos sob sua responsabilidade</strong></p>
						<ul class="list-group mb-3">
							<?php foreach ($instrumento->projetos as $projeto) : ?>
								<li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
									<div>
										<strong><?php echo $this->escape($projeto->rotulo); ?></strong>
										<br><small class="text-muted">Cadastrado por <?php echo $this->escape($projeto->gestor); ?> · <?php echo $this->escape($projeto->formulario); ?></small>
									</div>
									<a href="<?php echo $this->escape($projeto->url); ?>" class="btn btn-sm btn-primary">
										<i class="fas fa-plus" aria-hidden="true"></i> Preencher a execução
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ($instrumento->respostas) : ?>
						<?php $respostas = $instrumento->respostas; include __DIR__ . '/default_respostas.php'; ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php foreach ($this->outrasRespostas as $grupo) : ?>
			<div class="formulario-card">
				<div class="card-body">
					<h3 class="formulario-title"><?php echo $this->escape($grupo->formulario); ?></h3>
					<p class="formulario-description">Preenchimentos ainda sem instrumento escolhido.</p>
					<?php $respostas = $grupo->respostas; include __DIR__ . '/default_respostas.php'; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

</div>
