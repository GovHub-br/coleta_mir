<?php
/**
 * Acesso ao Coleta MIR: cadastro com pedido de perfis (sem login) ou "Meus acessos" (com login).
 *
 * @package    Com_Edital
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Comedital\Component\Edital\Site\Helper\AcessoHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formularios.css');

$user = Factory::getApplication()->getIdentity();
$dados = $this->dados;
$perfisPedidos = (array) ($dados['perfis'] ?? []);
$situacoes = ['pendente' => ['Aguardando liberação', 'badge-warning'], 'aprovada' => ['Liberado', 'badge-success'], 'recusada' => ['Recusado', 'badge-danger']];
$urlUnidade = Uri::root() . 'index.php?option=com_edital&task=solicitacaoacesso.unidade&' . Session::getFormToken() . '=1';

// Busca o nome da unidade (UG/CNPJ) nos instrumentos do DW enquanto a pessoa digita.
$this->document->addScriptDeclaration(<<<JS
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('[data-perfil-unidade]').forEach(function (campo) {
		var aviso = document.getElementById(campo.id + '-nome');
		var tipo = campo.getAttribute('data-perfil-unidade');
		var tamanho = tipo === 'ug' ? 6 : 14;
		var buscar = function () {
			var codigo = campo.value.replace(/\D/g, '');
			aviso.textContent = '';
			if (codigo.length !== tamanho) { return; }
			fetch('{$urlUnidade}&tipo=' + tipo + '&codigo=' + codigo, {credentials: 'same-origin'})
				.then(function (r) { return r.json(); })
				.then(function (r) {
					aviso.textContent = r.success
						? r.data.nome + ' (' + r.data.instrumentos + (r.data.instrumentos === 1 ? ' instrumento)' : ' instrumentos)')
						: (r.message || '');
					aviso.className = 'form-text ' + (r.success ? 'text-success' : 'text-danger');
				})
				.catch(function () {});
		};
		campo.addEventListener('input', buscar);
		buscar();
	});
	document.querySelectorAll('[data-perfil]').forEach(function (marcador) {
		var bloco = document.getElementById('unidade-' + marcador.value);
		var alternar = function () {
			if (bloco) {
				bloco.hidden = !marcador.checked;
				bloco.querySelectorAll('input').forEach(function (i) { i.required = marcador.checked; });
			}
		};
		marcador.addEventListener('change', alternar);
		alternar();
	});
});
JS);

$campoPerfis = function (string $nome, bool $multiplo) use ($perfisPedidos, $dados) {
	$html = '';

	foreach (AcessoHelper::PERFIS as $perfil => $config)
	{
		if ($perfil === 'mir' && $this->ehMir)
		{
			continue;
		}

		$id = 'perfil-' . $perfil;
		$marcado = in_array($perfil, $perfisPedidos, true) ? ' checked' : '';
		$html .= '<div class="form-check">'
			. '<input class="form-check-input" type="' . ($multiplo ? 'checkbox' : 'radio') . '" name="' . $nome . '" id="' . $id . '" value="' . $perfil . '" data-perfil' . $marcado . '>'
			. '<label class="form-check-label" for="' . $id . '">' . htmlspecialchars($config['rotulo']) . '</label></div>';

		if ($config['identificador'] !== '')
		{
			$rotulo = $config['identificador'] === 'ug' ? 'UG (unidade gestora no SIAFI, 6 dígitos)' : 'CNPJ do convenente';
			$campo = 'unidade_' . $perfil;
			$html .= '<div class="ms-4 mb-2" id="unidade-' . $perfil . '" hidden>'
				. '<label class="form-label small" for="' . $campo . '">' . $rotulo . '</label>'
				. '<input class="form-control form-control-sm" style="max-width: 20rem;" type="text" inputmode="numeric" name="' . $campo . '" id="' . $campo . '"'
				. ' maxlength="' . ($config['identificador'] === 'ug' ? 6 : 18) . '" data-perfil-unidade="' . $config['identificador'] . '"'
				. ' value="' . htmlspecialchars((string) ($dados[$campo] ?? '')) . '">'
				. '<div id="' . $campo . '-nome" class="form-text"></div></div>';
		}
	}

	return $html;
};
?>
<div class="formularios-container" style="max-width: 48rem;">

<?php if ($user->guest) : ?>
	<h2 class="section-title">Solicitar acesso</h2>
	<p>Crie a sua conta com o seu CPF e peça os perfis de que precisa. O acesso de cada perfil é liberado por um servidor do MIR.</p>
	<p class="text-muted small">Responsável por projeto (executor) não precisa pedir perfil: basta criar a conta com o CPF que o gestor informou no projeto.</p>

	<form method="post" action="<?php echo Route::_('index.php?option=com_edital&task=solicitacaoacesso.cadastrar'); ?>" class="formulario-card">
		<div class="card-body">
			<div class="mb-3">
				<label class="form-label" for="acesso-nome">Nome completo *</label>
				<input class="form-control" type="text" name="nome" id="acesso-nome" required maxlength="255" value="<?php echo $this->escape($dados['nome'] ?? ''); ?>">
			</div>
			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="acesso-cpf">CPF *</label>
					<input class="form-control" type="text" name="cpf" id="acesso-cpf" required inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?php echo $this->escape($dados['cpf'] ?? ''); ?>">
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="acesso-email">E-mail *</label>
					<input class="form-control" type="email" name="email" id="acesso-email" required maxlength="100" value="<?php echo $this->escape($dados['email'] ?? ''); ?>">
				</div>
			</div>
			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="acesso-senha">Senha * <small class="text-muted">(mínimo de 8 caracteres)</small></label>
					<input class="form-control" type="password" name="senha" id="acesso-senha" required minlength="8" autocomplete="new-password">
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="acesso-senha2">Confirme a senha *</label>
					<input class="form-control" type="password" name="senha2" id="acesso-senha2" required minlength="8" autocomplete="new-password">
				</div>
			</div>
			<fieldset class="mb-3">
				<legend class="form-label fs-6">Perfis que você precisa (opcional para executores)</legend>
				<?php echo $campoPerfis('perfis[]', true); ?>
			</fieldset>
			<div class="mb-3">
				<label class="form-label" for="acesso-justificativa">Informações para quem vai liberar (cargo, órgão, motivo)</label>
				<textarea class="form-control" name="justificativa" id="acesso-justificativa" rows="3" maxlength="2000"><?php echo $this->escape($dados['justificativa'] ?? ''); ?></textarea>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
			<button type="submit" class="btn btn-primary">Criar conta e solicitar acesso</button>
			<a class="ms-2" href="<?php echo Route::_('index.php?option=com_users&view=login'); ?>">Já tenho conta</a>
		</div>
	</form>

<?php else : ?>
	<h2 class="section-title">Meus acessos</h2>

	<?php if ($this->ehMir) : ?>
		<p><span class="badge badge-success">Liberado</span> Servidor do MIR</p>
	<?php endif; ?>

	<?php if ($this->solicitacoes) : ?>
		<ul class="list-group mb-4">
			<?php foreach ($this->solicitacoes as $solicitacao) : ?>
				<?php [$rotuloSituacao, $classe] = $situacoes[$solicitacao->st_solicitacao] ?? [$solicitacao->st_solicitacao, 'badge-info']; ?>
				<li class="list-group-item">
					<span class="badge <?php echo $classe; ?>"><?php echo $this->escape($rotuloSituacao); ?></span>
					<strong><?php echo $this->escape(AcessoHelper::PERFIS[$solicitacao->tp_perfil]['rotulo'] ?? $solicitacao->tp_perfil); ?></strong>
					<?php if ($solicitacao->co_identificador) : ?>
						· <?php echo $this->escape(strtoupper($solicitacao->tp_identificador) . ' ' . $solicitacao->co_identificador . ($solicitacao->no_unidade ? ' - ' . $solicitacao->no_unidade : '')); ?>
					<?php endif; ?>
					<br><small class="text-muted">Pedido em <?php echo HTMLHelper::_('date', $solicitacao->dt_solicitacao, 'd/m/Y'); ?>
						<?php if ($solicitacao->ds_motivo) : ?> · <?php echo $this->escape($solicitacao->ds_motivo); ?><?php endif; ?>
					</small>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif (!$this->ehMir) : ?>
		<div class="alert alert-info">Você ainda não pediu nenhum perfil. Se você é responsável por um projeto (executor), não precisa: o projeto aparece em "Meus instrumentos" quando o gestor informa o seu CPF.</div>
	<?php endif; ?>

	<form method="post" action="<?php echo Route::_('index.php?option=com_edital&task=solicitacaoacesso.solicitar'); ?>" class="formulario-card">
		<div class="card-body">
			<h3 class="formulario-title">Pedir um perfil</h3>
			<fieldset class="mb-3">
				<?php echo $campoPerfis('perfis[]', true); ?>
			</fieldset>
			<div class="mb-3">
				<label class="form-label" for="acesso-justificativa">Informações para quem vai liberar (cargo, órgão, motivo)</label>
				<textarea class="form-control" name="justificativa" id="acesso-justificativa" rows="2" maxlength="2000"></textarea>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
			<button type="submit" class="btn btn-primary">Solicitar</button>
			<a class="ms-2" href="<?php echo Route::_('index.php?option=com_edital&view=formularios'); ?>">Voltar aos meus instrumentos</a>
		</div>
	</form>
<?php endif; ?>

</div>
