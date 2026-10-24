/**
 * Cadastro: submit, confirmação de exclusão e filtros de edital.
 * Sem handlers inline (CSP script-src-attr 'none').
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		window.Joomla = window.Joomla || {};

		var cadastroForm = document.querySelector('.com-edital-cadastro-form');

		if (cadastroForm) {
			Joomla.submitbutton = function (task) {
				var form = document.getElementById('adminForm');

				if (!form) {
					return;
				}

				var taskName = String(task || '');
				var isCancel = taskName.indexOf('.cancel') !== -1;
				var isDestructive = taskName.indexOf('.delete') !== -1 || taskName.indexOf('.trash') !== -1;

				if (isDestructive) {
					var message = form.getAttribute('data-confirm-delete') || '';

					if (message && !window.confirm(message)) {
						return;
					}
				}

				var needsValidation = form.classList.contains('com-edital-cadastro-form') && !isCancel;

				if (needsValidation && document.formvalidator && !document.formvalidator.isValid(form)) {
					return;
				}

				Joomla.submitform(task, form);
			};

			document.addEventListener('click', function (event) {
				var button = event.target.closest('.js-cadastro-task');

				if (!button) {
					return;
				}

				event.preventDefault();

				var task = button.getAttribute('data-task');

				if (task) {
					Joomla.submitbutton(task);
				}
			});
		}

		var edital = document.getElementById('jform_co_edital');
		var diasNaoAplica = document.getElementById('jform_qt_dias_nao_aplica');
		var diasComplementacao = document.getElementById('jform_qt_dias_complementacao');

		if (diasNaoAplica && diasComplementacao) {
			var sincronizarDias = function () {
				diasComplementacao.disabled = diasNaoAplica.checked;
			};

			diasNaoAplica.addEventListener('change', sincronizarDias);
			sincronizarDias();
		}

		var configDependencia = (window.Joomla && Joomla.getOptions)
			? (Joomla.getOptions('com_edital.dependencia') || {})
			: {};

		var parseValoresCondicao = function (raw) {
			raw = String(raw || '').trim();

			if (!raw) {
				return [];
			}

			if (raw.charAt(0) === '[') {
				try {
					var parsed = JSON.parse(raw);

					if (Array.isArray(parsed)) {
						return parsed.map(function (item) { return String(item); }).filter(function (item) {
							return item !== '';
						});
					}
				} catch (e) {
					return [raw];
				}
			}

			return [raw];
		};

		var initCampoValores = function (sourceId, inputId) {
			var source = document.getElementById(sourceId);
			var input = document.getElementById(inputId);

			if (!source || !input || input.dataset.dependenciaPronta === '1') {
				return;
			}

			input.dataset.dependenciaPronta = '1';
			input.classList.add('visually-hidden');
			input.setAttribute('tabindex', '-1');
			input.setAttribute('aria-hidden', 'true');

			var box = document.createElement('div');
			box.className = 'depende-valores';
			input.insertAdjacentElement('afterend', box);

			var gravar = function (valores) {
				var unicos = [];

				valores.forEach(function (valor) {
					valor = String(valor || '').trim();

					if (valor !== '' && unicos.indexOf(valor) === -1) {
						unicos.push(valor);
					}
				});

				input.value = unicos.length ? JSON.stringify(unicos) : '';
			};

			var renderTexto = function (valores) {
				var lista = document.createElement('div');
				lista.className = 'd-flex flex-column gap-2';
				var linhas = valores.length ? valores.slice() : [''];

				var sincronizar = function () {
					gravar(Array.from(lista.querySelectorAll('input')).map(function (campo) {
						return campo.value;
					}));
				};

				var adicionarLinha = function (valor) {
					var grupo = document.createElement('div');
					grupo.className = 'input-group';

					var campo = document.createElement('input');
					campo.type = 'text';
					campo.className = 'form-control';
					campo.value = valor;
					campo.placeholder = configDependencia.placeholder || '';
					campo.addEventListener('input', sincronizar);

					var remover = document.createElement('button');
					remover.type = 'button';
					remover.className = 'btn btn-outline-danger';
					remover.textContent = configDependencia.remover || 'Remover';
					remover.addEventListener('click', function () {
						grupo.remove();

						if (!lista.querySelector('input')) {
							adicionarLinha('');
						}

						sincronizar();
					});

					grupo.appendChild(campo);
					grupo.appendChild(remover);
					lista.appendChild(grupo);
				};

				linhas.forEach(adicionarLinha);
				box.appendChild(lista);

				var adicionar = document.createElement('button');
				adicionar.type = 'button';
				adicionar.className = 'btn btn-sm btn-secondary mt-2';
				adicionar.textContent = configDependencia.adicionar || 'Adicionar valor';
				adicionar.addEventListener('click', function () {
					adicionarLinha('');
					var campos = lista.querySelectorAll('input');
					campos[campos.length - 1].focus();
				});
				box.appendChild(adicionar);
			};

			var renderLista = function (opcoes, selecionados) {
				var lista = document.createElement('div');
				lista.className = 'depende-valores-opcoes';
				var conhecidos = {};

				opcoes.forEach(function (opcao) {
					conhecidos[String(opcao.valor)] = true;
				});

				selecionados.forEach(function (valor) {
					if (!conhecidos[valor]) {
						opcoes.push({ nome: valor, valor: valor });
					}
				});

				opcoes.forEach(function (opcao, indice) {
					var label = document.createElement('label');
					label.className = 'form-check';

					var checkbox = document.createElement('input');
					checkbox.type = 'checkbox';
					checkbox.className = 'form-check-input';
					checkbox.value = opcao.valor;
					checkbox.id = inputId + '_' + indice;
					checkbox.checked = selecionados.indexOf(String(opcao.valor)) !== -1;
					checkbox.addEventListener('change', function () {
						gravar(Array.from(lista.querySelectorAll('input:checked')).map(function (campo) {
							return campo.value;
						}));
					});

					var texto = document.createElement('span');
					texto.className = 'form-check-label';
					texto.textContent = opcao.nome || opcao.valor;

					label.appendChild(checkbox);
					label.appendChild(texto);
					lista.appendChild(label);
				});

				box.appendChild(lista);
			};

			var render = function (limpar) {
				if (limpar) {
					input.value = '';
				}

				box.textContent = '';
				var perguntaId = String(source.value || '');

				if (!perguntaId) {
					var aviso = document.createElement('div');
					aviso.className = 'form-text';
					aviso.textContent = configDependencia.selecione || '';
					box.appendChild(aviso);
					return;
				}

				var info = (configDependencia.catalogo || {})[perguntaId] || {};
				var opcoes = Array.isArray(info.opcoes) ? info.opcoes.slice() : [];
				var selecionados = parseValoresCondicao(input.value);

				if (opcoes.length > 0) {
					renderLista(opcoes, selecionados);
					return;
				}

				renderTexto(selecionados);
			};

			source.addEventListener('change', function () {
				render(true);
			});
			render(false);
		};

		initCampoValores('jform_depende_de', 'jform_depende_de_valor');
		initCampoValores('jform_depende_de_ocultar', 'jform_depende_de_ocultar_valor');

		if (!edital) {
			return;
		}

		var filterDepende = function () {
			['jform_depende_de', 'jform_depende_de_ocultar'].forEach(function (idCampo) {
				var depende = document.getElementById(idCampo);

				if (!depende) {
					return;
				}

				var id = String(edital.value || '');
				var options = depende.options;
				var limpar = false;

				for (var i = 0; i < options.length; i++) {
					if (!options[i].value) {
						continue;
					}

					var editalOpcao = (options[i].getAttribute('data-filter') || '').trim();
					var ocultar = id !== '' && editalOpcao !== '' && editalOpcao !== id;

					options[i].hidden = ocultar;
					options[i].disabled = ocultar;

					if (ocultar && options[i].selected) {
						options[i].selected = false;
						options[i].removeAttribute('selected');
						limpar = true;
					}
				}

				var fancy = depende.closest('joomla-field-fancy-select');

				if (fancy && fancy.choicesInstance) {
					if (limpar) {
						fancy.choicesInstance.removeActiveItems();
					}

					fancy.choicesInstance.refresh();
				} else if (limpar) {
					depende.value = '';
				}

				if (limpar) {
					depende.dispatchEvent(new Event('change'));
				}
			});
		};

		var filterAgrupamento = function () {
			var agrupamento = document.getElementById('jform_co_agrupamento');

			if (!agrupamento) {
				return;
			}

			var id = String(edital.value || '');
			var atual = String(agrupamento.value || '');
			var options = agrupamento.options;
			var manter = false;

			for (var i = 0; i < options.length; i++) {
				if (!options[i].value) {
					continue;
				}

				var editais = (options[i].getAttribute('data-filter') || '').trim();
				var show = id !== '' && (editais === '' || (',' + editais + ',').indexOf(',' + id + ',') !== -1);

				options[i].hidden = !show;
				options[i].disabled = !show;

				if (show && options[i].value === atual) {
					manter = true;
				}
			}

			if (!manter) {
				agrupamento.value = '';
			}
		};

		var filterByEdital = function () {
			filterDepende();
			filterAgrupamento();
		};

		edital.addEventListener('change', filterByEdital);
		document.addEventListener('joomla.tab.shown', filterByEdital);
		filterByEdital();
	});
})();
