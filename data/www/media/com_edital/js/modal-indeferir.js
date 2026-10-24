/**
 * Modal Indeferir - Gerenciamento centralizado do modal de indeferimento
 * @package    Com_Edital
 * @version    1.0.0
 * @author     Railson Rodrigues Leite
 * @copyright  2026 Railson Rodrigues Leite
 */

var IndeferirModal = {
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
        var modalEl = document.getElementById('modal-indeferir');
        
        if (!modalEl) {
            console.warn('Modal indeferir não encontrado no DOM');
            return;
        }

        // Criar instância do Bootstrap Modal
        self.modalInstance = new bootstrap.Modal(modalEl);

        // Listener para opções de motivo padronizado
        var motivos = modalEl.querySelectorAll('.motivo-opcao');
        motivos.forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                // Remover seleção anterior
                motivos.forEach(function(m) {
                    m.classList.remove('selected');
                });
                
                // Adicionar seleção
                opcao.classList.add('selected');
                
                // Marcar o radio button
                var radio = opcao.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
                
                // Preencher textarea com o texto do motivo
                var texto = opcao.getAttribute('data-valor');
                var textarea = document.getElementById('indeferir-justificativa');
                if (textarea && texto) {
                    textarea.value = texto;
                    textarea.classList.remove('is-invalid');
                }
            });
        });

        // Listener para botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-indeferir');
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
     * @param {number} adesaoId - ID da adesão a ser indeferida
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

        // Abrir o modal
        if (self.modalInstance) {
            self.modalInstance.show();
        } else {
            var modalEl = document.getElementById('modal-indeferir');
            if (modalEl) {
                self.modalInstance = new bootstrap.Modal(modalEl);
                self.modalInstance.show();
                self.setupEventListeners();
            }
        }
    },

    /**
     * Confirma o indeferimento e envia para o servidor
     */
    confirmar: function() {
        var self = this;
        var textarea = document.getElementById('indeferir-justificativa');
        var btnConfirmar = document.getElementById('btn-confirmar-indeferir');
        var motivo = textarea ? textarea.value.trim() : '';

        // Validar justificativa
        if (!motivo) {
            if (textarea) {
                textarea.classList.add('is-invalid');
            }
            return;
        }

        textarea.classList.remove('is-invalid');

        // Desabilitar botão durante requisição
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processando...';
        }

        // Preparar dados
        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('adesao_id', self.adesaoId);
        formData.append('motivo', motivo);

        // Enviar requisição AJAX
        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaogestao.indeferirAdesao&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                // Sucesso - fechar modal e atualizar UI
                self.fechar();
                self.onSucesso(data.message || 'Solicitação indeferida com sucesso.', data.messageType || 'error');
            } else {
                // Erro
                alert(data.message || 'Erro ao indeferir solicitação.');
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="far fa-times-circle"></i> Confirmar Indeferimento';
                }
            }
        })
        .catch(function(error) {
            console.error('Erro ao indeferir:', error);
            alert('Erro de comunicação com o servidor. Por favor, tente novamente.');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="far fa-times-circle"></i> Confirmar Indeferimento';
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
        
        // Limpar variáveis
        self.adesaoId = null;
        // Não limpar contexto para permitir manter estado
        
        // Limpar textarea
        var textarea = document.getElementById('indeferir-justificativa');
        if (textarea) {
            textarea.value = '';
            textarea.classList.remove('is-invalid');
        }

        // Limpar seleção de motivos
        var motivos = document.querySelectorAll('.motivo-opcao');
        motivos.forEach(function(opcao) {
            opcao.classList.remove('selected');
            var radio = opcao.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = false;
            }
        });

        // Resetar botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-indeferir');
        if (btnConfirmar) {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="far fa-times-circle"></i> Confirmar Indeferimento';
        }
    },

    /**
     * Callback executado após sucesso do indeferimento
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
            // Recarregar lista de adesões
            if (typeof Adesaogestao !== 'undefined' && Adesaogestao.buscarAdesao) {
                Adesaogestao.buscarAdesao();
            } else {
                // Fallback: recarregar página
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            }
        } else if (self.contexto === 'detalhes') {
            // Atualizar status na página de detalhes
            var badge = document.querySelector('.ar-badge');
            if (badge) {
                badge.className = 'ar-badge ar-badge-indeferido';
                badge.innerHTML = '<i class="far fa-times-circle"></i> Indeferido';
            }

            // Remover botões de ação
            var bottomBar = document.querySelector('.ar-bottom-bar .ar-bottom-right');
            if (bottomBar) {
                bottomBar.innerHTML = '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Solicitação indeferida</p>';
            }

            // Scroll suave para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
};

// Auto-inicializar quando o script for carregado
IndeferirModal.init();
