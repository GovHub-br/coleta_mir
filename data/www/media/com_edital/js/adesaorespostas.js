var AdesaoRespostas = {
    token: '',
    baseUrl: '',

    init: function() {
        this.initTabs();
        this.initEvalButtons();
        this.initReabrirButton();
        this.initModalReabrir();
        this.initIndeferirButton();
        this.initDeferirButton();
        this.initComplementacaoButton();
        this.initTooltips(document);
    },

    initTabs: function() {
        var tabs = document.querySelectorAll('.ar-tab');
        var cards = document.querySelectorAll('.ar-doc-card');

        var filterMap = [
            null,                       // Todos
            'ar-doc-pendente',          // Pendentes
            'ar-doc-aprovado',          // Aprovados
            'ar-doc-rejeitado',         // Rejeitados
            'ar-doc-complementacao',    // Complementação
        ];

        tabs.forEach(function(tab, index) {
            tab.addEventListener('click', function() {
                tabs.forEach(function(t) { t.classList.remove('active'); });
                tab.classList.add('active');

                var filterClass = filterMap[index];

                cards.forEach(function(card) {
                    if (!filterClass || card.classList.contains(filterClass)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    },

    initEvalButtons: function() {
        var self = this;

        document.querySelectorAll('.ar-btn-aprovar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var respostaId = btn.dataset.respostaId;
                if (!respostaId) return;
                self.abrirModalAprovacao(respostaId, btn);
            });
        });

        document.querySelectorAll('.ar-btn-rejeitar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var respostaId = btn.dataset.respostaId;
                if (!respostaId) return;
                self.abrirModalRejeicao(respostaId, btn);
            });
        });

        document.querySelectorAll('.ar-btn-complementar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var respostaId = btn.dataset.respostaId;
                if (!respostaId) return;
                self.abrirModalComplementacao(respostaId, btn);
            });
        });

        this.initModal();
        this.initModalAprovacao();
        this.initModalComplementacao();
    },

    initReabrirButton: function() {
        var self = this;

        document.querySelectorAll('.ar-btn-reabrir').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var respostaId = btn.dataset.respostaId;
                if (!respostaId) {
                    return;
                }

                self.abrirModalReabrir(respostaId, btn);
            });
        });
    },

    initModalReabrir: function() {
        var self = this;
        var modalEl = document.getElementById('ar-modal-reabrir');
        if (!modalEl) return;

        var btnConfirmar = document.getElementById('ar-reabrir-confirmar');

        btnConfirmar.addEventListener('click', function() {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Reabrindo...';
            self.reabrirResposta(self._reabrirRespostaId);
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            self._reabrirRespostaId = null;
            self._reabrirBtn = null;
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="fas fa-undo"></i> Confirmar retorno para pendente';
        });
    },

    abrirModalReabrir: function(respostaId, btn) {
        this._reabrirRespostaId = respostaId;
        this._reabrirBtn = btn;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ar-modal-reabrir')).show();
    },

    initModalAprovacao: function() {
        var self = this;
        var modalEl = document.getElementById('ar-modal-aprovacao');
        if (!modalEl) return;

        var btnConfirmar = document.getElementById('ar-aprovacao-confirmar');

        btnConfirmar.addEventListener('click', function() {
            btnConfirmar.disabled = true;
            self.aprovarResposta(self._aprovacaoRespostaId, self._aprovacaoBtn);
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            self._aprovacaoRespostaId = null;
            self._aprovacaoBtn = null;
            btnConfirmar.disabled = false;
        });
    },

    abrirModalAprovacao: function(respostaId, btn) {
        this._aprovacaoRespostaId = respostaId;
        this._aprovacaoBtn = btn;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ar-modal-aprovacao')).show();
    },

    initModal: function() {
        var self = this;
        var modalEl = document.getElementById('ar-modal-rejeicao');
        if (!modalEl) return;

        var textarea     = document.getElementById('ar-motivo-texto');
        var btnConfirmar = document.getElementById('ar-modal-confirmar');

        // Motivos padronizados — clique preenche textarea e seleciona
        modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(o) { o.classList.remove('selected'); });
                opcao.classList.add('selected');
                textarea.value = opcao.textContent.trim();
                textarea.classList.remove('is-invalid');
            });
        });

        // Confirmar rejeição
        btnConfirmar.addEventListener('click', function() {
            var motivo = textarea.value.trim();
            if (!motivo) {
                textarea.classList.add('is-invalid');
                textarea.focus();
                return;
            }
            textarea.classList.remove('is-invalid');
            self.rejeitarResposta(self._rejeicaoRespostaId, self._rejeicaoBtn, motivo);
        });

        // Limpa estado ao fechar
        modalEl.addEventListener('hidden.bs.modal', function() {
            self._rejeicaoRespostaId = null;
            self._rejeicaoBtn = null;
            textarea.value = '';
            textarea.classList.remove('is-invalid');
            modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(o) { o.classList.remove('selected'); });
            btnConfirmar.disabled = false;
        });
    },

    abrirModalRejeicao: function(respostaId, btn) {        this._rejeicaoRespostaId = respostaId;
        this._rejeicaoBtn = btn;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ar-modal-rejeicao')).show();
    },

    fecharModal: function() {
        var modalEl = document.getElementById('ar-modal-rejeicao');
        if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
    },

    rejeitarResposta: function(respostaId, btn, motivo) {
        var self = this;
        var btnConfirmar = document.getElementById('ar-modal-confirmar');
        btnConfirmar.disabled = true;
        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('resposta_id', respostaId);
        formData.append('motivo', motivo);

        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaorespostas.rejeitarResposta&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                self.fecharModal();
                self.renderToast(data.message || '<strong>Documento rejeitado.</strong> Situação atualizada com sucesso', data.messageType || 'error');
                self.onRejeitado(btn, motivo, data.finalizacao || null);
            } else {
                alert(data.message || 'Erro ao rejeitar.');
                btnConfirmar.disabled = false;
            }
        })
        .catch(function() {
            alert('Erro de comunicação com o servidor.');
            btnConfirmar.disabled = false;
        });
    },

    reabrirResposta: function(respostaId) {
        var self = this;
        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('resposta_id', respostaId);

        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaorespostas.reabrirResposta&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var modalEl = document.getElementById('ar-modal-reabrir');
                if (modalEl) {
                    var modalInst = bootstrap.Modal.getInstance(modalEl);
                    if (modalInst) {
                        modalInst.hide();
                    }
                }
                window.location.reload();
            } else {
                alert(data.message || 'Erro ao voltar resposta para pendente.');
                var btnConfirmar = document.getElementById('ar-reabrir-confirmar');
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-undo"></i> Confirmar retorno para pendente';
                }
            }
        })
        .catch(function() {
            alert('Erro de comunicação com o servidor.');
            var btnConfirmar = document.getElementById('ar-reabrir-confirmar');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="fas fa-undo"></i> Confirmar retorno para pendente';
            }
        });
    },

    onRejeitado: function(btn, motivo, finalizacao) {
        var card = btn.closest('.ar-doc-card');
        if (!card) return;

        card.classList.remove('ar-doc-pendente', 'ar-doc-complementacao');
        card.classList.add('ar-doc-rejeitado');

        var badge = card.querySelector('.ar-doc-badge');
        if (badge) {
            badge.className = 'ar-doc-badge ar-doc-badge-rejeitado';
            badge.textContent = 'Rejeitado';
        }

        var docBody = card.querySelector('.ar-doc-body');
        if (docBody && motivo) {
            var existing = docBody.querySelector('.ar-motivo-rejeicao');
            if (!existing) {
                var div = document.createElement('div');
                div.className = 'ar-motivo-rejeicao';
                div.innerHTML = '<div class="ar-motivo-titulo">Motivo da rejeição:</div>'
                              + '<div class="ar-motivo-texto">' + motivo.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                docBody.appendChild(div);
            }
        }

        var evalAcoes = card.querySelector('.ar-eval-acoes');
        if (evalAcoes) evalAcoes.remove();

        this.atualizarContadores();
        this.atualizarEstadoFinalizacao(finalizacao);

        var tabAtiva = document.querySelector('.ar-tab.active');
        if (tabAtiva && (tabAtiva.textContent.trim().startsWith('Pendentes') || tabAtiva.textContent.trim().startsWith('Complementação'))) {
            card.style.display = 'none';
        }
    },

    initModalComplementacao: function() {
        var self = this;
        var modalEl     = document.getElementById('ar-modal-complementacao');
        if (!modalEl) return;

        var textarea     = document.getElementById('ar-complementacao-texto');
        var btnConfirmar = document.getElementById('ar-complementacao-confirmar');

        modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(o) { o.classList.remove('selected'); });
                opcao.classList.add('selected');
                textarea.value = opcao.textContent.trim();
                textarea.classList.remove('is-invalid');
            });
        });

        btnConfirmar.addEventListener('click', function() {
            var mensagem = textarea.value.trim();
            if (!mensagem) {
                textarea.classList.add('is-invalid');
                textarea.focus();
                return;
            }
            textarea.classList.remove('is-invalid');
            self.enviarComplementacao(self._complementacaoRespostaId, self._complementacaoBtn, mensagem);
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            self._complementacaoRespostaId = null;
            self._complementacaoBtn = null;
            textarea.value = '';
            textarea.classList.remove('is-invalid');
            modalEl.querySelectorAll('.ar-motivo-opcao').forEach(function(o) { o.classList.remove('selected'); });
            btnConfirmar.disabled = false;
        });
    },

    abrirModalComplementacao: function(respostaId, btn) {
        this._complementacaoRespostaId = respostaId;
        this._complementacaoBtn = btn;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('ar-modal-complementacao')).show();
    },

    enviarComplementacao: function(respostaId, btn, mensagem) {
        var self = this;
        var btnConfirmar = document.getElementById('ar-complementacao-confirmar');
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
        }

        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('resposta_id', respostaId);
        formData.append('mensagem', mensagem);

        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaorespostas.complementarResposta&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('ar-modal-complementacao')).hide();
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    self.renderToast(data.message || 'Solicitação de complementação enviada.', data.messageType || 'info');
                }
                self.onComplementado(btn, mensagem, data.finalizacao || null);
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = 'Enviar Solicitação';
            } else {
                alert(data.message || 'Erro ao enviar solicitação.');
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = 'Enviar Solicitação';
            }
        })
        .catch(function() {
            alert('Erro de comunicação com o servidor.');
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = 'Enviar Solicitação';
        });
    },

    onComplementado: function(btn, mensagem, finalizacao) {
        var card = btn.closest('.ar-doc-card');
        if (!card) return;

        card.classList.remove('ar-doc-pendente');
        card.classList.add('ar-doc-complementacao');

        var badge = card.querySelector('.ar-doc-badge');
        if (badge) {
            badge.className = 'ar-doc-badge ar-doc-badge-complementacao';
            badge.textContent = 'Em Complementação';
        }


        $(".ar-proto-row .ar-badge").removeClass('ar-badge-pendente ar-badge-aprovado ar-badge-rejeitado ar-badge-complementacao').addClass('ar-badge-complementacao');
        $(".ar-proto-row .ar-badge").html('<i class="far fa-comment-alt"></i> Em Complementação');

        var docBody = card.querySelector('.ar-doc-body');
        if (docBody && mensagem) {
            var existing = docBody.querySelector('.ar-motivo-rejeicao');
            if (!existing) {
                var div = document.createElement('div');
                div.className = 'ar-motivo-rejeicao ar-motivo-complementacao';
                div.innerHTML = '<div class="ar-motivo-titulo">Solicitação de complementação:</div>'
                              + '<div class="ar-motivo-texto">' + mensagem.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
                docBody.appendChild(div);
            }
        }

        var evalAcoes = card.querySelector('.ar-eval-acoes');
        if (evalAcoes) evalAcoes.remove();

        this.atualizarContadores();
        this.atualizarEstadoFinalizacao(finalizacao);
    },

    aprovarResposta: function(respostaId, btn) {
        var self = this;

        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('resposta_id', respostaId);

        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaorespostas.aprovarResposta&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                var modalEl = document.getElementById('ar-modal-aprovacao');
                if (modalEl) {
                    var modalInst = bootstrap.Modal.getInstance(modalEl);
                    if (modalInst) modalInst.hide();
                }
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    self.renderToast(data.message || 'Documento aprovado.', data.messageType || 'success');
                }
                self.onAprovado(btn, data.finalizacao || null);
            } else {
                alert(data.message || 'Erro ao aprovar.');
                var btnConfirmar = document.getElementById('ar-aprovacao-confirmar');
                if (btnConfirmar) btnConfirmar.disabled = false;
            }
        })
        .catch(function() {
            alert('Erro de comunicação com o servidor.');
            var btnConfirmar = document.getElementById('ar-aprovacao-confirmar');
            if (btnConfirmar) btnConfirmar.disabled = false;
        });
    },

    onAprovado: function(btn, finalizacao) {
        var card = btn.closest('.ar-doc-card');
        if (!card) return;

        // Atualiza classe do card
        card.classList.remove('ar-doc-pendente', 'ar-doc-complementacao');
        card.classList.add('ar-doc-aprovado');

        // Atualiza badge
        var badge = card.querySelector('.ar-doc-badge');
        if (badge) {
            badge.className = 'ar-doc-badge ar-doc-badge-aprovado';
            badge.textContent = 'Aprovado';
        }

        // Remove botões de avaliação
        var evalAcoes = card.querySelector('.ar-eval-acoes');
        if (evalAcoes) evalAcoes.remove();

        // Atualiza contadores nas tabs e stats
        this.atualizarContadores();
        this.atualizarEstadoFinalizacao(finalizacao);

        // Se tab ativa for "Pendentes" ou "Complementação", esconde o card
        var tabAtiva = document.querySelector('.ar-tab.active');
        if (tabAtiva && (tabAtiva.textContent.trim().startsWith('Pendentes') || tabAtiva.textContent.trim().startsWith('Complementação'))) {
            card.style.display = 'none';
        }
    },

    atualizarEstadoFinalizacao: function(finalizacao) {
        if (!finalizacao) return;

        var podeFinalizar = !!finalizacao.podeFinalizar;
        var motivoBloqueio = finalizacao.motivoBloqueio || 'A solicitação só pode ser finalizada quando todos os documentos estiverem com status Aprovado ou Rejeitado.';

        this.configurarBotaoFinalizacao('.ar-btn-deferir', podeFinalizar, motivoBloqueio);
        this.configurarBotaoFinalizacao('.ar-btn-indeferir', podeFinalizar, motivoBloqueio);
    },

    configurarBotaoFinalizacao: function(selector, podeFinalizar, motivoBloqueio) {
        var btn = document.querySelector(selector);
        if (!btn) return;

        var wrapper = btn.closest('.ar-action-wrapper');
        if (!wrapper) return;

        if (podeFinalizar) {
            btn.disabled = false;
            btn.removeAttribute('aria-disabled');
            wrapper.removeAttribute('tabindex');
            wrapper.removeAttribute('data-bs-toggle');
            wrapper.removeAttribute('title');
            wrapper.removeAttribute('data-bs-title');
            wrapper.removeAttribute('data-bs-original-title');

            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                var tooltip = bootstrap.Tooltip.getInstance(wrapper);
                if (tooltip) tooltip.dispose();
            }

            return;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            var currentTooltip = bootstrap.Tooltip.getInstance(wrapper);
            if (currentTooltip) currentTooltip.dispose();
        }

        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
        wrapper.setAttribute('tabindex', '0');
        wrapper.setAttribute('data-bs-toggle', 'tooltip');
        wrapper.setAttribute('title', motivoBloqueio);
        wrapper.setAttribute('data-bs-title', motivoBloqueio);

        this.initTooltips(wrapper);
    },

    renderToast: function(message, type) {
        var container = document.getElementById('system-message-container');
        if (container) {
            container.querySelectorAll('joomla-alert, .alert').forEach(function(alert) {
                alert.remove();
            });
        }

        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            var messages = {};
            messages[type || 'message'] = [message];
            Joomla.renderMessages(messages, container || undefined, true);
        }
    },

    initTooltips: function(context) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;

        var root = context || document;
        var triggers = [];

        if (root.nodeType === 1 && root.matches('[data-bs-toggle="tooltip"]')) {
            triggers.push(root);
        }

        if (root.querySelectorAll) {
            triggers = triggers.concat(Array.prototype.slice.call(root.querySelectorAll('[data-bs-toggle="tooltip"]')));
        }

        triggers.forEach(function(trigger) {
            bootstrap.Tooltip.getOrCreateInstance(trigger, { html: true });
        });
    },

    atualizarContadores: function() {
        var cards = document.querySelectorAll('.ar-doc-card');
        var total = 0, aprovados = 0, pendentes = 0, rejeitados = 0, complementacoes = 0;

        cards.forEach(function(card) {
            total++;
            if (card.classList.contains('ar-doc-aprovado')) aprovados++;
            else if (card.classList.contains('ar-doc-rejeitado')) rejeitados++;
            else if (card.classList.contains('ar-doc-complementacao')) complementacoes++;
            else pendentes++;
        });

        var tabs = document.querySelectorAll('.ar-tab');
        var labels = ['Todos', 'Pendentes', 'Aprovados', 'Rejeitados', 'Complementação'];
        var counts = [total, pendentes, aprovados, rejeitados, complementacoes];
        tabs.forEach(function(tab, i) {
            tab.textContent = labels[i] + ' (' + counts[i] + ')';
        });

        var statNums = document.querySelectorAll('.ar-stat-num');
        if (statNums[0]) statNums[0].textContent = total;
        if (statNums[1]) statNums[1].textContent = aprovados;
        if (statNums[2]) statNums[2].textContent = pendentes;
        if (statNums[3]) statNums[3].textContent = complementacoes;
        if (statNums[4]) statNums[4].textContent = rejeitados;
    },

    initIndeferirButton: function() {
        var self = this;
        
        // Listener para botão indeferir na bottom bar
        var btnIndeferir = document.querySelector('.ar-btn-indeferir');
        if (btnIndeferir) {
            btnIndeferir.addEventListener('click', function(e) {
                e.preventDefault();
                
                var adesaoId = jQuery('#ar-adesao-id').val();
                
                if (adesaoId && typeof IndeferirModal !== 'undefined') {
                    IndeferirModal.abrir(adesaoId, 'detalhes');
                } else {
                    console.error('ID da adesão não encontrado ou modal não carregado');
                }
            });
        }
    },

    initDeferirButton: function() {
        var self = this;

        // Listener para botão deferir na bottom bar
        var btnDeferir = document.querySelector('.ar-btn-deferir');
        if (btnDeferir) {
            btnDeferir.addEventListener('click', function(e) {
                e.preventDefault();

                var adesaoId = jQuery('#ar-adesao-id').val();

                if (adesaoId && typeof DeferirModal !== 'undefined') {
                    DeferirModal.abrir(adesaoId, 'detalhes');
                } else {
                    console.error('ID da adesão não encontrado ou modal não carregado');
                }
            });
        }
    },

    initComplementacaoButton: function() {
        var self = this;

        // Listener para botão complementação na bottom bar
        var btnCompl = document.querySelector('.ar-btn-complementacao');
        if (btnCompl) {
            btnCompl.addEventListener('click', function(e) {
                e.preventDefault();

                var adesaoId = jQuery('#ar-adesao-id').val();

                if (adesaoId && typeof ComplementacaoModal !== 'undefined') {
                    ComplementacaoModal.abrir(adesaoId, 'detalhes');
                } else {
                    console.error('ID da adesão não encontrado ou modal não carregado');
                }
            });
        }
    }
}
