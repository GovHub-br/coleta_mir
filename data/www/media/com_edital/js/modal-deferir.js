/**
 * Modal Deferir - Gerenciamento centralizado do modal de deferimento
 * @package    Com_Edital
 * @version    1.0.0
 * @author     Railson Rodrigues Leite
 * @copyright  2026 Railson Rodrigues Leite
 */

var DeferirModal = {
    // Estado interno
    adesaoId: null,
    contexto: null, // 'lista' ou 'detalhes'
    token: '',
    baseUrl: '',
    modalInstance: null,
    modalidadeSelecionada: null,

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
        var modalEl = document.getElementById('modal-deferir');

        if (!modalEl) {
            console.warn('Modal deferir não encontrado no DOM');
            return;
        }

        // Criar instância do Bootstrap Modal
        self.modalInstance = new bootstrap.Modal(modalEl);

        // Listener para opções de modalidade
        var opcoes = modalEl.querySelectorAll('.modalidade-opcao');
        opcoes.forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                // Remover seleção anterior
                opcoes.forEach(function(o) {
                    o.classList.remove('selected');
                });

                // Marcar opção clicada
                opcao.classList.add('selected');
                self.modalidadeSelecionada = opcao.getAttribute('data-valor');

                // Atualizar texto do header
                var label = document.getElementById('deferir-modalidade-label');
                var header = document.getElementById('deferir-modalidade-header');
                if (label) label.textContent = opcao.textContent.trim();
                if (header) header.classList.add('has-value');

                // Remover estado de inválido
                var wrapper = document.getElementById('deferir-modalidade-wrapper');
                var feedback = document.getElementById('deferir-modalidade-feedback');
                if (wrapper) wrapper.classList.remove('is-invalid');
                if (feedback) feedback.style.display = 'none';
            });
        });

        // Listener para botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-deferir');
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
     * @param {number} adesaoId - ID da adesão a ser deferida
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
            var modalEl = document.getElementById('modal-deferir');
            if (modalEl) {
                self.modalInstance = new bootstrap.Modal(modalEl);
                self.modalInstance.show();
                self.setupEventListeners();
            }
        }
    },

    /**
     * Confirma o deferimento e envia para o servidor
     */
    confirmar: function() {
        var self = this;
        var btnConfirmar = document.getElementById('btn-confirmar-deferir');

        // Validar modalidade selecionada
        if (!self.modalidadeSelecionada) {
            var wrapper = document.getElementById('deferir-modalidade-wrapper');
            var feedback = document.getElementById('deferir-modalidade-feedback');
            if (wrapper) wrapper.classList.add('is-invalid');
            if (feedback) feedback.style.display = 'block';
            return;
        }

        // Desabilitar botão durante requisição
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processando...';
        }

        var observacoes = document.getElementById('deferir-observacoes');
        var obsValue = observacoes ? observacoes.value.trim() : '';

        // Preparar dados
        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('adesao_id', self.adesaoId);
        formData.append('modalidade', self.modalidadeSelecionada);
        formData.append('observacoes', obsValue);

        // Enviar requisição AJAX
        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaogestao.deferirAdesao&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                self.fechar();
                self.onSucesso(data.message || 'Solicitação deferida com sucesso.', data.messageType || 'success');
            } else {
                alert(data.message || 'Erro ao deferir solicitação.');
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="far fa-check-circle"></i> Confirmar deferimento';
                }
            }
        })
        .catch(function(error) {
            console.error('Erro ao deferir:', error);
            alert('Erro de comunicação com o servidor. Por favor, tente novamente.');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="far fa-check-circle"></i> Confirmar deferimento';
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
        self.modalidadeSelecionada = null;

        // Remover seleção de opções
        var opcoes = document.querySelectorAll('#modal-deferir .modalidade-opcao');
        opcoes.forEach(function(opcao) {
            opcao.classList.remove('selected');
        });

        // Resetar header
        var label = document.getElementById('deferir-modalidade-label');
        var header = document.getElementById('deferir-modalidade-header');
        if (label) label.textContent = 'Selecione a modalidade';
        if (header) header.classList.remove('has-value');

        // Limpar estado de inválido
        var wrapper = document.getElementById('deferir-modalidade-wrapper');
        var feedback = document.getElementById('deferir-modalidade-feedback');
        if (wrapper) wrapper.classList.remove('is-invalid');
        if (feedback) feedback.style.display = 'none';

        // Limpar textarea
        var obs = document.getElementById('deferir-observacoes');
        if (obs) obs.value = '';

        // Resetar botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-deferir');
        if (btnConfirmar) {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="far fa-check-circle"></i> Confirmar deferimento';
        }
    },

    /**
     * Callback executado após sucesso do deferimento
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
                badge.className = 'ar-badge ar-badge-deferido';
                badge.innerHTML = '<i class="far fa-check-circle"></i> Deferido';
            }

            // Remover botões de ação
            var bottomBar = document.querySelector('.ar-bottom-bar .ar-bottom-right');
            if (bottomBar) {
                bottomBar.innerHTML = '<p class="text-muted mb-0"><i class="fas fa-info-circle"></i> Solicitação deferida</p>';
            }

            // Scroll suave para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
};

// Auto-inicializar quando o script for carregado
DeferirModal.init();
