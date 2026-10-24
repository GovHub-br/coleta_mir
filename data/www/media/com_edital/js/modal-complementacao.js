/**
 * Modal Complementação - Gerenciamento centralizado do modal de solicitação de complementação
 * @package    Com_Edital
 * @version    1.0.0
 * @author     Railson Rodrigues Leite
 * @copyright  2026 Railson Rodrigues Leite
 */

var ComplementacaoModal = {
    // Estado interno
    adesaoId: null,
    contexto: null, // 'lista' ou 'detalhes'
    token: '',
    baseUrl: '',
    modalInstance: null,

    /**
     * Inicializa o modal e seus event listeners
     */
    init: function() {
        var self = this;

        // Obter token e baseUrl do Joomla
        var tokenElements = document.querySelectorAll('input[name][value="1"]');
        if (tokenElements.length > 0) {
            self.token = tokenElements[0].getAttribute('name');
        }
        self.baseUrl = document.querySelector('base')?.href || window.location.origin + '/';

        // Aguardar DOM estar pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                self.setupEventListeners();
            });
        } else {
            self.setupEventListeners();
        }
    },

    /**
     * Configura os event listeners do modal
     */
    setupEventListeners: function() {
        var self = this;
        var modalEl = document.getElementById('modal-complementacao');

        if (!modalEl) {
            console.warn('Modal complementação não encontrado no DOM');
            return;
        }

        // Criar instância do Bootstrap Modal
        self.modalInstance = new bootstrap.Modal(modalEl);

        // Listener para mensagens padronizadas
        var opcoes = modalEl.querySelectorAll('.mensagem-opcao');
        opcoes.forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                // Remover seleção anterior
                opcoes.forEach(function(o) {
                    o.classList.remove('selected');
                });

                // Marcar opção clicada
                opcao.classList.add('selected');

                // Preencher textarea com o texto da mensagem
                var texto = opcao.getAttribute('data-valor');
                var textarea = document.getElementById('complementacao-mensagem');
                if (textarea && texto) {
                    textarea.value = texto;
                    textarea.classList.remove('is-invalid');
                }
            });
        });

        // Listener para botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-complementacao');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', function() {
                self.confirmar();
            });
        }

        // Limpar estado ao fechar o modal
        modalEl.addEventListener('hidden.bs.modal', function() {
            self.limparEstado();
        });
    },

    /**
     * Abre o modal para uma adesão específica
     * @param {number} adesaoId - ID da adesão
     * @param {string} contexto - 'lista' ou 'detalhes'
     */
    abrir: function(adesaoId, contexto) {
        var self = this;

        if (!adesaoId) {
            console.error('ID da adesão não fornecido');
            return;
        }

        self.adesaoId = adesaoId;
        self.contexto = contexto || 'lista';

        if (self.modalInstance) {
            self.modalInstance.show();
        } else {
            var modalEl = document.getElementById('modal-complementacao');
            if (modalEl) {
                self.modalInstance = new bootstrap.Modal(modalEl);
                self.modalInstance.show();
                self.setupEventListeners();
            }
        }
    },

    /**
     * Confirma a solicitação e envia para o servidor
     */
    confirmar: function() {
        var self = this;
        var textarea = document.getElementById('complementacao-mensagem');
        var btnConfirmar = document.getElementById('btn-confirmar-complementacao');
        var mensagem = textarea ? textarea.value.trim() : '';

        // Validar mensagem
        if (!mensagem) {
            if (textarea) {
                textarea.classList.add('is-invalid');
            }
            return;
        }

        textarea.classList.remove('is-invalid');

        // Desabilitar botão durante requisição
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...';
        }

        // Preparar dados
        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('adesao_id', self.adesaoId);
        formData.append('mensagem', mensagem);

        // Enviar requisição AJAX
        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaogestao.solicitarComplementacao&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                self.fechar();
                self.onSucesso(data.message || 'Complementação solicitada com sucesso.', data.messageType || 'info');
            } else {
                alert(data.message || 'Erro ao solicitar complementação.');
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="far fa-paper-plane"></i> Enviar solicitação';
                }
            }
        })
        .catch(function(error) {
            console.error('Erro ao solicitar complementação:', error);
            alert('Erro de comunicação com o servidor. Por favor, tente novamente.');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="far fa-paper-plane"></i> Enviar solicitação';
            }
        });
    },

    /**
     * Fecha o modal
     */
    fechar: function() {
        var self = this;
        if (self.modalInstance) {
            self.modalInstance.hide();
        }
    },

    /**
     * Limpa o estado do modal
     */
    limparEstado: function() {
        var self = this;

        self.adesaoId = null;

        // Limpar textarea
        var textarea = document.getElementById('complementacao-mensagem');
        if (textarea) {
            textarea.value = '';
            textarea.classList.remove('is-invalid');
        }

        // Remover seleção de mensagens
        var opcoes = document.querySelectorAll('#modal-complementacao .mensagem-opcao');
        opcoes.forEach(function(opcao) {
            opcao.classList.remove('selected');
        });

        // Resetar botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-complementacao');
        if (btnConfirmar) {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="far fa-paper-plane"></i> Enviar solicitação';
        }
    },

    /**
     * Callback executado após sucesso da solicitação
     * @param {string} message - Mensagem de sucesso
     */
    onSucesso: function(message, messageType) {
        var self = this;

        // Mostrar mensagem de sucesso
        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            var messages = {};
            messages[messageType || 'success'] = [message];
            Joomla.renderMessages(messages);
        } else {
            alert(message);
        }

        // Atualizar UI dependendo do contexto
        if (self.contexto === 'lista') {
            if (typeof Adesaogestao !== 'undefined' && Adesaogestao.buscarAdesao) {
                Adesaogestao.buscarAdesao();
            } else {
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            }
        } else if (self.contexto === 'detalhes') {
            // Atualizar status na página de detalhes
            var badge = document.querySelector('.ar-badge');
            if (badge) {
                badge.className = 'ar-badge ar-badge-complementacao';
                badge.innerHTML = '<i class="far fa-comment-alt"></i> Complementação';
            }

            // Remover botões de ação
            var bottomBar = document.querySelector('.ar-bottom-bar .ar-bottom-right');
            if (bottomBar) {
                bottomBar.innerHTML = '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Complementação solicitada</p>';
            }

            // Scroll suave para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
};

// Auto-inicializar quando o script for carregado
ComplementacaoModal.init();
