<?php
defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/adesaogestao.css');
$this->document->addStyleSheet(Uri::root() . 'media/com_edital/css/formulariosgestao.css');

include __DIR__ . '/../parts/_menu_formularios_gestao.php';
?>
<div class="formularios-construtor-container" id="construtor-perguntas">
	<a class="btn btn-light mb-3" href="<?php echo Route::_('index.php?option=com_edital&view=construtorlista'); ?>">
		<i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar
	</a>

	<div class="formularios-gestao-list">
		<header class="formularios-gestao-header">
			<div>
				<?php if (!$this->tituloNoTemplate) : ?>
					<h1>Seções e perguntas</h1>
				<?php endif; ?>
				<p>
					<?php echo htmlspecialchars($this->edital->no_edital, ENT_QUOTES, 'UTF-8'); ?>
					<span class="formulario-gestao-status ms-2" data-state-selo></span>
				</p>
			</div>
			<button type="button" class="btn btn-outline-secondary" data-acao="secao-adicionar">
				<i class="fas fa-plus" aria-hidden="true"></i> Adicionar seção
			</button>
		</header>

		<div class="cp-publicacao">
			<button type="button" class="btn" data-acao="publicar"></button>
		</div>

		<div data-alerta aria-live="polite"></div>

		<div data-secoes></div>

		<div class="cp-vazio" data-vazio hidden>Nenhuma seção cadastrada. Use "Adicionar seção" para começar.</div>

		<div class="cp-rodape">
			<span class="cp-sujo" data-sujo hidden>Há alterações não salvas.</span>
			<button type="button" class="btn" data-acao="publicar"></button>
			<button type="button" class="btn btn-primary" data-acao="salvar">
				<i class="fas fa-save" aria-hidden="true"></i> Salvar
			</button>
		</div>
	</div>
</div>

<template id="tpl-secao">
	<article class="cp-card cp-secao" data-secao>
		<header class="cp-card-head">
			<span class="cp-rotulo" data-rotulo></span>
			<div class="cp-acoes">
				<button type="button" class="cp-icone" data-acao="secao-subir" title="Mover para cima"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
				<button type="button" class="cp-icone" data-acao="secao-descer" title="Mover para baixo"><i class="fas fa-arrow-down" aria-hidden="true"></i></button>
				<button type="button" class="cp-icone" data-acao="secao-excluir" title="Excluir seção"><i class="far fa-trash-alt" aria-hidden="true"></i></button>
			</div>
		</header>
		<label class="cp-campo">
			<span>Título da seção</span>
			<input type="text" class="form-control" maxlength="255" data-campo="titulo">
		</label>
		<label class="cp-campo">
			<span>Descrição / instrução</span>
			<textarea class="form-control" rows="2" data-campo="descricao"></textarea>
		</label>
		<div class="cp-perguntas" data-perguntas></div>
		<button type="button" class="btn btn-outline-secondary btn-sm" data-acao="pergunta-adicionar">
			<i class="fas fa-plus" aria-hidden="true"></i> Adicionar pergunta
		</button>
	</article>
</template>

<template id="tpl-pergunta">
	<div class="cp-card cp-pergunta" data-pergunta>
		<header class="cp-card-head">
			<span class="cp-rotulo" data-rotulo></span>
			<div class="cp-acoes">
				<button type="button" class="cp-icone" data-acao="pergunta-subir" title="Mover para cima"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>
				<button type="button" class="cp-icone" data-acao="pergunta-descer" title="Mover para baixo"><i class="fas fa-arrow-down" aria-hidden="true"></i></button>
				<button type="button" class="cp-icone" data-acao="pergunta-duplicar" title="Duplicar pergunta"><i class="far fa-copy" aria-hidden="true"></i></button>
				<button type="button" class="cp-icone" data-acao="pergunta-excluir" title="Excluir pergunta"><i class="far fa-trash-alt" aria-hidden="true"></i></button>
			</div>
		</header>
		<label class="cp-campo">
			<span>Enunciado</span>
			<textarea class="form-control" rows="2" maxlength="255" data-campo="enunciado"></textarea>
		</label>
		<label class="cp-campo">
			<span>Texto de ajuda (opcional)</span>
			<textarea class="form-control" rows="2" data-campo="ajuda"></textarea>
		</label>
		<label class="cp-campo">
			<span>Tipo de resposta</span>
			<select class="form-select" data-campo="tipo"></select>
		</label>
		<div class="cp-campo" data-bloco-alternativas hidden>
			<span>Alternativas</span>
			<p class="alert alert-info small mb-2" data-bloco-dw hidden>
				<i class="fas fa-database" aria-hidden="true"></i>
				Alternativas sincronizadas com o data warehouse (fonte "<span data-dw-fonte></span>").
				Novas opções do data warehouse entram sozinhas; nomes editados e alternativas excluídas aqui são mantidos.
			</p>
			<div data-alternativas></div>
			<button type="button" class="btn btn-outline-secondary btn-sm" data-acao="alternativa-adicionar">
				<i class="fas fa-plus" aria-hidden="true"></i> Adicionar alternativa
			</button>
			<label class="form-check form-switch cp-obrigatoria" data-bloco-outro hidden>
				<input type="checkbox" class="form-check-input" role="switch" data-campo="permiteOutro">
				<span class="form-check-label">Permitir a opção "Outro" (texto livre)</span>
			</label>
		</div>
		<label class="form-check form-switch cp-obrigatoria">
			<input type="checkbox" class="form-check-input" role="switch" data-campo="obrigatoria">
			<span class="form-check-label">Resposta obrigatória</span>
		</label>
		<details class="cp-condicional" data-bloco-condicional>
			<summary>Lógica condicional <span class="badge bg-info text-dark" data-selo hidden>Condicional</span></summary>
			<div data-regras></div>
		</details>
	</div>
</template>

<template id="tpl-regra">
	<div class="cp-regra" data-regra>
		<label class="form-check form-switch" data-regra-chave>
			<input type="checkbox" class="form-check-input" role="switch" data-campo="regra-ativa">
			<span class="form-check-label" data-rotulo></span>
		</label>
		<div class="cp-regra-corpo" data-regra-corpo hidden>
			<label class="cp-campo">
				<span>Se a resposta da pergunta</span>
				<select class="form-select" data-campo="regra-origem"></select>
			</label>
			<div class="cp-campo" data-regra-valores-bloco hidden>
				<span>for qualquer uma destas:</span>
				<div data-regra-valores></div>
			</div>
			<p class="cp-aviso" data-regra-aviso hidden></p>
			<p class="cp-resumo" data-regra-resumo></p>
			<small class="text-muted"> São aceitas apenas perguntas de múltipla escolha inseridas anteriormente à pergunta atual.</small>
		</div>
		<div class="cp-regra-legada" data-regra-legada hidden>
			<span data-regra-legada-texto></span>
			<button type="button" class="btn btn-sm btn-outline-danger" data-acao="regra-remover-legada">Remover condição</button>
		</div>
	</div>
</template>

<template id="tpl-regra-valor">
	<label class="form-check">
		<input type="checkbox" class="form-check-input" data-campo="regra-valor">
		<span class="form-check-label" data-texto></span>
	</label>
</template>

<template id="tpl-alternativa">
	<div class="cp-alternativa" data-alternativa>
		<span class="cp-alternativa-num" data-num></span>
		<input type="text" class="form-control" maxlength="255" placeholder="Texto da alternativa" data-campo="alternativa">
		<button type="button" class="cp-icone" data-acao="alternativa-remover" title="Remover alternativa"><i class="fas fa-times" aria-hidden="true"></i></button>
	</div>
</template>
