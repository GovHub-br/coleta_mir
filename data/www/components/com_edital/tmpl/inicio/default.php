<?php
/**
 * Página inicial do Coleta MIR.
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/inicio.css?v=' . @filemtime(JPATH_ROOT . '/media/com_edital/css/inicio.css'));

$link = static fn (string $view) => Route::_('index.php?option=com_edital&view=' . $view);
$plural = static fn (int $n, string $um, string $varios) => $n . ' ' . ($n === 1 ? $um : $varios);
$primeiroNome = $this->user->guest ? '' : strtok(trim($this->user->name), ' ');
?>
<div class="coleta-inicio">

	<div class="row g-3 coleta-inicio-topo">
		<div class="col-lg-8">
			<section class="coleta-inicio-hero">
				<p class="coleta-inicio-sobretitulo">Ministério da Igualdade Racial</p>
				<h1>Coleta MIR</h1>
				<p class="coleta-inicio-lead">
					Acompanhamento da execução dos TEDs e convênios do MIR. Aqui o MIR, os gestores dos instrumentos
					e os responsáveis pelos projetos registram como cada instrumento está sendo executado: projetos apoiados,
					atividades realizadas e pessoas beneficiadas.
				</p>
			</section>
		</div>

		<div class="col-lg-4">
			<div class="coleta-inicio-acoes">
				<?php if ($this->user->guest) : ?>
					<button type="button" class="coleta-inicio-acao coleta-inicio-acao-principal" data-bs-toggle="modal" data-bs-target="#loginModal">
						<i class="fas fa-sign-in-alt" aria-hidden="true"></i>
						<span><strong>Entrar</strong><small>Com CPF e senha</small></span>
					</button>
					<a class="coleta-inicio-acao" href="<?php echo $link('solicitacaoacesso'); ?>">
						<i class="fas fa-user-plus" aria-hidden="true"></i>
						<span><strong>Solicitar acesso</strong><small>Crie sua conta e peça seu perfil</small></span>
					</a>
				<?php else : ?>
					<a class="coleta-inicio-acao coleta-inicio-acao-principal" href="<?php echo $link('formularios'); ?>">
						<i class="fas fa-folder-open" aria-hidden="true"></i>
						<span><strong>Meus instrumentos</strong><small>Instrumentos e projetos para preencher</small></span>
					</a>
					<a class="coleta-inicio-acao" href="<?php echo $link('solicitacaoacesso'); ?>">
						<i class="fas fa-user-check" aria-hidden="true"></i>
						<span><strong>Meus acessos</strong><small>Perfis liberados e pedidos</small></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if (!$this->user->guest) : ?>
		<section class="coleta-inicio-secao" aria-labelledby="coleta-situacao">
			<h2 id="coleta-situacao">Olá, <?php echo $this->escape($primeiroNome); ?></h2>
			<div class="row g-3">

				<?php if ($this->ehMir) : ?>
					<div class="col-md-6">
						<div class="coleta-inicio-aviso">
							<i class="fas fa-user-shield" aria-hidden="true"></i>
							<div>
								<strong>Perfil MIR</strong>
								<p class="mb-2">Cadastre um instrumento em "Novo instrumento", dentro de Meus instrumentos.</p>
								<?php if ($this->paraDecidir) : ?>
									<a href="<?php echo $link('solicitacoesacesso'); ?>"><?php echo $plural($this->paraDecidir, 'pedido de acesso aguarda', 'pedidos de acesso aguardam'); ?> sua decisão</a>
								<?php else : ?>
									<a href="<?php echo $link('solicitacoesacesso'); ?>">Nenhum pedido de acesso aguardando decisão</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ($this->unidades) : ?>
					<div class="col-md-6">
						<div class="coleta-inicio-aviso">
							<i class="fas fa-building" aria-hidden="true"></i>
							<div>
								<strong>Liberado para você</strong>
								<ul class="mb-2">
									<?php foreach ($this->unidades as $unidade) : ?>
										<li><?php echo $this->escape(strtoupper(AcessoHelper::PERFIS[$unidade['perfil']]['identificador']) . ' ' . $unidade['codigo'] . ' - ' . $unidade['nome']); ?></li>
									<?php endforeach; ?>
								</ul>
								<a href="<?php echo $link('formularios'); ?>"><?php echo $plural($this->totalInstrumentos, 'instrumento', 'instrumentos'); ?> para acompanhar</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ($this->pendentes) : ?>
					<div class="col-md-6">
						<div class="coleta-inicio-aviso coleta-inicio-aviso-espera">
							<i class="fas fa-hourglass-half" aria-hidden="true"></i>
							<div>
								<strong>Aguardando o MIR</strong>
								<ul class="mb-2">
									<?php foreach ($this->pendentes as $pedido) : ?>
										<li><?php echo $this->escape((AcessoHelper::PERFIS[$pedido->tp_perfil]['rotulo'] ?? $pedido->tp_perfil)
											. ($pedido->co_identificador ? ' - ' . strtoupper($pedido->tp_identificador) . ' ' . $pedido->co_identificador : '')); ?></li>
									<?php endforeach; ?>
								</ul>
								<a href="<?php echo $link('solicitacaoacesso'); ?>">Acompanhar em Meus acessos</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if (!$this->ehMir && !$this->unidades && !$this->pendentes) : ?>
					<div class="col-md-6">
						<div class="coleta-inicio-aviso">
							<i class="fas fa-info-circle" aria-hidden="true"></i>
							<div>
								<strong>Responsável por projeto?</strong>
								<p class="mb-2">Os projetos ligados ao seu CPF aparecem em Meus instrumentos, sem precisar pedir perfil.</p>
								<strong>Gestor de TED ou convenente?</strong>
								<p class="mb-2">Peça acesso à sua UG ou ao CNPJ do convenente.</p>
								<a href="<?php echo $link('solicitacaoacesso'); ?>">Pedir acesso</a>
							</div>
						</div>
					</div>
				<?php endif; ?>

			</div>
		</section>
	<?php endif; ?>

	<section class="coleta-inicio-secao" aria-labelledby="coleta-quem">
		<h2 id="coleta-quem">Quem preenche o quê</h2>
		<div class="coleta-inicio-fluxo">
			<img src="<?php echo Uri::root() . 'media/com_edital/images/inicio/quem-preenche.svg?v=' . @filemtime(JPATH_ROOT . '/media/com_edital/images/inicio/quem-preenche.svg'); ?>" width="1200" height="640" loading="lazy"
				alt="O servidor do MIR cadastra o instrumento em Novo instrumento. O gestor de TED ou convenente abre o instrumento pela UG ou CNPJ, informa execução e contrapartida e lista os projetos com o CPF do responsável; quando a própria unidade executa, ele mesmo registra as atividades. O responsável pelo projeto entra com o CPF, abre o projeto em Meus instrumentos e registra cada atividade e os beneficiários.">
		</div>
	</section>

	<section class="coleta-inicio-secao" aria-labelledby="coleta-passos">
		<h2 id="coleta-passos">Como começar</h2>
		<div class="coleta-inicio-fluxo">
			<img src="<?php echo Uri::root() . 'media/com_edital/images/inicio/como-comecar.svg?v=' . @filemtime(JPATH_ROOT . '/media/com_edital/images/inicio/como-comecar.svg'); ?>" width="1200" height="560" loading="lazy"
				alt="Crie a conta em Solicitar acesso, com CPF, nome, e-mail e senha. Gestor de TED informa a UG, convenente informa o CNPJ e servidor do MIR pede o perfil MIR. O MIR confere o pedido em Solicitações de acesso e libera ou recusa com motivo; o resultado aparece em Meus acessos. Responsável por projeto não pede perfil e preenche direto em Meus instrumentos.">
		</div>
	</section>

</div>
