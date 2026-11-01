(function () {
	'use strict';

	const opcoes = Joomla.getOptions('com_edital.construtor');
	const root = document.getElementById('construtor-perguntas');

	if (!opcoes || !root) {
		return;
	}

	const contSecoes = root.querySelector('[data-secoes]');
	const avisoVazio = root.querySelector('[data-vazio]');
	const avisoSujo = root.querySelector('[data-sujo]');
	const areaAlerta = root.querySelector('[data-alerta]');
	const botaoSalvar = root.querySelector('[data-acao="salvar"]');
	const botoesPublicar = root.querySelectorAll('[data-acao="publicar"]');
	const seloState = root.querySelector('[data-state-selo]');

	let secoes = clonar(opcoes.secoes || []);
	let sujo = false;
	let salvando = false;
	let publicando = false;
	let state = parseInt(opcoes.state, 10) === 1 ? 1 : 0;

	function clonar(valor) {
		return JSON.parse(JSON.stringify(valor));
	}

	function novoTemplate(id) {
		return document.getElementById(id).content.firstElementChild.cloneNode(true);
	}

	function posicao(el) {
		return Array.prototype.indexOf.call(el.parentElement.children, el);
	}

	function ehSelecao(tipo) {
		return opcoes.selecao.indexOf(tipo) !== -1;
	}

	function aceitaOutro(tipo) {
		return opcoes.comOutro.indexOf(tipo) !== -1;
	}

	const REGRAS = ['exibir', 'ocultar'];
	const ROTULO_REGRA = {
		exibir: 'Exibir esta pergunta somente se…',
		ocultar: 'Ocultar esta pergunta se…'
	};

	function condicaoVazia() {
		return { origem: null, valores: [], legado: false, ativa: false };
	}

	// Identifica a pergunta antes de ela ter id; o servidor troca pelo id real ao gravar.
	function novaChave() {
		return 'n' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
	}

	function prepararEstado(lista) {
		lista.forEach(function (s) {
			s.perguntas.forEach(function (p) {
				REGRAS.forEach(function (nome) {
					p[nome] = p[nome] || condicaoVazia();
					p[nome].ativa = !!p[nome].origem;
				});
			});
		});

		return lista;
	}

	function novaPergunta() {
		return {
			id: 0,
			chave: novaChave(),
			enunciado: '',
			ajuda: '',
			tipo: 'linha',
			obrigatoria: false,
			alternativas: [],
			permiteOutro: false,
			exibir: condicaoVazia(),
			ocultar: condicaoVazia()
		};
	}

	function truncar(texto, max) {
		texto = texto || '(sem enunciado)';
		return texto.length > max ? texto.slice(0, max - 1) + '…' : texto;
	}

	function paraCadaPergunta(fn) {
		secoes.forEach(function (s, i) {
			s.perguntas.forEach(function (p, j) {
				fn(p, i, j);
			});
		});
	}

	function paraCadaRegra(fn) {
		paraCadaPergunta(function (p) {
			REGRAS.forEach(function (nome) {
				fn(p[nome], nome, p);
			});
		});
	}

	function localizar(chave) {
		let achada = null;

		paraCadaPergunta(function (p, i, j) {
			if (p.chave === chave) {
				achada = { pergunta: p, iSecao: i, iPergunta: j };
			}
		});

		return achada;
	}

	function rotuloPergunta(iSecao, iPergunta, p) {
		return 'Seção ' + (iSecao + 1) + ' · Pergunta ' + (iPergunta + 1) + ': ' + truncar(p.enunciado, 60);
	}

	// Apenas perguntas anteriores e com alternativas podem ser origem de uma regra.
	function origensPossiveis(iSecao, iPergunta) {
		const lista = [];

		paraCadaPergunta(function (p, i, j) {
			if ((i < iSecao || (i === iSecao && j < iPergunta)) && ehSelecao(p.tipo)) {
				lista.push({ chave: p.chave, rotulo: rotuloPergunta(i, j, p) });
			}
		});

		return lista;
	}

	function valoresDisponiveis(p) {
		const lista = p.alternativas
			.map(function (a) { return a.trim(); })
			.filter(function (a) { return a !== ''; })
			.map(function (a) { return { valor: a, texto: a }; });

		if (p.permiteOutro && aceitaOutro(p.tipo)) {
			lista.push({ valor: opcoes.outroValor, texto: 'Outro' });
		}

		return lista;
	}

	function removerValorDasRegras(chave, valor) {
		paraCadaRegra(function (cond) {
			if (cond.origem === chave) {
				cond.valores = cond.valores.filter(function (v) { return v !== valor; });
			}
		});
	}

	function renomearValorNasRegras(chave, antigo, novo) {
		paraCadaRegra(function (cond) {
			if (cond.origem === chave) {
				cond.valores = cond.valores.map(function (v) { return v === antigo ? novo : v; });
			}
		});
	}

	// Regras de perguntas fora do conjunto que usam alguma das chaves como origem.
	function regrasDependentes(chaves) {
		const lista = [];

		paraCadaRegra(function (cond, nome, p) {
			if (cond.origem && chaves.indexOf(cond.origem) !== -1 && chaves.indexOf(p.chave) === -1) {
				lista.push(cond);
			}
		});

		return lista;
	}

	function limparRegrasDe(chaves) {
		regrasDependentes(chaves).forEach(function (cond) {
			Object.assign(cond, condicaoVazia());
		});
	}

	function mensagemExclusao(base, chaves) {
		const n = regrasDependentes(chaves).length;

		return n ? base + '\n' + n + ' regra(s) condicional(is) que dependem dela(s) serão removidas.' : base;
	}

	function atualizarSelo(elPergunta, pergunta) {
		elPergunta.querySelector('[data-selo]').hidden = !REGRAS.some(function (nome) {
			return pergunta[nome].origem || pergunta[nome].legado;
		});
	}

	function atualizarRegra(regra, pergunta, nome, iSecao, iPergunta) {
		const cond = pergunta[nome];
		const corpo = regra.querySelector('[data-regra-corpo]');

		regra.querySelector('[data-regra-chave]').hidden = cond.legado;
		regra.querySelector('[data-regra-legada]').hidden = !cond.legado;

		if (cond.legado) {
			regra.querySelector('[data-regra-legada-texto]').textContent = cond.aviso
				|| 'Condição criada fora do construtor (pergunta de origem em outro formulário ou removida). Ela será mantida.';
			corpo.hidden = true;
			return;
		}

		regra.querySelector('[data-campo="regra-ativa"]').checked = cond.ativa;
		corpo.hidden = !cond.ativa;

		if (!cond.ativa) {
			return;
		}

		const itens = origensPossiveis(iSecao, iPergunta);
		const atual = cond.origem ? localizar(cond.origem) : null;
		const naLista = itens.some(function (o) { return o.chave === cond.origem; });

		if (cond.origem && !naLista && atual) {
			itens.push({ chave: cond.origem, rotulo: rotuloPergunta(atual.iSecao, atual.iPergunta, atual.pergunta) });
		}

		const select = regra.querySelector('[data-campo="regra-origem"]');
		const assinatura = itens.map(function (o) { return o.chave; }).join('|');

		if (select.dataset.assinatura !== assinatura) {
			select.replaceChildren(new Option('Selecione a pergunta…', ''));
			itens.forEach(function (o) { select.add(new Option(o.rotulo, o.chave)); });
			select.dataset.assinatura = assinatura;
		} else {
			itens.forEach(function (o, k) { select.options[k + 1].textContent = o.rotulo; });
		}

		select.value = cond.origem || '';

		const origem = atual ? atual.pergunta : null;
		const disponiveis = origem ? valoresDisponiveis(origem) : [];
		const listaValores = regra.querySelector('[data-regra-valores]');
		const assinaturaValores = (cond.origem || '') + '|' + disponiveis.map(function (d) { return d.valor; }).join('\u0001');

		regra.querySelector('[data-regra-valores-bloco]').hidden = !origem;

		if (listaValores.dataset.assinatura !== assinaturaValores) {
			listaValores.replaceChildren(...disponiveis.map(function (d) {
				const item = novoTemplate('tpl-regra-valor');
				item.querySelector('input').value = d.valor;
				item.querySelector('[data-texto]').textContent = d.texto;
				return item;
			}));
			listaValores.dataset.assinatura = assinaturaValores;
		}

		listaValores.querySelectorAll('input').forEach(function (inp) {
			inp.checked = cond.valores.indexOf(inp.value) !== -1;
		});

		let aviso = '';

		if (cond.origem && !naLista) {
			aviso = 'A pergunta de origem precisa vir antes desta. Reordene ou escolha outra.';
		} else if (origem && disponiveis.length === 0) {
			aviso = 'A pergunta de origem ainda não tem alternativas preenchidas.';
		} else if (origem && cond.valores.length === 0) {
			aviso = 'Marque ao menos uma alternativa.';
		}

		const avisoEl = regra.querySelector('[data-regra-aviso]');
		avisoEl.textContent = aviso;
		avisoEl.hidden = aviso === '';

		regra.querySelector('[data-regra-resumo]').textContent = origem && cond.valores.length
			? 'Esta pergunta ' + (nome === 'exibir' ? 'aparece' : 'fica oculta') + ' quando "' + truncar(origem.enunciado, 50) + '" for '
				+ cond.valores.map(function (v) { return v === opcoes.outroValor ? 'Outro' : v; }).join(' ou ') + '.'
			: '';
	}

	function renderRegras(el, pergunta, iSecao, iPergunta) {
		const cont = el.querySelector('[data-regras]');

		REGRAS.forEach(function (nome) {
			const regra = novoTemplate('tpl-regra');

			regra.dataset.regra = nome;
			regra.querySelector('[data-rotulo]').textContent = ROTULO_REGRA[nome];
			cont.appendChild(regra);
			atualizarRegra(regra, pergunta, nome, iSecao, iPergunta);
		});

		const temRegra = REGRAS.some(function (nome) { return pergunta[nome].origem || pergunta[nome].legado; });

		atualizarSelo(el, pergunta);
		el.querySelector('[data-bloco-condicional]').open = pergunta.condAberto !== undefined ? pergunta.condAberto : !!temRegra;
	}

	// Mantém rótulos, alternativas e avisos das regras em dia sem redesenhar a tela.
	function refrescarRegras() {
		root.querySelectorAll('[data-pergunta]').forEach(function (elPergunta) {
			const c = contexto(elPergunta);

			elPergunta.querySelectorAll('[data-regra]').forEach(function (regra) {
				atualizarRegra(regra, c.pergunta, regra.dataset.regra, c.iSecao, c.iPergunta);
			});
			atualizarSelo(elPergunta, c.pergunta);
		});
	}

	function alterarRegra(el, campo) {
		const c = contexto(el);
		const cond = c.pergunta[c.regra];

		if (campo === 'regra-ativa') {
			cond.ativa = el.checked;

			if (!el.checked) {
				cond.origem = null;
				cond.valores = [];
			}
		} else if (campo === 'regra-origem') {
			cond.origem = el.value || null;
			cond.valores = [];
		} else {
			cond.valores = Array.from(c.elRegra.querySelectorAll('[data-campo="regra-valor"]:checked'))
				.map(function (inp) { return inp.value; });
		}

		atualizarRegra(c.elRegra, c.pergunta, c.regra, c.iSecao, c.iPergunta);
		atualizarSelo(c.elPergunta, c.pergunta);
		marcarSujo(true);
	}

	function marcarSujo(valor) {
		sujo = valor;
		avisoSujo.hidden = !valor;
	}

	function mostrarAlerta(mensagem, tipo) {
		const div = document.createElement('div');
		div.className = 'alert alert-' + tipo;
		div.setAttribute('role', 'alert');
		div.textContent = mensagem;
		areaAlerta.replaceChildren(div);

		if (tipo === 'success') {
			setTimeout(function () {
				if (div.parentNode) {
					div.remove();
				}
			}, 5000);
		}
	}

	function mover(lista, indice, delta) {
		const destino = indice + delta;

		if (destino < 0 || destino >= lista.length) {
			return false;
		}

		lista.splice(destino, 0, lista.splice(indice, 1)[0]);
		return true;
	}

	function renderAlternativa(texto, i) {
		const el = novoTemplate('tpl-alternativa');
		el.querySelector('[data-num]').textContent = i + 1;
		el.querySelector('[data-campo="alternativa"]').value = texto;
		el.querySelector('[data-campo="alternativa"]').dataset.ultimo = texto.trim();
		return el;
	}

	// Pergunta com fonte_dw: o preenchimento usa a lista do data warehouse, que aqui só é exibida.
	// As alternativas fixas ficam ocultas e são gravadas como estão (reserva para quando o DW não responde).
	function renderListaDw(bloco, lista) {
		const blocoDw = bloco.querySelector('[data-bloco-dw]');
		const resumo = blocoDw.querySelector('[data-dw-resumo]');
		const itens = blocoDw.querySelector('[data-dw-lista]');

		blocoDw.hidden = !lista;
		bloco.querySelector('[data-alternativas]').hidden = !!lista;
		bloco.querySelector('[data-acao="alternativa-adicionar"]').hidden = !!lista;

		if (!lista) {
			return;
		}

		if (lista.porInstrumento) {
			resumo.textContent = 'Alternativas carregadas do data warehouse (fonte "' + lista.fonte + '") de acordo com o instrumento escolhido no preenchimento.';
		} else if (!lista.opcoes.length) {
			resumo.textContent = 'Alternativas carregadas do data warehouse (fonte "' + lista.fonte + '"), mas o data warehouse não respondeu agora. No preenchimento, valem as alternativas fixas da pergunta enquanto ele estiver fora do ar.';
		} else {
			resumo.textContent = lista.opcoes.length + ' alternativas carregadas do data warehouse (fonte "' + lista.fonte + '")'
				+ (lista.filtrada ? ', filtradas no preenchimento de acordo com respostas anteriores' : '')
				+ '. Elas são atualizadas no data warehouse e não podem ser editadas aqui.';
		}

		itens.hidden = !lista.opcoes.length;
		itens.replaceChildren(...lista.opcoes.map(function (nome) {
			const li = document.createElement('li');
			li.textContent = nome;
			return li;
		}));
	}

	function renderPergunta(pergunta, i, total, iSecao) {
		const el = novoTemplate('tpl-pergunta');
		const select = el.querySelector('[data-campo="tipo"]');

		el.querySelector('[data-rotulo]').textContent = 'Pergunta ' + (i + 1);
		el.querySelector('[data-campo="enunciado"]').value = pergunta.enunciado;
		el.querySelector('[data-campo="ajuda"]').value = pergunta.ajuda;
		el.querySelector('[data-campo="obrigatoria"]').checked = !!pergunta.obrigatoria;

		Object.keys(opcoes.tipos).forEach(function (valor) {
			select.add(new Option(opcoes.tipos[valor], valor));
		});

		// Tipo legado que não faz parte da lista do construtor.
		if (!opcoes.tipos[pergunta.tipo]) {
			select.add(new Option(pergunta.tipo + ' (legado)', pergunta.tipo));
		}

		select.value = pergunta.tipo;

		const bloco = el.querySelector('[data-bloco-alternativas]');
		bloco.hidden = !ehSelecao(pergunta.tipo);
		bloco.querySelector('[data-alternativas]').replaceChildren(...pergunta.alternativas.map(renderAlternativa));
		renderListaDw(bloco, pergunta.listaDw);

		const blocoOutro = bloco.querySelector('[data-bloco-outro]');
		blocoOutro.hidden = !aceitaOutro(pergunta.tipo);
		blocoOutro.querySelector('[data-campo="permiteOutro"]').checked = !!pergunta.permiteOutro;

		el.querySelector('[data-acao="pergunta-subir"]').disabled = i === 0;
		el.querySelector('[data-acao="pergunta-descer"]').disabled = i === total - 1;

		renderRegras(el, pergunta, iSecao, i);

		return el;
	}

	function renderSecao(secao, i, total) {
		const el = novoTemplate('tpl-secao');

		el.querySelector('[data-rotulo]').textContent = 'Seção ' + (i + 1);
		el.querySelector('[data-campo="titulo"]').value = secao.titulo;
		el.querySelector('[data-campo="descricao"]').value = secao.descricao;
		el.querySelector('[data-acao="secao-subir"]').disabled = i === 0;
		el.querySelector('[data-acao="secao-descer"]').disabled = i === total - 1;
		el.querySelector('[data-perguntas]').replaceChildren(
			...secao.perguntas.map(function (p, j) {
				return renderPergunta(p, j, secao.perguntas.length, i);
			})
		);

		return el;
	}

	function render() {
		contSecoes.replaceChildren(
			...secoes.map(function (s, i) {
				return renderSecao(s, i, secoes.length);
			})
		);
		avisoVazio.hidden = secoes.length > 0;
	}

	function focar(el) {
		const campo = el && el.querySelector('input[type="text"], textarea');

		if (campo) {
			campo.focus();
			el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	}

	function contexto(origem) {
		const elSecao = origem.closest('[data-secao]');
		const elPergunta = origem.closest('[data-pergunta]');
		const elAlt = origem.closest('[data-alternativa]');
		const elRegra = origem.closest('[data-regra]');
		const ctx = { elSecao: elSecao, elPergunta: elPergunta, elAlt: elAlt, elRegra: elRegra, regra: elRegra ? elRegra.dataset.regra : null };

		if (elSecao) {
			ctx.iSecao = posicao(elSecao);
			ctx.secao = secoes[ctx.iSecao];
		}

		if (elPergunta) {
			ctx.iPergunta = posicao(elPergunta);
			ctx.pergunta = ctx.secao.perguntas[ctx.iPergunta];
		}

		if (elAlt) {
			ctx.iAlt = posicao(elAlt);
		}

		return ctx;
	}

	const acoes = {
		'secao-adicionar': function () {
			secoes.push({ id: 0, titulo: '', descricao: '', perguntas: [] });
			render();
			focar(contSecoes.lastElementChild);
		},
		'secao-subir': function (c) {
			mover(secoes, c.iSecao, -1);
			render();
		},
		'secao-descer': function (c) {
			mover(secoes, c.iSecao, 1);
			render();
		},
		'secao-excluir': function (c) {
			const chaves = c.secao.perguntas.map(function (p) { return p.chave; });

			if (!window.confirm(mensagemExclusao('Excluir esta seção e todas as suas perguntas?', chaves))) {
				return false;
			}

			limparRegrasDe(chaves);
			secoes.splice(c.iSecao, 1);
			render();
		},
		'pergunta-adicionar': function (c) {
			c.secao.perguntas.push(novaPergunta());
			render();
			focar(contSecoes.children[c.iSecao].querySelector('[data-perguntas]').lastElementChild);
		},
		'pergunta-subir': function (c) {
			mover(c.secao.perguntas, c.iPergunta, -1);
			render();
		},
		'pergunta-descer': function (c) {
			mover(c.secao.perguntas, c.iPergunta, 1);
			render();
		},
		'pergunta-duplicar': function (c) {
			const copia = clonar(c.pergunta);
			copia.id = 0;
			copia.chave = novaChave();
			c.secao.perguntas.splice(c.iPergunta + 1, 0, copia);
			render();
			focar(contSecoes.children[c.iSecao].querySelector('[data-perguntas]').children[c.iPergunta + 1]);
		},
		'pergunta-excluir': function (c) {
			if (!window.confirm(mensagemExclusao('Excluir esta pergunta?', [c.pergunta.chave]))) {
				return false;
			}

			limparRegrasDe([c.pergunta.chave]);
			c.secao.perguntas.splice(c.iPergunta, 1);
			render();
		},
		'alternativa-adicionar': function (c) {
			c.pergunta.alternativas.push('');
			render();

			const lista = contSecoes.children[c.iSecao]
				.querySelector('[data-perguntas]').children[c.iPergunta]
				.querySelector('[data-alternativas]');

			focar(lista.lastElementChild);
		},
		'alternativa-remover': function (c) {
			const removida = c.pergunta.alternativas[c.iAlt].trim();

			if (removida !== '') {
				removerValorDasRegras(c.pergunta.chave, removida);
			}

			c.pergunta.alternativas.splice(c.iAlt, 1);
			render();
		},
		'regra-remover-legada': function (c) {
			c.pergunta[c.regra] = condicaoVazia();
			render();
		},
		salvar: salvar,
		publicar: publicar
	};

	root.addEventListener('click', function (e) {
		const botao = e.target.closest('[data-acao]');

		if (!botao || botao.disabled || !acoes[botao.dataset.acao]) {
			return;
		}

		if (acoes[botao.dataset.acao](contexto(botao)) !== false && botao.dataset.acao !== 'salvar' && botao.dataset.acao !== 'publicar') {
			marcarSujo(true);
		}
	});

	root.addEventListener('input', function (e) {
		const campo = e.target.dataset.campo;

		if (!campo || campo === 'tipo' || campo === 'obrigatoria' || campo === 'permiteOutro' || campo.indexOf('regra-') === 0) {
			return;
		}

		const c = contexto(e.target);

		if (campo === 'titulo' || campo === 'descricao') {
			c.secao[campo] = e.target.value;
		} else if (campo === 'alternativa') {
			const antigo = e.target.dataset.ultimo || '';
			const novo = e.target.value.trim();

			c.pergunta.alternativas[c.iAlt] = e.target.value;

			// Campo vazio por instantes (usuário redigitando) não apaga a regra; o 'change' trata o esvaziamento final.
			if (novo !== '') {
				if (antigo !== '' && antigo !== novo) {
					renomearValorNasRegras(c.pergunta.chave, antigo, novo);
				}

				e.target.dataset.ultimo = novo;
			}

			refrescarRegras();
		} else {
			c.pergunta[campo] = e.target.value;

			if (campo === 'enunciado') {
				refrescarRegras();
			}
		}

		marcarSujo(true);
	});

	root.addEventListener('change', function (e) {
		const campo = e.target.dataset.campo;

		if (campo && campo.indexOf('regra-') === 0) {
			alterarRegra(e.target, campo);
			return;
		}

		if (campo === 'alternativa' && e.target.value.trim() === '' && e.target.dataset.ultimo) {
			removerValorDasRegras(contexto(e.target).pergunta.chave, e.target.dataset.ultimo);
			e.target.dataset.ultimo = '';
			refrescarRegras();
			return;
		}

		if (campo !== 'tipo' && campo !== 'obrigatoria' && campo !== 'permiteOutro') {
			return;
		}

		const c = contexto(e.target);

		if (campo === 'obrigatoria') {
			c.pergunta.obrigatoria = e.target.checked;
		} else if (campo === 'permiteOutro') {
			c.pergunta.permiteOutro = e.target.checked;

			if (!e.target.checked) {
				removerValorDasRegras(c.pergunta.chave, opcoes.outroValor);
			}

			refrescarRegras();
		} else {
			c.pergunta.tipo = e.target.value;

			if (!aceitaOutro(c.pergunta.tipo)) {
				c.pergunta.permiteOutro = false;
				removerValorDasRegras(c.pergunta.chave, opcoes.outroValor);
			}

			if (!ehSelecao(c.pergunta.tipo)) {
				limparRegrasDe([c.pergunta.chave]);
			}

			while (ehSelecao(c.pergunta.tipo) && c.pergunta.alternativas.length < 2) {
				c.pergunta.alternativas.push('');
			}

			render();
		}

		marcarSujo(true);
	});

	// 'toggle' não propaga, por isso a captura.
	root.addEventListener('toggle', function (e) {
		if (e.target.matches && e.target.matches('[data-bloco-condicional]')) {
			const c = contexto(e.target);

			if (c.pergunta) {
				c.pergunta.condAberto = e.target.open;
			}
		}
	}, true);

	window.addEventListener('beforeunload', function (e) {
		if (sujo) {
			e.preventDefault();
			e.returnValue = '';
		}
	});

	function salvar() {
		if (salvando) {
			return false;
		}

		salvando = true;
		botaoSalvar.disabled = true;

		const dados = new FormData();
		dados.append(opcoes.token, '1');
		dados.append('edital_id', opcoes.editalId);
		dados.append('dados', JSON.stringify({ secoes: secoes }));

		fetch(opcoes.urlSalvar, {
			method: 'POST',
			body: dados,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
		})
			.then(function (resposta) {
				return resposta.json().catch(function () {
					return { success: false, message: 'Resposta inesperada do servidor.' };
				});
			})
			.then(function (json) {
				if (!json.success) {
					mostrarAlerta(json.message || 'Não foi possível salvar.', 'danger');
					return;
				}

				secoes = prepararEstado(clonar(json.data.secoes));
				render();
				marcarSujo(false);
				mostrarAlerta(json.message, 'success');
			})
			.catch(function () {
				mostrarAlerta('Falha de comunicação com o servidor.', 'danger');
			})
			.finally(function () {
				salvando = false;
				botaoSalvar.disabled = false;
			});

		return false;
	}

	function renderPublicacao() {
		const publicado = state === 1;
		const status = !publicado
			? { classe: 'is-draft', icone: 'fa-pencil-alt', texto: 'Rascunho' }
			: (opcoes.aberto
				? { classe: 'is-open', icone: 'fa-check-circle', texto: 'Aberto para preenchimento' }
				: { classe: 'is-closed', icone: 'fa-clock', texto: 'Encerrado' });

		seloState.className = 'formulario-gestao-status ms-2 ' + status.classe;
		seloState.innerHTML = '<i class="fas ' + status.icone + '" aria-hidden="true"></i> ' + status.texto;

		botoesPublicar.forEach(function (b) {
			b.className = 'btn ' + (publicado ? 'btn-warning' : 'btn-success');
			b.disabled = publicando;
			b.innerHTML = publicado
				? '<i class="fas fa-undo" aria-hidden="true"></i> Voltar para rascunho'
				: '<i class="fas fa-check" aria-hidden="true"></i> Publicar';
		});
	}

	function publicar() {
		if (publicando) {
			return false;
		}

		publicando = true;
		renderPublicacao();

		const dados = new FormData();
		dados.append(opcoes.token, '1');
		dados.append('edital_id', opcoes.editalId);
		dados.append('state', state === 1 ? '0' : '1');

		fetch(opcoes.urlPublicar, {
			method: 'POST',
			body: dados,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
		})
			.then(function (resposta) {
				return resposta.json().catch(function () {
					return { success: false, message: 'Resposta inesperada do servidor.' };
				});
			})
			.then(function (json) {
				if (!json.success) {
					mostrarAlerta(json.message || 'Não foi possível alterar a publicação.', 'danger');
					return;
				}

				state = json.data.state === 1 ? 1 : 0;
				mostrarAlerta(json.message, 'success');
			})
			.catch(function () {
				mostrarAlerta('Falha de comunicação com o servidor.', 'danger');
			})
			.finally(function () {
				publicando = false;
				renderPublicacao();
			});

		return false;
	}

	secoes = prepararEstado(secoes);
	render();
	renderPublicacao();
})();
