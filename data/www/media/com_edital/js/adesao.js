var AdesaoForm = {
    navLinks: null,
    init: function () {
        // Inicializar máscara de CNPJ
        AdesaoForm.initCnpjMask();
        AdesaoForm.initCpfMask();

        // Inicializar validação de e-mails
        AdesaoForm.initEmailValidation();

        // Inicializar contagem de documentos
        AdesaoForm.initDocumentosContagem();

        //montagem dados solicitante
        AdesaoForm.initFormSolicitante();
        AdesaoForm.initSubmitButtonState();

        // Exibir/ocultar perguntas dependentes de outra pergunta
        AdesaoForm.initDependenciaPerguntas();

        // Alternativa "Outro" (texto livre) de perguntas de seleção única/múltipla
        AdesaoForm.initOutroOpcoes();

        // Opções filtradas e pré-preenchimento a partir do data warehouse
        AdesaoForm.initIntegracaoDw();

        // Perguntas do tipo lista de itens (tabela)
        AdesaoForm.initTabelas();

        // Formulário de execução: perguntas preenchidas a partir do formulário de origem
        AdesaoForm.initPerguntasTravadas();

        // Customizações visuais da etapa Estrutura e Gestão
        AdesaoForm.initEstruturaGestaoFrontend();

        // Navegação por abas dos agrupamentos
        AdesaoForm.initTabsAgrupamento();

        // Recalcular estado "pronto para envio" quando qualquer resposta de pergunta mudar
        $('#form-adesao').on('input change', '.tab-agrupamento [name^="pergunta"]', function () {
            AdesaoForm.updateSubmitButtonState();
        });

        $("#form-adesao").on("submit", async function (e) {
            e.preventDefault();

            // Validar campos obrigatórios do solicitante (inclui CNPJ, tipo, estado, municípios condicional, etc.)
            if (!AdesaoForm.validateSolicitanteFields()) {
                return false;
            }

            // Validar e-mails antes de submeter (responsável obrigatório, alternativo opcional)
            if (!AdesaoForm.validateEmails()) {
                AdesaoForm.activateTab('solicitante');
                AdesaoForm.showAlert('danger', 'Por favor, corrija o e-mail institucional do Responsável.');
                return false;
            }

            
            var btnClicado = $(document.activeElement);
            if (btnClicado.val() == "rascunho") {
                var acaoBotao = btnClicado.attr('data-action');

                // Ao avançar de etapa, validar as perguntas obrigatórias da aba ativa
                // (campos com display:none não são verificados pela validação nativa do navegador).
                if (acaoBotao === 'proxima-etapa') {
                    var $tabAtiva = $('.tab-agrupamento.active');
                    var $perguntaInvalidaEtapa = AdesaoForm.encontrarPerguntaObrigatoriaVazia($tabAtiva, true);
                    if ($perguntaInvalidaEtapa) {
                        AdesaoForm.showAlert('danger', 'Por favor, preencha todos os campos obrigatórios desta etapa antes de avançar.');
                        setTimeout(function () {
                            $('html, body').animate({ scrollTop: $perguntaInvalidaEtapa.offset().top - 100 }, 300);
                            $perguntaInvalidaEtapa.trigger('focus');
                        }, 100);
                        return false;
                    }
                }

                // Botão possui o atributo formnovalidate, então a validação HTML5
                // (inclusive dos campos radio obrigatórios) já é ignorada pelo navegador.
                AdesaoForm.submitForm(true, acaoBotao); // Enviar como rascunho
                return;
            }
            
            
            // Validar perguntas obrigatórias que possam estar em abas ocultas
            // (campos com display:none não são verificados pela validação nativa do navegador)
            var $perguntaInvalida = AdesaoForm.encontrarPerguntaObrigatoriaVazia(null, true);
            if ($perguntaInvalida) {
                AdesaoForm.activateTabForElement($perguntaInvalida);
                AdesaoForm.showAlert('danger', 'Por favor, preencha todos os campos obrigatórios.');
                setTimeout(function () {
                    $('html, body').animate({ scrollTop: $perguntaInvalida.offset().top - 100 }, 300);
                    $perguntaInvalida.trigger('focus');
                }, 100);
                return false;
            }

            // Validar captcha se necessário
            if (!AdesaoForm.validateCaptcha()) {
                AdesaoForm.showAlert('danger', 'Por favor, complete a verificação de segurança (captcha).');
                return false;
            }

            AdesaoForm.submitForm();
        });

    },

    initDocumentosContagem: function () {
        // Aguardar um pouco para garantir que o DOM está completamente carregado
        setTimeout(function () {
            AdesaoForm.atualizarContagemDocumentos();
        }, 300);

        // Também contar quando a página estiver totalmente carregada
        $(window).on('load', function () {
            setTimeout(function () {
                AdesaoForm.atualizarContagemDocumentos();
            }, 100);
        });

        // Observar mudanças nos inputs de arquivo
        $(document).on('change', 'input[type="file"][data-pergunta-id]', function () {
            AdesaoForm.atualizarContagemDocumentos();
        });

        // Observar quando arquivos são removidos (quando o input é limpo)
        $(document).on('click', '.file-drop-area', function () {
            // Aguardar um pouco para que o evento change seja processado
            setTimeout(function () {
                AdesaoForm.atualizarContagemDocumentos();
            }, 100);
        });


        // Observar mudanças na UI do upload (ex.: remoção sem disparar "change")
        // Isso evita contadores "travados" quando o arquivo é limpo via UI/JS.
        if (window.MutationObserver) {
            var contagemDebounceTimer = null;
            var scheduleRecount = function () {
                if (contagemDebounceTimer) {
                    clearTimeout(contagemDebounceTimer);
                }
                contagemDebounceTimer = setTimeout(function () {
                    AdesaoForm.atualizarContagemDocumentos();
                }, 80);
            };

            try {
                $('.file-upload-wrapper').each(function () {
                    var wrapperEl = this;
                    var observer = new MutationObserver(function () {
                        scheduleRecount();
                    });
                    observer.observe(wrapperEl, {
                        childList: true,
                        subtree: true,
                        attributes: true,
                        attributeFilter: ['class', 'style']
                    });
                });
            } catch (e) {
                // Falha silenciosa: contagem seguirá pelos eventos tradicionais.
            }
        }
    },

    initDependenciaPerguntas: function () {
        var $camposDependentes = $('.pergunta-field[data-depende-de], .pergunta-field[data-depende-ocultar]');
        if ($camposDependentes.length === 0) {
            return;
        }

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

        var condicaoAtendida = function (esperados, atuais) {
            return esperados.some(function (valor) {
                return atuais.indexOf(valor) !== -1;
            });
        };

        var getValorPergunta = AdesaoForm.valoresPergunta;

        var getPerguntaField = function (perguntaId) {
            return $('.pergunta-field[data-pergunta-id="' + perguntaId + '"]');
        };

        var origemAtiva = function (perguntaId) {
            var $origem = getPerguntaField(perguntaId);
            return $origem.length === 0 || !$origem.hasClass('d-none');
        };

        var deveExibirCampo = function ($campo) {
            var exibirDe = $campo.attr('data-depende-de');
            var ocultarDe = $campo.attr('data-depende-ocultar');
            var visivel = true;

            if (exibirDe) {
                visivel = origemAtiva(exibirDe)
                    && condicaoAtendida(parseValoresCondicao($campo.attr('data-depende-de-valor')), getValorPergunta(exibirDe));
            }

            if (visivel && ocultarDe) {
                var ocultar = origemAtiva(ocultarDe)
                    && condicaoAtendida(parseValoresCondicao($campo.attr('data-depende-ocultar-valor')), getValorPergunta(ocultarDe));
                if (ocultar) {
                    visivel = false;
                }
            }

            return visivel;
        };

        // Atualiza toda a árvore abaixo da pergunta alterada.
        var atualizarDependentes = function (perguntaOrigemId, visitados) {
            visitados = visitados || {};
            if (visitados[perguntaOrigemId]) {
                return;
            }
            visitados[perguntaOrigemId] = true;

            $('.pergunta-field[data-depende-de="' + perguntaOrigemId + '"], .pergunta-field[data-depende-ocultar="' + perguntaOrigemId + '"]').each(function () {
                var $campo = $(this);
                var deveExibir = deveExibirCampo($campo);

                $campo.toggleClass('d-none', !deveExibir);

                $campo.find('input, select, textarea').each(function () {
                    var $input = $(this);
                    if (!$input.data('estado-original-armazenado')) {
                        $input.data('obrigatorio-original', $input.prop('required'));
                        $input.data('desabilitado-original', $input.prop('disabled'));
                        $input.data('estado-original-armazenado', true);
                    }

                    $input.prop('required', deveExibir ? $input.data('obrigatorio-original') : false);
                    $input.prop('disabled', deveExibir ? $input.data('desabilitado-original') : true);
                });

                if (!deveExibir) {
                    $campo.removeClass('pergunta-invalida');
                    $campo.find('.is-invalid').removeClass('is-invalid');
                }

                atualizarDependentes($campo.attr('data-pergunta-id'), visitados);
            });
        };

        // Preserva required/disabled antes de processar o estado inicial.
        $camposDependentes.find('input, select, textarea').each(function () {
            var $input = $(this);
            $input.data('obrigatorio-original', $input.prop('required'));
            $input.data('desabilitado-original', $input.prop('disabled'));
            $input.data('estado-original-armazenado', true);
        });

        var origens = {};
        $camposDependentes.each(function () {
            var exibirDe = $(this).attr('data-depende-de');
            var ocultarDe = $(this).attr('data-depende-ocultar');
            if (exibirDe) {
                origens[exibirDe] = true;
            }
            if (ocultarDe) {
                origens[ocultarDe] = true;
            }
        });

        Object.keys(origens).forEach(function (perguntaOrigemId) {
            atualizarDependentes(perguntaOrigemId, {});
        });

        $('#form-adesao').on('change input', '.pergunta-field [name^="pergunta"]', function () {
            var perguntaId = $(this).closest('.pergunta-field').attr('data-pergunta-id');
            atualizarDependentes(perguntaId, {});
            AdesaoForm.atualizarContagemDocumentos();
            AdesaoForm.updateSubmitButtonState();
        });

        $('#form-adesao').on('change', 'input[name="tp_esfera_governamental"]', function () {
            atualizarDependentes('solicitante', {});
            AdesaoForm.atualizarContagemDocumentos();
            AdesaoForm.updateSubmitButtonState();
        });

        AdesaoForm.updateSubmitButtonState();
    },

    // Retorna o(s) valor(es) atualmente selecionado(s)/preenchido(s) para uma pergunta de origem
    valoresPergunta: function (perguntaId) {
        if (perguntaId === 'solicitante') {
            var esfera = $('input[type="radio"][name="tp_esfera_governamental"]:checked').val()
                || $('input[type="hidden"][name="tp_esfera_governamental"]').val()
                || '';

            return [esfera];
        }

        var $radios = $('input[type="radio"][name="pergunta[' + perguntaId + ']"]');
        if ($radios.length > 0) {
            return [$radios.filter(':checked').val() || ''];
        }

        var $checkboxes = $('input[type="checkbox"][name="pergunta[' + perguntaId + '][]"]');
        if ($checkboxes.length > 0) {
            return $checkboxes.filter(':checked').map(function () { return $(this).val(); }).get();
        }

        var $campo = $('#pergunta_' + perguntaId);
        if ($campo.length > 0) {
            return [$campo.val() || ''];
        }

        return [''];
    },

    // Integração com o data warehouse (data-application-mir):
    // - perguntas com data-fonte-dw e depende-de mostram só as opções cujo data-filtro
    //   corresponde à resposta da pergunta-pai ("5802 - Políticas..." casa com "5802");
    // - perguntas com data-chave-dw identificam um registro; ao mudar, as perguntas com
    //   data-campo-dw="registro.campo" são preenchidas com os dados do DW.
    // Valores vindos do formulário de origem (o servidor ignora alterações): somente leitura,
    // mas ainda enviados (disabled não seria). O <select> não tem readonly: sai do foco e do clique.
    initPerguntasTravadas: function () {
        var $travadas = $('.pergunta-field.pergunta-travada');
        $travadas.find('input, textarea').prop('readonly', true);
        $travadas.find('select').attr({ tabindex: '-1', 'aria-readonly': 'true' })
            .on('mousedown keydown', function (e) { e.preventDefault(); });
        $travadas.each(function () {
            $(this).find('label').first().append(' <span class="badge text-bg-light fw-normal">definido pelo gestor</span>');
        });
    },

    initIntegracaoDw: function () {
        var seletorResposta = function (perguntaId) {
            return '[name="pergunta[' + perguntaId + ']"], [name="pergunta[' + perguntaId + '][]"]';
        };

        var filtroCorresponde = function (filtro, valores) {
            return valores.some(function (valor) {
                valor = String(valor || '');
                return valor === filtro || valor.indexOf(filtro + ' ') === 0;
            });
        };

        $('.pergunta-field[data-fonte-dw][data-depende-de]').each(function () {
            var $campo = $(this);
            var origem = $campo.attr('data-depende-de');
            // Cópia de todas as opções filtráveis: as que não valem para a resposta da
            // pergunta-pai saem do <select> (o Safari ignora hidden em <option>).
            var $select = $campo.find('select');
            var valorSalvo = $select.val();
            var $opcoesFiltraveis = $select.find('option[data-filtro]').detach();

            var aplicarFiltro = function () {
                var valores = AdesaoForm.valoresPergunta(origem);

                if ($select.length > 0) {
                    var selecionado = $select.val() || valorSalvo;
                    valorSalvo = null;
                    $select.find('option[data-filtro]').detach();
                    $select.append($opcoesFiltraveis.filter(function () {
                        return filtroCorresponde(this.getAttribute('data-filtro'), valores);
                    }));
                    $select.val(selecionado);
                    if (($select.val() || '') !== (selecionado || '')) {
                        $select.val('').trigger('change');
                    }
                }

                $campo.find('.form-check[data-filtro]').each(function () {
                    var $item = $(this);
                    var corresponde = filtroCorresponde($item.attr('data-filtro'), valores);
                    $item.toggleClass('d-none', !corresponde);
                    if (!corresponde) {
                        $item.find('input:checked').prop('checked', false).trigger('change');
                    }
                });
            };

            $(document).on('change', seletorResposta(origem), aplicarFiltro);
            aplicarFiltro();
        });

        // Sugestões (datalist) filtradas pela resposta da pergunta cuja fonte é data-filtro-dw,
        // ex.: metas do plano de trabalho do instrumento selecionado.
        $('.pergunta-field[data-sugestoes-dw][data-filtro-dw]').each(function () {
            var $campo = $(this);
            var fonte = $campo.attr('data-sugestoes-dw');
            var $origem = $('.pergunta-field[data-fonte-dw="' + $campo.attr('data-filtro-dw') + '"]').first();
            var $lista = $campo.find('datalist');

            if ($origem.length === 0 || $lista.length === 0) {
                return;
            }

            var origemId = $origem.attr('data-pergunta-id');

            var carregar = function () {
                var filtro = AdesaoForm.valoresPergunta(origemId)[0] || '';
                $lista.empty();
                if (!filtro) {
                    return;
                }

                var ajaxData = {
                    option: 'com_edital',
                    task: 'adesaoform.opcoesDw',
                    fonte: fonte,
                    filtro: filtro
                };
                ajaxData[window.adesaoConfig.token] = 1;

                $.ajax({
                    url: window.adesaoConfig.baseUrl + 'index.php',
                    type: 'GET',
                    data: ajaxData,
                    dataType: 'json'
                }).done(function (response) {
                    if (!response || !response.success) {
                        return;
                    }
                    $.each(response.data, function (i, opcao) {
                        $('<option>').attr('value', opcao.valor).appendTo($lista);
                    });
                }).fail(function (xhr, status, error) {
                    console.debug('Sugestões do DW indisponíveis:', error);
                });
            };

            $(document).on('change', seletorResposta(origemId), carregar);
            carregar();
        });

        $('.pergunta-field[data-chave-dw]').each(function () {
            var registro = $(this).attr('data-chave-dw');
            var perguntaId = $(this).attr('data-pergunta-id');
            var $destinos = $('.pergunta-field[data-campo-dw^="' + registro + '."]');

            if ($destinos.length === 0) {
                return;
            }

            var preencher = function (sobrescrever) {
                var chave = AdesaoForm.valoresPergunta(perguntaId)[0] || '';
                if (!chave) {
                    return;
                }

                var ajaxData = {
                    option: 'com_edital',
                    task: 'adesaoform.dadosDw',
                    registro: registro,
                    chave: chave
                };
                ajaxData[window.adesaoConfig.token] = 1;

                $.ajax({
                    url: window.adesaoConfig.baseUrl + 'index.php',
                    type: 'GET',
                    data: ajaxData,
                    dataType: 'json'
                }).done(function (response) {
                    // Registro não encontrado conta como "sem dados": limpa o que foi preenchido automaticamente antes.
                    var dados = (response && response.success) ? response.data : {};
                    $destinos.each(function () {
                        var campo = $(this).attr('data-campo-dw').split('.')[1];
                        var valor = dados[campo] !== undefined ? String(dados[campo]) : '';
                        if (valor !== '' || sobrescrever) {
                            AdesaoForm.preencherCampoDw($(this), valor, sobrescrever);
                        }
                    });
                }).fail(function (xhr, status, error) {
                    console.debug('Dados do DW indisponíveis:', error);
                });
            };

            $(document).on('change', seletorResposta(perguntaId), function () {
                preencher(true);
            });
            preencher(false);
        });
    },

    // Preenche a pergunta com o valor vindo do DW. Só altera campos vazios ou, quando
    // sobrescrever, campos que ainda guardam o valor preenchido automaticamente antes.
    preencherCampoDw: function ($destino, valor, sobrescrever) {
        var perguntaId = $destino.attr('data-pergunta-id');
        var anterior = $destino.data('dwValor');
        var $radios = $destino.find('input[type="radio"][name="pergunta[' + perguntaId + ']"]');

        if ($radios.length > 0) {
            var $marcado = $radios.filter(':checked');
            if ($marcado.length > 0 && !(sobrescrever && $marcado.val() === anterior)) {
                return;
            }
            var $alvo = $radios.filter(function () { return this.value === valor; });
            if ($alvo.length > 0) {
                $alvo.prop('checked', true).trigger('change');
            } else if (valor === '' && $marcado.length > 0) {
                $marcado.prop('checked', false).trigger('change');
            }
            $destino.data('dwValor', valor);
            return;
        }

        // Checkbox: marca a alternativa vinda do DW; só mexe se nada foi marcado pela pessoa.
        var $checkboxes = $destino.find('input[type="checkbox"][name="pergunta[' + perguntaId + '][]"]');
        if ($checkboxes.length > 0) {
            var $marcados = $checkboxes.filter(':checked');
            var soAutomatico = $marcados.length === 1 && $marcados.val() === anterior;
            if ($marcados.length > 0 && !(sobrescrever && soAutomatico)) {
                return;
            }
            $marcados.prop('checked', false);
            $checkboxes.filter(function () { return this.value === valor; }).prop('checked', true);
            $checkboxes.first().trigger('change');
            $destino.data('dwValor', valor);
            return;
        }

        var $input = $destino.find('#pergunta_' + perguntaId);
        if ($input.length === 0) {
            return;
        }

        // Lista: o DW pode trazer só o código ("5802") de uma opção "5802 - Políticas...".
        if ($input.is('select') && valor !== '') {
            var $opcao = $input.find('option').filter(function () {
                return this.value === valor || this.value.indexOf(valor + ' ') === 0;
            }).first();
            if ($opcao.length === 0) {
                return;
            }
            valor = $opcao.val();
        }

        var atual = $input.val() || '';
        if (atual === valor || (atual !== '' && !(sobrescrever && atual === anterior))) {
            return;
        }

        $input.val(valor).trigger('input').trigger('change');
        $destino.data('dwValor', valor);
    },

    // Lista de itens (pergunta do tipo "tabela"): a pessoa preenche os campos de entrada e
    // adiciona o item à lista; os itens ficam em JSON no campo oculto pergunta[id].
    initTabelas: function () {
        var formatarCnpj = function (valor) {
            var d = String(valor || '').replace(/\D/g, '').substring(0, 14);
            return d.replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
        };

        var formatarMoeda = function (valor) {
            var numero = parseFloat(valor);
            return isNaN(numero) ? valor : numero.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        };

        $('.pergunta-field[data-tipo-pergunta="tabela"] .pergunta-tabela').each(function () {
            var $tabela = $(this);
            var colunas = JSON.parse($tabela.attr('data-colunas') || '[]');
            var $oculto = $tabela.children('input[type="hidden"]');
            var $corpo = $tabela.find('.pergunta-tabela-lista tbody');
            var $erro = $tabela.find('.pergunta-tabela-erro');
            var itens = [];

            try {
                itens = JSON.parse($oculto.val() || '[]');
            } catch (e) {
                itens = [];
            }

            var atualizar = function () {
                $corpo.empty();
                $.each(itens, function (i, item) {
                    var $linha = $('<tr>').append($('<td>').text(i + 1));
                    $.each(colunas, function (j, coluna) {
                        var valor = item[coluna.chave] || '';
                        $linha.append($('<td>').text(coluna.tipo === 'moeda' && valor !== '' ? formatarMoeda(valor) : valor));
                    });
                    $linha.append($('<td class="text-end">').append(
                        $('<button type="button" class="btn btn-sm btn-outline-danger pergunta-tabela-remover">')
                            .attr('data-indice', i)
                            .attr('aria-label', 'Remover item ' + (i + 1))
                            .html('<i class="fas fa-trash" aria-hidden="true"></i> Remover')
                    ));
                    $corpo.append($linha);
                });
                $tabela.find('.pergunta-tabela-lista').toggleClass('d-none', itens.length === 0);
                $tabela.find('.pergunta-tabela-vazia').toggleClass('d-none', itens.length > 0);
                $oculto.val(itens.length ? JSON.stringify(itens) : '').trigger('change');
            };

            $tabela.on('input', 'input[data-tipo-coluna="cnpj"]', function () {
                this.value = formatarCnpj(this.value);
            });

            // Enter num campo de entrada adiciona o item em vez de enviar o formulário.
            $tabela.on('keydown', 'input[data-coluna]', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $tabela.find('.pergunta-tabela-adicionar').trigger('click');
                }
            });

            $tabela.on('click', '.pergunta-tabela-adicionar', function () {
                var item = {};
                var faltando = [];

                $.each(colunas, function (i, coluna) {
                    var valor = $.trim($tabela.find('[data-coluna="' + coluna.chave + '"]').val() || '');
                    if (coluna.tipo === 'cnpj' && valor !== '' && valor.replace(/\D/g, '').length !== 14) {
                        faltando.push(coluna.rotulo + ' (14 dígitos)');
                    } else if (coluna.obrigatoria && valor === '') {
                        faltando.push(coluna.rotulo);
                    }
                    item[coluna.chave] = valor;
                });

                if (faltando.length) {
                    $erro.text('Preencha: ' + faltando.join(', ') + '.').removeClass('d-none');
                    return;
                }

                $erro.addClass('d-none').text('');
                itens.push(item);
                $tabela.find('[data-coluna]').val('');
                atualizar();
            });

            $tabela.on('click', '.pergunta-tabela-remover', function () {
                itens.splice(parseInt($(this).attr('data-indice'), 10), 1);
                atualizar();
            });

            atualizar();
        });
    },

    initOutroOpcoes: function () {
        // Registrado depois de initDependenciaPerguntas para reaplicar o required após a reexibição.
        $('#form-adesao').on('change', '.pergunta-field input[type="radio"], .pergunta-field input[type="checkbox"]', function () {
            AdesaoForm.sincronizarOutros();
        });

        AdesaoForm.sincronizarOutros();
    },

    sincronizarOutros: function () {
        $('.pergunta-outro-toggle').each(function () {
            var $toggle = $(this);
            var $texto = $('#' + $toggle.attr('id') + '_texto');
            var marcado = $toggle.is(':checked');

            $texto.toggleClass('d-none', !marcado).prop('required', marcado && !$texto.prop('disabled'));

            if (!marcado) {
                $texto.val('');
            }
        });
    },

    outroSemTexto: function ($input) {
        return $input.hasClass('pergunta-outro-toggle')
            && $.trim($('#' + $input.attr('id') + '_texto').val() || '') === '';
    },

    initTabsAgrupamento: function () {
        AdesaoForm.navLinks = $('.nav-link-agrupamento');
        if (AdesaoForm.navLinks.length === 0) {
            return;
        }

        AdesaoForm.navLinks.on('click', function () {
            AdesaoForm.activateTab($(this).attr('data-tab-target'));
        });

        $('#btn-etapa-anterior').on('click', function () {
            var indiceAtual = $('.nav-link-agrupamento.active').parent().index();
            var indiceAnterior = Math.max(0, indiceAtual - 1);
            AdesaoForm.activateTab(AdesaoForm.navLinks.eq(indiceAnterior).attr('data-tab-target'));
        });

        AdesaoForm.updateEtapasNavigation();
    },

    navigateToTab: function (chave) {
        AdesaoForm.activateTab(chave);
        return true;
    },

    activateTab: function (chave) {
        var $navLink = $('.nav-link-agrupamento[data-tab-target="' + chave + '"]');
        var $tab = $('.tab-agrupamento[data-tab-chave="' + chave + '"]');
        if ($navLink.length === 0 || $tab.length === 0) {
            return;
        }

        $('.nav-link-agrupamento').removeClass('active');
        $navLink.addClass('active');

        $('.tab-agrupamento').removeClass('active');
        $tab.addClass('active');
        AdesaoForm.updateEtapasNavigation();

        if ($tab.offset()) {
            $('html, body').animate({ scrollTop: $tab.offset().top - 120 }, 250);
        }
    },

    updateEtapasNavigation: function () {
        var $links = $('.nav-link-agrupamento');
        var indiceAtual = $('.nav-link-agrupamento.active').parent().index();
        var total = $links.length;
        if (indiceAtual < 0 || total === 0) {
            return;
        }

        $('#btn-etapa-anterior').prop('disabled', indiceAtual === 0);
        $('#btn-proxima-etapa')
            .toggle(indiceAtual < total - 1)
            .html('Salvar e avançar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>');

        $('#btn-enviar-adesao').toggle(indiceAtual === total - 1);
    },

    activateTabForElement: function ($el) {
        var $tab = $el.closest('.tab-agrupamento[data-tab-chave]');
        if ($tab.length === 0) {
            return;
        }
        AdesaoForm.activateTab($tab.attr('data-tab-chave'));
    },

    isPerguntaPreenchida: function ($pergunta) {
        var tipoPergunta = $pergunta.attr('data-tipo-pergunta');
        var $campos = $pergunta.find('input, select, textarea').filter(':not(:disabled)');

        if (tipoPergunta === 'radio') {
            var $radio = $campos.filter('input[type="radio"]:checked');
            return $radio.length > 0 && !AdesaoForm.outroSemTexto($radio.first());
        }

        if (tipoPergunta === 'checkbox') {
            return $campos.filter('input[type="checkbox"]:checked').filter(function () {
                return !AdesaoForm.outroSemTexto($(this));
            }).length > 0;
        }

        if (tipoPergunta === 'arquivo') {
            var $arquivo = $campos.filter('input[type="file"]').first();
            return ($arquivo[0] && $arquivo[0].files.length > 0)
                || $arquivo.attr('data-tem-resposta-rascunho') === 'Sim';
        }

        if (tipoPergunta === 'tabela') {
            return $.trim($pergunta.find('.pergunta-tabela > input[type="hidden"]').val() || '') !== '';
        }

        if (tipoPergunta === 'distribuicao_numerica') {
            var $numeros = $campos.filter('input[type="number"]');
            return $numeros.length > 0 && $numeros.filter(function () {
                return $.trim($(this).val() || '') === '';
            }).length === 0;
        }

        var $campo = $campos.filter(':not([type="hidden"])').first();
        return $campo.length > 0 && $.trim($campo.val() || '') !== '';
    },

    perguntasDoProgresso: function () {
        return $('.tab-agrupamento .pergunta-field').filter(function () {
            var $pergunta = $(this);
            return !$pergunta.hasClass('d-none')
                && $pergunta.attr('data-tipo-pergunta') !== 'informativo';
        });
    },

    contarProgressoSolicitante: function () {
        var $tab = $('.tab-agrupamento[data-tab-chave="solicitante"]');
        if ($tab.length === 0) {
            return { total: 0, preenchidos: 0 };
        }

        var $esferaRadios = $tab.find('input[name="tp_esfera_governamental"]');
        var $esferaVisiveis = $esferaRadios.filter(function () {
            return $(this).closest('.d-none').length === 0;
        });
        var $form = $tab.find('.formSolicitante');
        var formVisivel = $form.length > 0 && !$form.hasClass('d-none');

        if ($esferaVisiveis.length === 0 && !formVisivel) {
            return { total: 0, preenchidos: 0 };
        }

        var total = 0;
        var preenchidos = 0;
        var add = function (ativo, ok) {
            if (!ativo) {
                return;
            }
            total++;
            if (ok) {
                preenchidos++;
            }
        };

        var esfera = ($esferaVisiveis.filter(':checked').val()
            || $esferaRadios.filter(':checked').val()
            || '');
        add($esferaVisiveis.length > 0, esfera !== '');

        if (!formVisivel) {
            return { total: total, preenchidos: preenchidos };
        }

        add($('#ds_cnpj').length > 0, ($('#ds_cnpj').val() || '').replace(/\D/g, '').length > 0);
        add($('#ds_cpf').length > 0, ($('#ds_cpf').val() || '').replace(/\D/g, '').length > 0);
        add(esfera === 'consorcio', $.trim($('#no_oficial').val() || '') !== '');
        add($('#sg_estado').length > 0, !!$('#sg_estado').val());
        add(esfera !== '' && esfera !== 'estadual', $('input[name="ds_municipios[]"]').length > 0);

        if (esfera === 'consorcio') {
            $('#municipios-emails-container input[type="email"]').each(function () {
                add(true, $.trim($(this).val() || '') !== '');
            });
        }

        add($('#ds_email_responsavel').length > 0, $.trim($('#ds_email_responsavel').val() || '') !== '');
        add($('#ds_senha').length > 0, ($('#ds_senha').val() || '').length > 0);
        add($('#ds_confirma_senha').length > 0, ($('#ds_confirma_senha').val() || '').length > 0);

        return { total: total, preenchidos: preenchidos };
    },

    calcularProgressoPreenchimento: function () {
        var solicitante = AdesaoForm.contarProgressoSolicitante();
        var $perguntas = AdesaoForm.perguntasDoProgresso();
        var total = solicitante.total + $perguntas.length;
        if (total === 0) {
            return 0;
        }

        var preenchidos = solicitante.preenchidos;
        $perguntas.each(function () {
            if (AdesaoForm.isPerguntaPreenchida($(this))) {
                preenchidos++;
            }
        });

        return Math.round((preenchidos / total) * 100);
    },

    atualizarBarraProgresso: function () {
        var percentual = AdesaoForm.calcularProgressoPreenchimento();
        $('#adesao-progress-value').text(percentual + '%');
        $('#adesao-progress-bar').css('width', percentual + '%');
        $('.adesao-progress').attr('aria-valuenow', percentual);
    },

    // Verifica perguntas obrigatórias mesmo quando estão em abas ocultas,
    // já que a validação nativa (required) do navegador ignora campos com display:none.
    encontrarPerguntaObrigatoriaVazia: function ($escopo, marcarErros) {
        var $invalido = null;
        $escopo = $escopo && $escopo.length ? $escopo : $('.tab-agrupamento');
        marcarErros = marcarErros === true;

        $escopo.find('.pergunta-field[data-obrigatorio="1"]').each(function () {
            if ($invalido) {
                return;
            }

            var $pergunta = $(this);
            if ($pergunta.hasClass('d-none') || $pergunta.attr('data-tipo-pergunta') === 'informativo') {
                return;
            }

            var $campos = $pergunta.find('input, select, textarea').filter(':not(:disabled)');
            var preenchido = AdesaoForm.isPerguntaPreenchida($pergunta);

            if (preenchido) {
                $pergunta.removeClass('pergunta-invalida');
                $campos.removeClass('is-invalid');
            } else if (marcarErros) {
                $pergunta.addClass('pergunta-invalida');
                $campos.addClass('is-invalid');
            }

            if (!preenchido) {
                $invalido = $campos.filter(':not([type="hidden"])').first();
                if ($invalido.length === 0) {
                    $invalido = $pergunta;
                }
            }
        });

        return $invalido;
    },

    atualizarContagemDocumentos: function () {
        // Encontrar todos os inputs de arquivo (documentos)
        var $documentos = $('input[type="file"][data-pergunta-id]').filter(function () {
            return !this.disabled && !$(this).closest('.pergunta-field').hasClass('d-none');
        });
        var total = $documentos.length;
        var enviados = 0;
        var pendentes = 0;
        var comErro = 0;

        if (total === 0) {
            // Se não encontrou documentos, tentar encontrar de outra forma
            $documentos = $('input[type="file"]').filter(function () {
                return !this.disabled
                    && !$(this).closest('.pergunta-field').hasClass('d-none')
                    && $(this).attr('id')
                    && $(this).attr('id').indexOf('pergunta_') === 0;
            });
            total = $documentos.length;
        }

        $documentos.each(function () {
            var $input = $(this);
            var inputId = $input.attr('id');

            // Tentar encontrar o campo pergunta de diferentes formas
            var $perguntaField = $input.closest('.pergunta-field');
            if ($perguntaField.length === 0) {
                // Se não encontrou, procurar pelo ID
                $perguntaField = $input.closest('div').parent().closest('.pergunta-field');
            }
            if ($perguntaField.length === 0) {
                // Última tentativa: procurar pelo wrapper do file-upload
                $perguntaField = $input.closest('.file-upload-wrapper').closest('.pergunta-field');
            }

            var $dropArea = $perguntaField.length > 0 ? $perguntaField.find('.file-drop-area') : $();
            var $fileCurrent = $perguntaField.length > 0 ? $perguntaField.find('.file-current') : $();

            // Procurar o label de diferentes formas
            var $label = $perguntaField.length > 0 ? $perguntaField.find('label[for="' + inputId + '"]') : $();
            if ($label.length === 0) {
                $label = $input.closest('.file-upload-wrapper').siblings('label');
            }
            if ($label.length === 0) {
                $label = $input.closest('.file-upload-wrapper').prevAll('label').first();
            }

            // Verificar se tem arquivo selecionado (novo upload)
            var temArquivoSelecionado = $input[0].files && $input[0].files.length > 0;

            // Verificar se tem arquivo existente (já enviado anteriormente)
            // Importante: algumas telas "removem" apenas ocultando o bloco.
            // Se estiver oculto, NÃO deve contar como enviado.
            var temArquivoExistente = $fileCurrent.length > 0 && $fileCurrent.is(':visible');

            // Verificar se a área de drop mostra que um arquivo foi selecionado
            var dropAreaComArquivo = false;
            if ($dropArea.length > 0) {
                dropAreaComArquivo = $dropArea.hasClass('file-selected');
                if (!dropAreaComArquivo) {
                    var $fileName = $dropArea.find('.file-selected-name');
                    // Importante: só considerar "tem arquivo" se o nome estiver visível.
                    // Quando o usuário remove o arquivo, o elemento pode ficar com texto
                    // (mas escondido), o que não deve contar como enviado.
                    dropAreaComArquivo = $fileName.length > 0 &&
                        $fileName.is(':visible') &&
                        $fileName.text().trim().length > 0;
                }
            }

            // Verificar se está com erro de validação
            var temErro = $input.hasClass('is-invalid');
            if (!temErro && $perguntaField.length > 0) {
                temErro = $perguntaField.find('.is-invalid').length > 0;
            }
            if (!temErro && $dropArea.length > 0) {
                temErro = $dropArea.hasClass('file-error');
            }

            //verifica se tem arquivo oriundo do rascunho
            var temArquivoRascunho = $input.attr('data-tem-resposta-rascunho') === 'Sim';

            // Verificar se o campo é obrigatório
            // Primeiro verifica o atributo data-obrigatorio (tentar ambos os métodos)
            var dataObrigatorio = $input.attr('data-obrigatorio') || $input.data('obrigatorio');
            var isObrigatorio = dataObrigatorio === '1' || dataObrigatorio === 1 || dataObrigatorio === true;

            // Se não encontrou pelo data-obrigatorio, verifica required
            if (!isObrigatorio) {
                isObrigatorio = $input.prop('required') === true || $input.attr('required') !== undefined;
            }

            // Se ainda não encontrou, verifica o label com asterisco
            if (!isObrigatorio && $label.length > 0) {
                var labelHtml = $label.html() || '';
                isObrigatorio = $label.find('.text-danger').length > 0 ||
                    $label.find('span.text-danger').length > 0 ||
                    labelHtml.indexOf('<span class="text-danger">*</span>') !== -1 ||
                    labelHtml.indexOf('text-danger') !== -1;
            }

            // Se ainda não encontrou, verifica se há asterisco no texto do label
            if (!isObrigatorio && $label.length > 0) {
                var labelText = $label.text() || '';
                isObrigatorio = labelText.indexOf('*') !== -1;
            }

            // Determinar o status do documento
            var temArquivo = temArquivoSelecionado || temArquivoExistente || dropAreaComArquivo || temArquivoRascunho;

            if (temErro) {
                // Documento com erro tem prioridade
                comErro++;
            } else if (temArquivo) {
                // Documento enviado (com arquivo e sem erro)
                enviados++;
            } else {
                // Se não tem arquivo, verificar se é obrigatório
                if (isObrigatorio) {
                    // Documento pendente (obrigatório sem arquivo e sem erro)
                    pendentes++;
                }
                // Campos opcionais sem arquivo não contam para nenhuma categoria
            }
        });

        // Atualizar os números no resumo
        $('#total-documentos').text(total);
        $('#documentos-enviados').text(enviados);
        $('#documentos-pendentes').text(pendentes);
        $('#documentos-erro').text(comErro);

        // Guardar para uso no botão Enviar e atualizar estado do botão
        AdesaoForm.ultimaContagemDocumentos = { pendentes: pendentes, comErro: comErro };
        if (typeof AdesaoForm.updateSubmitButtonState === 'function') {
            AdesaoForm.updateSubmitButtonState();
        }
    },

    initCnpjMask: function () {
        $('#ds_cnpj').on('input', function () {
            var value = $(this).val().replace(/\D/g, ''); // Remove tudo que não é dígito

            // Aplica a máscara: 00.000.000/0000-00
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');

                $(this).val(value);
            }

            // Validação em tempo real
            AdesaoForm.validateCnpj().then(function (isValid) {
                AdesaoForm.updateSubmitButtonState();
            });
        });

        // Aplicar máscara no valor inicial se existir
        var initialValue = $('#ds_cnpj').val();
        if (initialValue) {
            $('#ds_cnpj').trigger('input');
        }
    },

    initEstruturaGestaoFrontend: function () {
        var normalizar = function (texto) {
            return (texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        };
        var $etapa = $('.tab-agrupamento').filter(function () {
            return normalizar($(this).find('.etapa-titulo').first().text()).indexOf('estrutura e gestao') !== -1;
        }).first();
        if (!$etapa.length) return;

        $etapa.addClass('etapa-estrutura-gestao');

        var encontrarPergunta = function (trecho) {
            trecho = normalizar(trecho);
            return $etapa.find('.pergunta-field').filter(function () {
                var titulo = $(this).find('.form-label').first().text();
                return normalizar(titulo).indexOf(trecho) !== -1;
            }).first();
        };

        // Ato normativo: apresenta Tipo, Número e Ano em uma única linha.
        var $atoNormativo = encontrarPergunta('ato normativo de criacao do orgao');
        if ($atoNormativo.length) {
            var tituloAtoNormativo = normalizar($atoNormativo.find('.form-label').first().text());
            if (tituloAtoNormativo.indexOf('numero') !== -1 || tituloAtoNormativo.indexOf('ano') !== -1) {
                $atoNormativo = $etapa.find('.pergunta-field').filter(function () {
                    var titulo = normalizar($(this).find('.form-label').first().text());
                    return titulo.indexOf('ato normativo de criacao do orgao') !== -1 &&
                        titulo.indexOf('numero') === -1 && titulo.indexOf('ano') === -1;
                }).first();
            }
        }
        var $numeroAtoNormativo = encontrarPergunta('numero do ato normativo');
        var $anoAtoNormativo = encontrarPergunta('ano do ato normativo');
        if (!$numeroAtoNormativo.length) {
            $numeroAtoNormativo = $etapa.find('.pergunta-field').filter(function () {
                var titulo = normalizar($(this).find('.form-label').first().text());
                return titulo.indexOf('ato normativo') !== -1 && titulo.indexOf('numero') !== -1;
            }).first();
        }
        if (!$anoAtoNormativo.length) {
            $anoAtoNormativo = $etapa.find('.pergunta-field').filter(function () {
                var titulo = normalizar($(this).find('.form-label').first().text());
                return titulo.indexOf('ato normativo') !== -1 && titulo.indexOf('ano') !== -1;
            }).first();
        }
        if ($atoNormativo.length && $numeroAtoNormativo.length && $anoAtoNormativo.length) {
            if (!$atoNormativo.parent().hasClass('ato-normativo-perguntas')) {
                var $grupoAtoNormativo = $('<div>', {
                    class: 'ato-normativo-perguntas estrutura-bloco-separado'
                });
                $atoNormativo.before($grupoAtoNormativo);
                $grupoAtoNormativo.append($atoNormativo.addClass('ato-normativo-tipo'));
                $grupoAtoNormativo.append($numeroAtoNormativo.addClass('ato-normativo-numero'));
                $grupoAtoNormativo.append($anoAtoNormativo.addClass('ato-normativo-ano'));
            }

            $atoNormativo.find('select option[value=""]').first().text('Tipo');
            $numeroAtoNormativo.find('input:not([type="hidden"])').first()
                .attr({ placeholder: 'Número', 'aria-label': 'Número do Ato Normativo' });
            $anoAtoNormativo.find('input:not([type="hidden"])').first()
                .attr({ placeholder: 'Ano', 'aria-label': 'Ano do Ato Normativo' });
        }

        // A ordem das perguntas e dos anexos é definida no Joomla.
        var $uploadAto = encontrarPergunta('lei ou atos normativos sobre a criacao');
        var $uploadLoa = encontrarPergunta('anexar unidade orcamentaria');
        var $estruturaOrganizacional = encontrarPergunta('como se caracteriza a estrutura organizacional');
        $estruturaOrganizacional.addClass('estrutura-organizacional');
        $uploadLoa.addClass('estrutura-fim-subsecao');
        $uploadAto.addClass('estrutura-fim-subsecao');

        // Totais visuais dos quadros numéricos.
        $etapa.find('.pergunta-field[data-tipo-pergunta="distribuicao_numerica"]').each(function () {
            var $pergunta = $(this);
            var $titulo = $pergunta.find('p.form-label').first();
            if ($titulo.length && !$pergunta.find('.distribuicao-total').length) {
                $titulo.addClass('distribuicao-titulo');
                $titulo.append('<span class="distribuicao-total">Total: <strong>0</strong></span>');
            }
            var atualizarTotal = function () {
                var total = 0;
                $pergunta.find('input[type="number"]').each(function () {
                    total += parseInt($(this).val(), 10) || 0;
                });
                $pergunta.find('.distribuicao-total strong').text(total);
            };
            $pergunta.on('input change', 'input[type="number"]', atualizarTotal);
            atualizarTotal();
        });

        // Integra o campo "Outro:" à grade visual de Sexo sem alterar o campo
        // nem a sequência de perguntas definida no Joomla.
        var $sexo = encontrarPergunta('iii) sexo');
        var $outroSexo = encontrarPergunta('outro:');

        if ($sexo.length && $outroSexo.length) {
            var $opcoesSexo = $sexo.children('.row').first();
            if ($opcoesSexo.length && !$outroSexo.parent().is($opcoesSexo)) {
                $opcoesSexo.append(
                    $outroSexo.addClass('col-sm-6 col-md-4 mb-2 estrutura-sexo-outro')
                );
            }
        }

        encontrarPergunta('equipe permanente').addClass('estrutura-equipe-permanente estrutura-bloco-separado');

        // Sede própria e acesso à internet lado a lado.
        var $sede = encontrarPergunta('possui sede propria');
        var $internet = encontrarPergunta('possui acesso a internet');
        if ($sede.length && $internet.length && !$sede.parent().hasClass('estrutura-perguntas-binarias')) {
            var $binarias = $('<div>', { class: 'row estrutura-perguntas-binarias' });
            $sede.before($binarias);
            $binarias.append($sede.addClass('col-md-6'));
            $binarias.append($internet.addClass('col-md-6'));
        }
    },

    initCpfMask: function () {
        $('#ds_cpf').on('input', function () {
            var value = $(this).val().replace(/\D/g, '').slice(0, 11);
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            $(this).val(value);
            AdesaoForm.validateCpf();
            AdesaoForm.updateSubmitButtonState();
        });

        if ($('#ds_cpf').val()) {
            $('#ds_cpf').trigger('input');
        }
    },

    validateCpfSync: function () {
        var cpf = $('#ds_cpf').val().replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

        for (var t = 9; t < 11; t++) {
            var soma = 0;
            for (var i = 0; i < t; i++) {
                soma += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
            }
            var digito = ((10 * soma) % 11) % 10;
            if (parseInt(cpf.charAt(t), 10) !== digito) return false;
        }
        return true;
    },

    // Valida a força da senha: mínimo 8 caracteres, 1 maiúscula, 1 minúscula,
    // 1 número e 1 caractere especial.
    validatePasswordSync: function (senha) {
        senha = senha || '';
        var requisitosFaltando = [];

        if (senha.length < 8) requisitosFaltando.push('mínimo de 8 caracteres');
        if (!/[A-Z]/.test(senha)) requisitosFaltando.push('1 letra maiúscula');
        if (!/[a-z]/.test(senha)) requisitosFaltando.push('1 letra minúscula');
        if (!/\d/.test(senha)) requisitosFaltando.push('1 número');
        if (!/[^A-Za-z0-9]/.test(senha)) requisitosFaltando.push('1 caractere especial');

        if (requisitosFaltando.length > 0) {
            return {
                valido: false,
                mensagem: 'A senha deve conter: ' + requisitosFaltando.join(', ') + '.'
            };
        }

        return { valido: true, mensagem: '' };
    },

    validateCpf: function () {
        var cpf = $('#ds_cpf').val().replace(/\D/g, '');
        var $input = $('#ds_cpf');
        var $groupText = $input.siblings('.input-group-text');
        var $errorMsg = $('#cpf-error');

        $input.removeClass('is-invalid is-valid');
        $groupText.removeClass('is-invalid is-valid');
        $errorMsg.hide();

        if (!cpf.length) return false;
        if (!AdesaoForm.validateCpfSync()) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $errorMsg.text('Informe um CPF válido.').show();
            return false;
        }

        $input.addClass('is-valid');
        $groupText.addClass('is-valid');
        return true;
    },

    validateCnpjSync: function () {
        var cnpj = $('#ds_cnpj').val().replace(/\D/g, ''); // Remove formatação
        var $errorMsg = $('#cnpj-error');
        var $input = $('#ds_cnpj');
        var $groupText = $input.siblings('.input-group-text');

        // Verifica se está vazio
        if (cnpj.length === 0) {
            return false; // Para validação síncrona, campo vazio é inválido
        }

        // Verifica se tem 14 dígitos
        if (cnpj.length !== 14) {
            return false;
        }

        // Verifica se todos os dígitos são iguais (CNPJ inválido)
        if (/^(\d)\1+$/.test(cnpj)) {
            return false;
        }

        // Validação básica dos dígitos verificadores
        var tamanho = cnpj.length - 2;
        var numeros = cnpj.substring(0, tamanho);
        var digitos = cnpj.substring(tamanho);
        var soma = 0;
        var pos = tamanho - 7;

        // Valida primeiro dígito verificador
        for (var i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) {
                pos = 9;
            }
        }

        var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(0)) {
            return false;
        }

        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;

        // Valida segundo dígito verificador
        for (var i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) {
                pos = 9;
            }
        }

        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(1)) {
            return false;
        }

        // CNPJ válido localmente
        return true;
    },

    validateCnpj: function () {
        var cnpj = $('#ds_cnpj').val().replace(/\D/g, ''); // Remove formatação
        var $errorMsg = $('#cnpj-error');
        var $input = $('#ds_cnpj');
        var $groupText = $input.siblings('.input-group-text');

        // Remove classes de validação anteriores
        $input.removeClass('is-invalid is-valid');
        $groupText.removeClass('is-invalid is-valid');
        $errorMsg.hide();

        // Verifica se está vazio
        if (cnpj.length === 0) {
            return Promise.resolve(true); // Campo vazio não é erro (pode ser opcional)
        }

        // Verifica se tem 14 dígitos
        if (cnpj.length !== 14) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $errorMsg.text('CNPJ deve conter 14 dígitos').show();
            return Promise.resolve(false);
        }

        // Verifica se todos os dígitos são iguais (CNPJ inválido)
        if (/^(\d)\1+$/.test(cnpj)) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $errorMsg.text('CNPJ inválido').show();
            return Promise.resolve(false);
        }

        // Validação dos dígitos verificadores
        var tamanho = cnpj.length - 2;
        var numeros = cnpj.substring(0, tamanho);
        var digitos = cnpj.substring(tamanho);
        var soma = 0;
        var pos = tamanho - 7;

        // Valida primeiro dígito verificador
        for (var i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) {
                pos = 9;
            }
        }

        var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(0)) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $errorMsg.text('CNPJ inválido').show();
            return Promise.resolve(false);
        }

        tamanho = tamanho + 1;
        numeros = cnpj.substring(0, tamanho);
        soma = 0;
        pos = tamanho - 7;

        // Valida segundo dígito verificador
        for (var i = tamanho; i >= 1; i--) {
            soma += numeros.charAt(tamanho - i) * pos--;
            if (pos < 2) {
                pos = 9;
            }
        }

        resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
        if (resultado != digitos.charAt(1)) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $errorMsg.text('CNPJ inválido').show();
            return Promise.resolve(false);
        }

        // CNPJ válido localmente, agora verificar se já tem adesão
        $input.addClass('is-valid');
        $groupText.addClass('is-valid');
        $errorMsg.hide();

        // Retornar Promise que aguarda verificação via AJAX
        return this.consultaAdesaoCnpj(cnpj);
    },

    initEmailValidation: function () {
        // Validação em tempo real para o e-mail do responsável (obrigatório)
        $('#ds_email_responsavel').on('blur input', function () {
            AdesaoForm.validateEmail('ds_email_responsavel', true);
        });


        // Validar e-mails iniciais se já tiverem valores
        if ($('#ds_email_responsavel').val()) {
            AdesaoForm.validateEmail('ds_email_responsavel', true);
        }
    },

    validateEmail: function (fieldId, forceRequired) {
        var $input = $('#' + fieldId);
        var email = $input.val().trim();
        var $groupText = $input.siblings('.input-group-text');
        var $formGroup = $input.closest('.form-group');
        var $errorMsg = $formGroup.find('.email-error');

        // Verifica se o campo é obrigatório (forceRequired sobrescreve o atributo required)
        var isRequired = forceRequired === true || (forceRequired !== false && ($input.prop('required') || $input.attr('required') !== undefined));

        // Remove classes de validação anteriores
        $input.removeClass('is-invalid is-valid');
        $groupText.removeClass('is-invalid is-valid');

        // Remove mensagem de erro existente
        if ($errorMsg.length > 0) {
            $errorMsg.remove();
        }

        // Verifica se está vazio
        if (email.length === 0) {
            if (isRequired) {
                $input.addClass('is-invalid');
                $groupText.addClass('is-invalid');
                $formGroup.append('<small class="form-text text-danger email-error d-block">Este campo é obrigatório.</small>');
                return false;
            }
            // Campo opcional vazio é válido
            return true;
        }

        // Expressão regular para validar formato de e-mail
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        // Verifica formato básico do e-mail
        if (!emailRegex.test(email)) {
            $input.addClass('is-invalid');
            $groupText.addClass('is-invalid');
            $formGroup.append('<small class="form-text text-danger email-error  d-block">Por favor, insira um e-mail válido.</small>');
            return false;
        }


        // E-mail válido
        $input.addClass('is-valid');
        $groupText.addClass('is-valid');
        return true;
    },

    validateEmails: function () {
        var emailResponsavelValid = AdesaoForm.validateEmail('ds_email_responsavel', true);

        return emailResponsavelValid;
    },

    validateSolicitanteFields: function () {
        var $form = $('#form-adesao');
        var esfera = $form.find('input[name="tp_esfera_governamental"]:checked').val();
        var $solicitante = $('.solicitante');

        // Remove mensagens de erro anteriores dos campos do solicitante
        $solicitante.find('.solicitante-field-error').remove();
        $('#no_oficial, #sg_estado, #ds_cpf').removeClass('is-invalid');
        $('#ds_cnpj').add($('#ds_cnpj').siblings('.input-group-text')).removeClass('is-invalid');
        $('#ds_cpf').add($('#ds_cpf').siblings('.input-group-text')).removeClass('is-invalid');
        $('#cnpj-error').hide();
        $('#municipios-select-group').removeClass('is-invalid');
        $('#municipios-select-group .municipios-input-wrapper .form-control, #municipios-select-group .municipios-input-wrapper .form-select').removeClass('is-invalid');



        // 3. CPF do Solicitante obrigatório e válido
        var cpf = $('#ds_cpf').val().replace(/\D/g, '');
        if (!cpf.length || !AdesaoForm.validateCpfSync()) {
            $('#ds_cpf').addClass('is-invalid').closest('.form-group').find('.input-group-text').addClass('is-invalid');
            $('#cpf-error').text(cpf.length ? 'Informe um CPF válido.' : 'CPF do Solicitante é obrigatório.').show();
            AdesaoForm.activateTab('solicitante');
            AdesaoForm.showAlert('danger', cpf.length ? 'Informe um CPF do Solicitante válido.' : 'Preencha o CPF do Solicitante.');
            $('html, body').animate({ scrollTop: $('#ds_cpf').offset().top - 80 }, 300);
            return false;
        }

        var senha = $('#ds_senha').val();
        var confirmaSenha = $('#ds_confirma_senha').val();

        if (senha || confirmaSenha) {
            var senhaValidacao = AdesaoForm.validatePasswordSync(senha);
            if (!senhaValidacao.valido) {
                $('#ds_senha').addClass('is-invalid');
                $('#ds_senha').closest('.form-group').append('<small class="form-text text-danger solicitante-field-error d-block">' + senhaValidacao.mensagem + '</small>');
                AdesaoForm.activateTab('solicitante');
                AdesaoForm.showAlert('danger', senhaValidacao.mensagem);
                $('html, body').animate({ scrollTop: $('#ds_senha').offset().top - 80 }, 300);
                return false;
            }
            if (senha !== confirmaSenha) {
                $('#ds_confirma_senha').addClass('is-invalid');
                $('#ds_confirma_senha').closest('.form-group').append('<small class="form-text text-danger solicitante-field-error d-block">As senhas não coincidem.</small>');
                AdesaoForm.activateTab('solicitante');
                AdesaoForm.showAlert('danger', 'As senhas não coincidem.');
                $('html, body').animate({ scrollTop: $('#ds_confirma_senha').offset().top - 80 }, 300);
                return false;
            }
        }

        return true;
    },

    areAllRequiredSolicitanteFieldsFilled: function () {
        var esfera = $('#form-adesao').find('input[name="tp_esfera_governamental"]:checked').val();
        if (!esfera) return false;

        var cnpj = $('#ds_cnpj').val().replace(/\D/g, '');
        if (cnpj.length === 0) return false;
        if (!AdesaoForm.validateCnpjSync()) return false;

        if (!AdesaoForm.validateCpfSync()) return false;

        if (esfera === 'consorcio' && !$('#no_oficial').val().trim()) return false;
        if (!$('#sg_estado').val()) return false;

        if (esfera !== 'estadual') {
            if ($('input[name="ds_municipios[]"]').length === 0) return false;
        }

        if (esfera === 'consorcio') {
            var $emailsMunicipio = $('#municipios-emails-container input[type="email"]');
            if ($emailsMunicipio.length === 0) return false;
            var emailsMunicipioOk = true;
            $emailsMunicipio.each(function () {
                var email = ($(this).val() || '').trim();
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    emailsMunicipioOk = false;
                    return false;
                }
            });
            if (!emailsMunicipioOk) return false;
        }

        if (!AdesaoForm.validateEmail('ds_email_responsavel', true)) return false;
        return true;
    },

    updateSubmitButtonState: function () {
        AdesaoForm.atualizarBarraProgresso();
        AdesaoForm.atualizarMensagemProntoParaEnvio();
    },

    // Verifica se todos os campos obrigatórios (solicitante + perguntas, incluindo
    // as que estão em abas ocultas e os documentos) estão preenchidos.
    todosCamposObrigatoriosPreenchidos: function () {
        if (!AdesaoForm.areAllRequiredSolicitanteFieldsFilled()) {
            return false;
        }

        if (AdesaoForm.encontrarPerguntaObrigatoriaVazia()) {
            return false;
        }

        var contagemDocumentos = AdesaoForm.ultimaContagemDocumentos;
        if (contagemDocumentos && (contagemDocumentos.pendentes > 0 || contagemDocumentos.comErro > 0)) {
            return false;
        }

        return true;
    },

    // Exibe/oculta a mensagem "Tudo pronto para envio!" no último tab-agrupamento
    atualizarMensagemProntoParaEnvio: function () {
        var $ultimaTab = $('.tab-agrupamento').last();
        if ($ultimaTab.length === 0) {
            return;
        }

        var $mensagem = $ultimaTab.find('#mensagem-pronto-envio');
        if ($mensagem.length === 0) {
            $mensagem = $(
                '<div id="mensagem-pronto-envio" class="alert alert-success mensagem-pronto-envio d-none mt-3" role="alert">' +
                '<i class="fa-solid fa-circle-check me-2"></i>Tudo pronto para envio!' +
                '</div>'
            );

            var $destino = $ultimaTab.find('.card-body').last();
            if ($destino.length === 0) {
                $destino = $ultimaTab;
            }
            $destino.append($mensagem);
        }

        $mensagem.toggleClass('d-none', !AdesaoForm.todosCamposObrigatoriosPreenchidos());
    
        var $mensagemIncompleto = $ultimaTab.find('#mensagem-incompleto');
        if ($mensagemIncompleto.length === 0) {
            $mensagemIncompleto = $(
                '<div id="mensagem-incompleto" class="alert alert-warning mensagem-incompleto d-none mt-3" role="alert">' +
                '<i class="fa-solid fa-circle-exclamation me-2"></i>Existem campos obrigatórios não preenchidos ou com erros. Por favor, revise o formulário.' +
                '</div>'
            );

            var $destino = $ultimaTab.find('.card-body').last();
            if ($destino.length === 0) {
                $destino = $ultimaTab;
            }
            $destino.append($mensagemIncompleto);
        }

        $mensagemIncompleto.toggleClass('d-none', AdesaoForm.todosCamposObrigatoriosPreenchidos());
    },

    submitForm: function (rascunho, action) {
        var formData = new FormData($("#form-adesao")[0]);

        formData.append('rascunho', rascunho ? '1' : '0');

        // Show loading state
        var $submitBtn = $("#form-adesao button[type='submit']");
        $submitBtn.each(function() {
            $(this).attr('text-before-send', $(this).html());
        });
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Salvando...');

        $.ajax({
            url: $("#form-adesao").attr("action"),
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            headers: {
                'X-CSRF-Token': window.adesaoConfig.token
            },
            success: function (response) {
                if (response.success) {
                    // Show success message
                    AdesaoForm.showAlert('success', response.message || 'Formulário enviado com sucesso!');

                    if(rascunho) {
                        if(action === 'proxima-etapa') {
                            var indiceAtual = $('.nav-link-agrupamento.active').parent().index();
                            var proximoIndice = Math.min(AdesaoForm.navLinks.length - 1, indiceAtual + 1);
                            AdesaoForm.activateTab(AdesaoForm.navLinks.eq(proximoIndice).attr('data-tab-target'));

                        }

                        //preenche dados como se tivesse logado o usuario
                        if($("#co_adesao").val().length == 0) {
                            $("#co_adesao").val(response.data.id);
                            $(".campos-senha").remove();
                            $("#ds_email_responsavel").prop('readonly', true);
                            $("#id_usuario_logado").val(response.data.userId);
                        }
                        // Refresh CSRF token for subsequent rascunho submissions
                        if (response.token) {
                            $('#form-adesao input[name="' + window.adesaoConfig.token + '"]').remove();
                            $('<input type="hidden">').attr('name', response.token).val('1').appendTo('#form-adesao');
                            window.adesaoConfig.token = response.token;
                        }
                        // Re-enable submit button
                        $submitBtn.prop('disabled', false);
                        $submitBtn.each(function() {
                            $(this).html($(this).attr('text-before-send'));
                        });
                        return; //nao redireiciona se for rascunho
                    }
                    // Redirect after 2 seconds
                    setTimeout(function () {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 2000);
                } else {
                    // Show error messages
                    var errorMsg = 'Erros encontrados:<ul>';
                    if (response.messages && Array.isArray(response.messages)) {
                        response.messages.forEach(function (msg) {
                            errorMsg += '<li>' + msg + '</li>';
                        });
                    } else {
                        errorMsg += '<li>' + (response.message || 'Erro desconhecido') + '</li>';
                    }
                    errorMsg += '</ul>';
                    AdesaoForm.showAlert('danger', errorMsg);

                    // Re-enable submit button
                    $submitBtn.prop('disabled', false);
                    $submitBtn.each(function() {
                        $(this).html($(this).attr('text-before-send'));
                    });
                    //reset cloudflare turstile
                    turnstile.reset();
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro AJAX:', error, xhr.responseText);
                var message = 'Ocorreu um erro ao enviar o formulário. Por favor, tente novamente.';
                if (xhr.status === 413) {
                    message = 'O envio excedeu o tamanho máximo permitido. Reduza os arquivos anexados e tente novamente.';
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                AdesaoForm.showAlert('danger', message);

                // Re-enable submit button
                $submitBtn.prop('disabled', false);
                $submitBtn.each(function() {
                    $(this).html($(this).attr('text-before-send'));
                });
                //reset cloudflare turstile
                turnstile.reset();
            },
            always: function () {
                $submitBtn.prop('disabled', false);
                $submitBtn.each(function() {
                    $(this).html($(this).attr('text-before-send'));
                });
                //reset cloudflare turstile
                turnstile.reset();
            }
        });
    },
    showAlert: function (type, message) {
        // Remove existing alerts
        $('#form-adesao .alert').remove();

        // Create new alert
        var $alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>');

        // Prepend to form
        $('#form-adesao').prepend($alert);

        // Scroll to alert
        $('html, body').animate({
            scrollTop: $alert.offset().top - 100
        }, 500);
    },
    initFormSolicitante: function () {
        var updateMunicipiosLabel = function (valor) {
            var ocultarNomeOficial = valor === 'estadual' || valor === 'municipal';
            $('#nome-oficial-group').toggleClass('d-none', ocultarNomeOficial);
            $('#campos-obrigatorios-consorcio').toggleClass('d-none', valor !== 'consorcio');
            $('#municipios-tags-container').removeClass('d-none');

            $('#labelCnpjSolicitante').html('CNPJ Solicitante <span class="text-danger">*</span>');

            if (valor === 'consorcio') {
                $('#contato-solicitante-row').insertBefore('#localizacao-solicitante-row');
                $('#labelEstadoSolicitante').html('Estado do Solicitante <span class="text-danger">*</span>');
                $('#sg_estado option[value=""]').text('Selecione um ou mais Estados');
            } else {
                $('#contato-solicitante-row').insertAfter('#localizacao-solicitante-row');
                $('#labelEstadoSolicitante').html('UF do Solicitante <span class="text-danger">*</span>');
                $('#sg_estado option[value=""]').text('Selecione um estado');
            }

            if (valor === 'estadual' && $('#sg_estado').val()) {
                $('#no_oficial').val($('#sg_estado option:selected').text());
            }

            if (valor === 'estadual') {
                $(".orientacao-consorcio").addClass('d-none');
                $('#ds_municipios_select').prop('disabled', true);
                $("#municipios-select-group").hide();
            } else if (valor === 'municipal') {
                $(".orientacao-consorcio").addClass('d-none');
                $('#ds_municipios_select').prop('disabled', false);
                $("#municipios-select-group").show();
                $("#label-municipio").html("Município <span class=\"text-danger municipios-required-asterisk\">*</span>");
            } else if (valor === 'consorcio') {
                $('#ds_municipios_select').prop('disabled', false);
                $(".orientacao-consorcio").addClass('d-none');
                $("#municipios-select-group").show();
                $("#label-municipio").html("Municípios integrantes dos Consórcios/Associações <span class=\"text-danger municipios-required-asterisk\">*</span>");
            }
        };

        $('.solicitante .form-check-input').on('change', function () {
            var valor = $(this).val();
            if ($(this).is(':checked')) {
                $(".formSolicitante").removeClass('d-none');
                updateMunicipiosLabel(valor);
            }
            AdesaoForm.updateSubmitButtonState();
        });

        // Ao carregar, aplicar estado inicial (ex.: edição com tipo já selecionado)
        var $checked = $('.solicitante .form-check-input:checked');
        if ($checked.length) {
            $(".formSolicitante").removeClass('d-none');
            updateMunicipiosLabel($checked.val());
        }
        AdesaoForm.updateSubmitButtonState();
    },

    initSubmitButtonState: function () {
        $('#no_oficial, #sg_estado, #ds_cpf, #ds_email_responsavel, #ds_senha, #ds_confirma_senha').on('input change', function () {
            AdesaoForm.updateSubmitButtonState();
        });
        $('#municipios-emails-container').on('input change', 'input[type="email"]', function () {
            AdesaoForm.updateSubmitButtonState();
        });
    },
    montarMensagemErroCnpj: function (protocolo) {
        var mensagem = (window.adesaoConfig && window.adesaoConfig.mensagemErroCnpj)
            ? String(window.adesaoConfig.mensagemErroCnpj)
            : '';
        var efetueLogin = $('#loginBtn').length
            ? '<a href="#" data-bs-toggle="modal" data-bs-target="#loginModal">efetue login</a>'
            : 'efetue login';
        var protocoloSeguro = $('<div>').text(protocolo == null ? '' : String(protocolo)).html();

        if (!mensagem.trim()) {
            mensagem = 'CNPJ já possui adesão cadastrada. Protocolo: {protocolo}. Caso o preenchimento do formulário de adesão já tenha sido iniciado, {login} com o CPF e a senha cadastrados para continuar o preenchimento. Em caso de dúvidas, entre em contato pelo e-mail <a href="mailto:sinapir@igualdaderacial.gov.br">sinapir@igualdaderacial.gov.br</a>.';
        }

        return mensagem
            .replace(/\{protocolo\}/g, protocoloSeguro)
            .replace(/\{login\}/g, efetueLogin);
    },

    consultaAdesaoCnpj: function (cnpj) {
        var ajaxData = {
            option: 'com_edital',
            task: 'adesaoform.consultaAdesaoCnpj',
            cnpj: cnpj,
            id_edital: $("#co_edital").val()
        };
        ajaxData[window.adesaoConfig.token] = 1;

        return new Promise(function (resolve, reject) {
            $.ajax({
                url: window.adesaoConfig.baseUrl + 'index.php',
                type: 'GET',
                data: ajaxData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // CNPJ já possui adesão cadastrada
                        $("#ds_cnpj").removeClass('is-valid').addClass('is-invalid');
                        $("#ds_cnpj").siblings('.input-group-text').removeClass('is-valid').addClass('is-invalid');
                        $('#cnpj-error').html(AdesaoForm.montarMensagemErroCnpj(response.data)).show();
                        AdesaoForm.updateSubmitButtonState();
                        resolve(false); // CNPJ inválido (já tem adesão)
                    } else {
                        // CNPJ não possui adesão, é válido
                        resolve(true);
                    }
                },
                error: function (xhr, status, error) {
                    console.debug('Erro na consulta do CNPJ:', error, xhr.responseText);
                    // Em caso de erro na consulta, considerar válido para não bloquear o usuário
                    resolve(true);
                }
            });
        });
    },

    validateCaptcha: function () {
        // Verificar se o captcha está habilitado no Joomla
        if (typeof window.captchaEnabled === 'undefined' || !window.captchaEnabled) {
            return true; // Se captcha não está habilitado, validação passa
        }

        // Verificar se o plugin reCAPTCHA está sendo usado
        if (typeof grecaptcha !== 'undefined') {
            // reCAPTCHA v2
            var response = grecaptcha.getResponse();
            if (!response || response.length === 0) {
                return false;
            }
            return true;
        }

        // Para outros tipos de captcha (quando implementados)
        var $captchaInput = $('#captcha_response, input[name*="captcha"]').first();
        if ($captchaInput.length && $captchaInput.val().trim().length === 0) {
            return false;
        }

        return true;
    },

}

jQuery(document).ready(function ($) {
    'use strict';

    const $estadoSelect = $('#sg_estado');
    const $municipioSelect = $('#ds_municipios_select');
    const $tagsContainer = $('#municipios-tags-container');
    const $hiddenInputsContainer = $('#municipios-hidden-inputs');
    const $formGroup = $municipioSelect.closest('.form-group');
    const $emailsGroup = $('#municipios-emails-group');
    const $emailsContainer = $('#municipios-emails-container');

    if (!$estadoSelect.length || !$municipioSelect.length) return;

    // Armazenar municípios selecionados {co_ibge: no_municipio}
    let municipiosSelecionados = {};

    // Verifica se o tipo de solicitante atual é Consórcio de Municípios
    function isConsorcio() {
        return $('#esfera-consorcio').is(':checked');
    }

    // Aplica o título alternativo (data-titulo-consorcio) aos elementos marcados,
    // preservando o título original em data-titulo-original para poder restaurá-lo depois.
    function aplicarTituloSeConsorcio() {
        if (isConsorcio()) {
            $("[data-titulo-consorcio]").each(function () {
                $(this).attr('data-titulo-original', $(this).html());
                $(this).html($(this).data('titulo-consorcio'));
            });
        }else{
            $("[data-titulo-consorcio]").each(function () {
                $(this).html($(this).attr('data-titulo-original'));
            });
        }
    }

    // Atualiza a visibilidade do grupo de e-mails por município
    function atualizarVisibilidadeEmailsGrupo() {
        var deveExibir = isConsorcio() && $emailsContainer.children().length > 0;
        $emailsGroup.toggleClass('d-none', !deveExibir);
    }

    // Cria (se ainda não existir) o campo de e-mail vinculado a um município,
    // exibido apenas quando o solicitante for do tipo Consórcio.
    function criarCampoEmailMunicipio(coIbge, noMunicipio, sgEstado, dsEmail) {
        if (!isConsorcio()) {
            return;
        }

        if ($emailsContainer.find('[data-co-ibge="' + coIbge + '"]').length > 0) {
            if (dsEmail) {
                $emailsContainer.find('#ds_email_municipio_' + coIbge).val(dsEmail);
            }
            return; // Já existe
        }

        var estado = sgEstado || $estadoSelect.val();
        var $row = $('<div>', {
            class: 'municipio-email-row',
            'data-co-ibge': coIbge
        });

        var inputId = 'ds_email_municipio_' + coIbge;

        $row.append($('<label>', {
            for: inputId,
            class: 'form-label municipio-email-label',
            text: noMunicipio + (estado ? ' (' + estado + ')' : '')
        }));

        $row.append($('<input>', {
            type: 'email',
            id: inputId,
            class: 'form-control municipio-email-input',
            name: 'ds_email_municipios[' + coIbge + ']',
            placeholder: 'Ex.: contato@' + 'municipio.gov.br',
            required: true,
            value: dsEmail || '',
            'data-co-ibge': coIbge
        }));

        $emailsContainer.append($row);
        atualizarVisibilidadeEmailsGrupo();
    }

    // Remove o campo de e-mail vinculado a um município
    function removerCampoEmailMunicipio(coIbge) {
        $emailsContainer.find('.municipio-email-row[data-co-ibge="' + coIbge + '"]').remove();
        atualizarVisibilidadeEmailsGrupo();
    }

    // Limpa todos os campos de e-mail por município
    function limparCamposEmailMunicipios() {
        $emailsContainer.empty();
        atualizarVisibilidadeEmailsGrupo();
    }

    // Função para adicionar uma tag
    function adicionarTag(coIbge, noMunicipio, sgEstado, dsEmail) {
        if (municipiosSelecionados[coIbge]) {
            return; // Já está selecionado
        }

        municipiosSelecionados[coIbge] = noMunicipio;

        // O backend ainda exige no_oficial; para município ele é derivado da seleção.
        if ($('#esfera_municipio').is(':checked')) {
            $('#no_oficial').val(noMunicipio).trigger('input');
        }

        // Desabilitar a opção no select
        const $option = $municipioSelect.find('option[value="' + coIbge + '"]');
        if ($option.length) {
            $option.prop('disabled', true);
        }

        // Criar tag visual
        const $tag = $('<div>', {
            class: 'municipio-tag',
            'data-co-ibge': coIbge
        });

        $tag.append($('<span>', {
            text: $('#esfera-consorcio').is(':checked')
                ? noMunicipio
                : noMunicipio + " (" + (sgEstado || $("#sg_estado").val()) + ")"
        }));

        const $removeBtn = $('<button>', {
            type: 'button',
            class: 'municipio-tag-remove',
            'aria-label': 'Remover ' + noMunicipio,
            html: '×'
        });

        $removeBtn.on('click', function () {
            removerTag(coIbge);
        });

        $tag.append($removeBtn);

        $tagsContainer.append($tag);

        // Criar campo hidden para o formulário
        const $hiddenInput = $('<input>', {
            type: 'hidden',
            name: 'ds_municipios[]',
            value: coIbge,
            'data-co-ibge': coIbge
        });
        $hiddenInputsContainer.append($hiddenInput);

        // Criar campo de e-mail vinculado ao município (apenas para Consórcio)
        criarCampoEmailMunicipio(coIbge, noMunicipio, sgEstado, dsEmail);

        // Limpar seleção do select
        $municipioSelect.val('');
        if (typeof AdesaoForm !== 'undefined' && AdesaoForm.updateSubmitButtonState) {
            AdesaoForm.updateSubmitButtonState();
        }
    }

    // Função para remover uma tag
    function removerTag(coIbge) {
        if (!municipiosSelecionados[coIbge]) {
            return;
        }

        delete municipiosSelecionados[coIbge];

        // Reabilitar a opção no select
        const $option = $municipioSelect.find('option[value="' + coIbge + '"]');
        if ($option.length) {
            $option.prop('disabled', false);
        }

        // Remover tag visual
        $tagsContainer.find('.municipio-tag[data-co-ibge="' + coIbge + '"]').remove();

        // Remover campo hidden
        $hiddenInputsContainer.find('input[data-co-ibge="' + coIbge + '"]').remove();
        if ($('#esfera_municipio').is(':checked')) {
            $('#no_oficial').val('').trigger('input');
        }

        // Remover campo de e-mail vinculado ao município
        removerCampoEmailMunicipio(coIbge);
        if (typeof AdesaoForm !== 'undefined' && AdesaoForm.updateSubmitButtonState) {
            AdesaoForm.updateSubmitButtonState();
        }
    }

    // Limpar todas as tags
    function limparTags() {
        // Reabilitar todas as opções desabilitadas
        $municipioSelect.find('option:disabled').prop('disabled', false);

        // Limpar objeto
        Object.keys(municipiosSelecionados).forEach(function (key) {
            delete municipiosSelecionados[key];
        });
        $tagsContainer.empty();
        $hiddenInputsContainer.empty();
        if ($('#esfera_municipio').is(':checked')) {
            $('#no_oficial').val('').trigger('input');
        }
        limparCamposEmailMunicipios();
        if (typeof AdesaoForm !== 'undefined' && AdesaoForm.updateSubmitButtonState) {
            AdesaoForm.updateSubmitButtonState();
        }
    }

    // Evento de mudança no select de municípios
    $municipioSelect.on('change', function () {
        const coIbge = $(this).val();
        const noMunicipio = $(this).find('option:selected').text();

        if (coIbge && noMunicipio && noMunicipio !== 'Selecione um estado primeiro' && noMunicipio !== 'Carregando...' && noMunicipio !== 'Selecione um ou mais municípios') {
            if ($("#esfera_municipio").is(':checked')) {
                limparTags();
            }
            adicionarTag(coIbge, noMunicipio);
        }
    });

    // Initialize select styling
    $municipioSelect.addClass('form-select');

    $estadoSelect.on('change', function () {
        const coEstado = $(this).val();

        // O nome oficial do Estado passa a ser derivado da UF selecionada.
        if ($('#esfera_estado').is(':checked')) {
            $('#no_oficial').val(coEstado ? $(this).find('option:selected').text() : '').trigger('input');
        }

        // Limpar tags quando mudar de estado
        if (!$("#esfera-consorcio").is(':checked')) {
            limparTags();
        }

        if (!coEstado) {
            $municipioSelect.html('<option value="">Selecione um estado primeiro</option>')
                .prop('disabled', true);
            return;
        }

        // Show loading with spinner
        $municipioSelect.html('<option value="">Carregando...</option>')
            .prop('disabled', true);

        // Add loading spinner
        const $spinner = $('<div class="spinner-border spinner-border-sm ms-2" role="status"><span class="visually-hidden">Carregando...</span></div>');
        $formGroup.find('.spinner-border').remove();
        $formGroup.find('label').append($spinner);

        // Fetch municipalities via AJAX
        var ajaxData = {
            option: 'com_edital',
            task: 'adesaoform.getMunicipios',
            co_estado: coEstado
        };
        ajaxData[window.adesaoConfig.token] = 1;

        $.ajax({
            url: window.adesaoConfig.baseUrl + 'index.php',
            type: 'GET',
            dataType: 'json',
            data: ajaxData,
            success: function (data) {
                $spinner.remove();

                if (data.success && data.data) {
                    $municipioSelect.empty();

                    // Add default option
                    $municipioSelect.append($('<option>', {
                        value: '',
                        text: 'Selecione um município'
                    }));

                    // Add municipalities
                    $.each(data.data, function (index, municipio) {
                        // Verificar se o município já está selecionado
                        const jaSelecionado = municipiosSelecionados[municipio.co_ibge] ? true : false;

                        $municipioSelect.append($('<option>', {
                            value: municipio.co_ibge,
                            text: municipio.no_municipio,
                            disabled: jaSelecionado
                        }));
                    });

                    $municipioSelect.prop('disabled', false);

                    // Show success feedback
                    // $formGroup.find('.text-success').remove();
                    // $formGroup.append('<small class="text-success d-block mt-1"><i class="fas fa-check-circle"></i> ' + data.data.length + ' municípios carregados</small>');
                    setTimeout(() => $formGroup.find('.text-success').fadeOut(), 3000);
                } else {
                    $municipioSelect.html('<option value="">Erro ao carregar municípios</option>');
                    showAlert('danger', data.message || 'Erro ao carregar municípios');
                }
            },
            error: function (xhr, status, error) {
                $spinner.remove();
                $municipioSelect.html('<option value="">Erro ao carregar municípios</option>')
                    .prop('disabled', false);
                showAlert('danger', 'Erro na requisição: ' + error);
                console.error('Erro AJAX:', error);
            }
        });
    });

    // Carregar municípios automaticamente se o estado já estiver selecionado (edição).
    // Quando a localidade vem travada pelo link de acesso, as tags já são renderizadas
    // pelo servidor e não há lista de municípios a carregar.
    if ($estadoSelect.val() && !(window.adesaoConfig && window.adesaoConfig.prefillTravado)) {
        $estadoSelect.trigger('change');
    }

    function emailSalvoMunicipio(coIbge) {
        var dsEmail = '';
        if (!Array.isArray(window.municipiosSalvos)) {
            return dsEmail;
        }

        window.municipiosSalvos.forEach(function (municipio) {
            if (String(municipio.co_ibge) === String(coIbge) && municipio.ds_email) {
                dsEmail = municipio.ds_email;
            }
        });

        return dsEmail;
    }

    // Quando a localidade vem travada pelo link, as tags já estão no HTML.
    // Sincroniza o estado JS e garante um campo de e-mail por município do consórcio.
    function sincronizarMunicipiosPrefill() {
        $tagsContainer.find('.municipio-tag').each(function () {
            var coIbge = String($(this).attr('data-co-ibge') || '');
            if (!coIbge) {
                return;
            }

            var noMunicipio = $(this).attr('data-no-municipio') || $(this).find('span').first().text();
            var sgEstado = $(this).attr('data-sg-estado') || $estadoSelect.val();

            municipiosSelecionados[coIbge] = noMunicipio;
            criarCampoEmailMunicipio(coIbge, noMunicipio, sgEstado, emailSalvoMunicipio(coIbge));
        });

        atualizarVisibilidadeEmailsGrupo();
    }

    if (window.adesaoConfig && window.adesaoConfig.prefillTravado) {
        sincronizarMunicipiosPrefill();
    } else if (Array.isArray(window.municipiosSalvos)) {
        // Recriar as tags dos municípios já salvos (retomada de rascunho)
        window.municipiosSalvos.forEach(function (municipio) {
            adicionarTag(municipio.co_ibge, municipio.no_municipio, municipio.sg_estado, municipio.ds_email);
        });
    }

    //limpar seleção ao mudar esfera
    $('input[name="tp_esfera_governamental"]').on('change', function () {
        limparTags();

        $("[data-titulo-original]").each(function () {
            $(this).html($(this).data('data-titulo-original'));
        });

        if ($(this).val() === 'municipal') {
            $('#nome-oficial-group').addClass('d-none');
            $('#labelCnpjSolicitante').html('CNPJ Solicitante <span class="text-danger">*</span>');
            $('#no_oficial').val('');
        }

        else if ($(this).val() === 'consorcio') {
            $('#nome-oficial-group').removeClass('d-none');
            $('#labelCnpjSolicitante').html('CNPJ Solicitante <span class="text-danger">*</span>');
            $("#labelNomeOficial").html("Nome dos Consórcios/Associações <span class=\"text-danger\">*</span>");
            $("#no_oficial").attr('placeholder', 'Informe o nome oficial (Consórcios/Associações)');
            $('#no_oficial').val('');
        }
        else {
            $('#nome-oficial-group').addClass('d-none');
            $('#labelCnpjSolicitante').html('CNPJ Solicitante <span class="text-danger">*</span>');
            $('#no_oficial').val($estadoSelect.val() ? $estadoSelect.find('option:selected').text() : '');
        }
        AdesaoForm.updateSubmitButtonState();
        aplicarTituloSeConsorcio();
    });

    // Helper function to show Bootstrap alerts
    function showAlert(type, message) {
        const $alert = $('<div class="alert alert-' + type + ' alert-dismissible fade show mt-2" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>');

        $formGroup.find('.alert').remove();
        $formGroup.append($alert);

        setTimeout(() => $alert.fadeOut(() => $alert.remove()), 5000);
    }

    // File upload drag and drop functionality
    $('.file-drop-area').each(function () {
        const $dropArea = $(this);
        const fileInputId = $dropArea.data('file-input');
        const $fileInput = $('#' + fileInputId);
        const $fileName = $dropArea.find('.file-selected-name');
        const $dropMessage = $dropArea.find('.file-drop-message, .file-drop-hint, .file-drop-icon');

        // Function to validate file extension based on accept attribute
        function validateFileExtension(file, $input) {
            const acceptAttr = $input.attr('accept');
            if (!acceptAttr) {
                return { valid: true }; // Se não há restrição, aceita qualquer arquivo
            }

            const fileName = file.name.toLowerCase();
            const fileExtension = '.' + fileName.split('.').pop();
            const acceptedTypes = acceptAttr.toLowerCase().split(',').map(type => type.trim());

            let isValid = false;
            let acceptedExtensions = [];

            for (let type of acceptedTypes) {
                if (type.startsWith('.')) {
                    // Extensão específica (ex: .pdf, .jpg)
                    acceptedExtensions.push(type);
                    if (fileName.endsWith(type)) {
                        isValid = true;
                        break;
                    }
                } else if (type.includes('/')) {
                    // MIME type (ex: image/*, application/pdf)
                    const [mainType, subType] = type.split('/');
                    if (subType === '*') {
                        // Tipo genérico (ex: image/*, audio/*)
                        if (file.type.startsWith(mainType + '/')) {
                            isValid = true;
                            break;
                        }
                    } else {
                        // MIME type específico
                        if (file.type === type) {
                            isValid = true;
                            break;
                        }
                    }
                }
            }

            // Construir lista de extensões aceitas para mostrar no erro
            if (!isValid) {
                // Extrair extensões do accept attribute para mensagem de erro
                const extensions = acceptedTypes
                    .filter(type => type.startsWith('.'))
                    .join(', ')
                    .toUpperCase();

                const mimeTypes = acceptedTypes
                    .filter(type => type.includes('/'))
                    .map(type => {
                        if (type === 'image/*') return 'imagens';
                        if (type === 'application/pdf') return 'PDF';
                        if (type === 'application/msword') return 'DOC';
                        if (type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') return 'DOCX';
                        return type;
                    });

                let acceptedFormats = [];
                if (extensions) acceptedFormats.push(extensions);
                if (mimeTypes.length > 0) acceptedFormats.push(...mimeTypes);

                return {
                    valid: false,
                    message: acceptedFormats.length > 0
                        ? `Formato não permitido. Formatos aceitos: ${acceptedFormats.join(', ')}`
                        : 'Formato de arquivo não permitido'
                };
            }

            return { valid: true };
        }

        // Function to show file error
        function showFileError($input, $dropArea, message) {
            $input.addClass('is-invalid');
            $dropArea.addClass('file-error').removeClass('file-selected');
            $dropArea.find('.file-selected-name').hide().empty();
            $dropArea.find('.file-remove-btn').remove();

            // Remove mensagem de erro anterior
            $dropArea.find('.file-error-message').remove();

            // Adicionar mensagem de erro
            $fileName.hide();
            $dropMessage.hide();
            $dropArea.append(`<div class="file-error-message text-danger"><i class="fas fa-exclamation-triangle me-2"></i>${message}</div>`);

            // Limpar o input
            $input.val('');

            // Atualizar contagem de documentos
            if (typeof AdesaoForm !== 'undefined' && AdesaoForm.atualizarContagemDocumentos) {
                setTimeout(() => AdesaoForm.atualizarContagemDocumentos(), 50);
            }
        }

        // Function to show file success
        function showFileSuccess(file, $input, $dropArea, $fileName, $dropMessage) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB

            $dropMessage.hide();
            $dropArea.find('.file-error-message').remove();
            $dropArea.find('.file-remove-btn').remove();
            $fileName.html('<i class="fas fa-file-alt me-2"></i><strong>' + fileName + '</strong> (' + fileSize + ' MB)').show();

            const $removeBtn = $('<button>', {
                type: 'button',
                class: 'btn btn-link btn-sm file-remove-btn text-danger p-0 ms-2',
                html: '<i class="fas fa-times me-1"></i>'
            });

            $removeBtn.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $fileInput.val('');
                $fileInput.trigger('change');
            });

            $dropArea.find('.file-selected-name').append($removeBtn);
            $dropArea.addClass('file-selected').removeClass('file-error');
            $input.removeClass('is-invalid');

            // Atualizar contagem de documentos
            if (typeof AdesaoForm !== 'undefined' && AdesaoForm.atualizarContagemDocumentos) {
                setTimeout(() => AdesaoForm.atualizarContagemDocumentos(), 50);
            }
        }

        // Click to upload
        $dropArea.on('click', function (event) {
            if ($(event.target).closest('.file-remove-btn').length) {
                return;
            }
            $fileInput.trigger('click');
        });

        // File input change
        $fileInput.on('change', function () {
            const files = this.files;
            if (files.length > 0) {
                const file = files[0];

                // Validar extensão do arquivo
                const validation = validateFileExtension(file, $fileInput);

                if (!validation.valid) {
                    showFileError($fileInput, $dropArea, validation.message);
                    return;
                }

                // Validar tamanho do arquivo (10MB max)
                const maxSize = 10 * 1024 * 1024; // 10MB em bytes
                if (file.size > maxSize) {
                    showFileError($fileInput, $dropArea, 'Arquivo muito grande. Tamanho máximo permitido: 10MB');
                    return;
                }

                showFileSuccess(file, $fileInput, $dropArea, $fileName, $dropMessage);
            } else {
                // Se o arquivo foi removido
                $dropMessage.show();
                $fileName.hide().empty();
                $dropArea.removeClass('file-selected file-error');
                $dropArea.find('.file-error-message').remove();
                $dropArea.find('.file-remove-btn').remove();
                $fileInput.removeClass('is-invalid');

                // Atualizar contagem quando arquivo é removido
                if (typeof AdesaoForm !== 'undefined' && AdesaoForm.atualizarContagemDocumentos) {
                    setTimeout(() => AdesaoForm.atualizarContagemDocumentos(), 50);
                }
            }
        });

        // Drag events
        $dropArea.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropArea.addClass('dragover');
        });

        $dropArea.on('dragleave dragend', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropArea.removeClass('dragover');
        });

        $dropArea.on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropArea.removeClass('dragover');

            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];

                // Validar extensão do arquivo
                const validation = validateFileExtension(file, $fileInput);

                if (!validation.valid) {
                    showFileError($fileInput, $dropArea, validation.message);
                    return;
                }

                // Validar tamanho do arquivo (10MB max)
                const maxSize = 10 * 1024 * 1024; // 10MB em bytes
                if (file.size > maxSize) {
                    showFileError($fileInput, $dropArea, 'Arquivo muito grande. Tamanho máximo permitido: 10MB');
                    return;
                }

                // Se passou na validação, definir o arquivo no input
                $fileInput[0].files = files;
                showFileSuccess(file, $fileInput, $dropArea, $fileName, $dropMessage);
            }
        });
    });

    // Atualizar contagem de documentos quando a página carregar completamente
    // Isso garante que arquivos existentes (file-current) sejam contados
    setTimeout(function () {
        if (typeof AdesaoForm !== 'undefined' && AdesaoForm.atualizarContagemDocumentos) {
            AdesaoForm.atualizarContagemDocumentos();
        }
    }, 500);

    // Aplicar o título alternativo de Consórcio já na carga da página,
    // caso o tipo de solicitante já venha selecionado (ex.: retomada de rascunho).
    aplicarTituloSeConsorcio();
});
