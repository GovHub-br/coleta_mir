/**
 * Modal Modalidade - Gerenciamento do modal de definição de modalidade de gestão
 * @package    Com_Edital
 * @version    1.0.0
 * @author     Railson Rodrigues Leite
 * @copyright  2026 Railson Rodrigues Leite
 */

var ModalidadeModal = {
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
        var modalEl = document.getElementById('modal-modalidade');

        if (!modalEl) {
            console.warn('Modal modalidade não encontrado no DOM');
            return;
        }

        // Criar instância do Bootstrap Modal
        self.modalInstance = new bootstrap.Modal(modalEl);

        // Listener para opções de modalidade
        var opcoes = modalEl.querySelectorAll('.modalidade-opcao');
        opcoes.forEach(function(opcao) {
            opcao.addEventListener('click', function() {
                opcoes.forEach(function(o) { o.classList.remove('selected'); });

                opcao.classList.add('selected');
                self.modalidadeSelecionada = opcao.getAttribute('data-valor');

                var label = document.getElementById('modalidade-label');
                var header = document.getElementById('modalidade-header');
                if (label) label.textContent = opcao.textContent.trim();
                if (header) header.classList.add('has-value');

                var wrapper = document.getElementById('modalidade-wrapper');
                var feedback = document.getElementById('modalidade-feedback');
                if (wrapper) wrapper.classList.remove('is-invalid');
                if (feedback) feedback.style.display = 'none';
            });
        });

        // Listener para botão confirmar
        var btnConfirmar = document.getElementById('btn-confirmar-modalidade');
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', function() {
                self.confirmar();
            });
        }

        // Limpar estado ao fechar
        modalEl.addEventListener('hidden.bs.modal', function() {
            self.limparEstado();
        });
    },

    /**
     * Abre o modal para uma adesão específica
     * @param {number} adesaoId
     * @param {string} contexto - 'lista' ou 'detalhes'
     * @param {string} modalidadeAtual - valor pré-selecionado (opcional)
     */
    abrir: function(adesaoId, contexto, modalidadeAtual) {
        var self = this;

        if (!adesaoId) {
            console.error('ID da adesão não fornecido');
            return;
        }

        self.adesaoId = adesaoId;
        self.contexto = contexto || 'lista';

        // Pré-selecionar modalidade atual, se fornecida
        if (modalidadeAtual) {
            var modalEl = document.getElementById('modal-modalidade');
            if (modalEl) {
                var opcoes = modalEl.querySelectorAll('.modalidade-opcao');
                opcoes.forEach(function(opcao) {
                    if (opcao.getAttribute('data-valor') === modalidadeAtual) {
                        opcao.classList.add('selected');
                        self.modalidadeSelecionada = modalidadeAtual;
                        var label = document.getElementById('modalidade-label');
                        var header = document.getElementById('modalidade-header');
                        if (label) label.textContent = opcao.textContent.trim();
                        if (header) header.classList.add('has-value');
                    }
                });
            }
        }

        if (self.modalInstance) {
            self.modalInstance.show();
        } else {
            var modalEl = document.getElementById('modal-modalidade');
            if (modalEl) {
                self.modalInstance = new bootstrap.Modal(modalEl);
                self.modalInstance.show();
                self.setupEventListeners();
            }
        }
    },

    /**
     * Confirma a definição de modalidade e envia para o servidor
     */
    confirmar: function() {
        var self = this;
        var btnConfirmar = document.getElementById('btn-confirmar-modalidade');

        if (!self.modalidadeSelecionada) {
            var wrapper = document.getElementById('modalidade-wrapper');
            var feedback = document.getElementById('modalidade-feedback');
            if (wrapper) wrapper.classList.add('is-invalid');
            if (feedback) feedback.style.display = 'block';
            return;
        }

        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Salvando...';
        }

        var formData = new FormData();
        formData.append(self.token, '1');
        formData.append('adesao_id', self.adesaoId);
        formData.append('modalidade', self.modalidadeSelecionada);

        fetch(self.baseUrl + 'index.php?option=com_edital&task=adesaogestao.definirModalidade&format=json', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                self.fechar();
                self.onSucesso(data.message || 'Modalidade definida com sucesso.', data.messageType || 'info');
            } else {
                alert(data.message || 'Erro ao definir modalidade.');
                if (btnConfirmar) {
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-award"></i> Salvar modalidade';
                }
            }
        })
        .catch(function(error) {
            console.error('Erro ao definir modalidade:', error);
            alert('Erro de comunicação com o servidor. Por favor, tente novamente.');
            if (btnConfirmar) {
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = '<i class="fas fa-award"></i> Salvar modalidade';
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

        var opcoes = document.querySelectorAll('#modal-modalidade .modalidade-opcao');
        opcoes.forEach(function(opcao) { opcao.classList.remove('selected'); });

        var label = document.getElementById('modalidade-label');
        var header = document.getElementById('modalidade-header');
        if (label) label.textContent = 'Selecione a modalidade';
        if (header) header.classList.remove('has-value');

        var wrapper = document.getElementById('modalidade-wrapper');
        var feedback = document.getElementById('modalidade-feedback');
        if (wrapper) wrapper.classList.remove('is-invalid');
        if (feedback) feedback.style.display = 'none';

        var btnConfirmar = document.getElementById('btn-confirmar-modalidade');
        if (btnConfirmar) {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="fas fa-award"></i> Salvar modalidade';
        }
    },

    /**
     * Callback executado após sucesso
     * @param {string} message
     */
    onSucesso: function(message, messageType) {
        var self = this;

        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            var messages = {};
            messages[messageType || 'success'] = [message];
            Joomla.renderMessages(messages);
        } else {
            alert(message);
        }

        if (self.contexto === 'lista') {
            if (typeof Adesaogestao !== 'undefined' && Adesaogestao.buscarAdesao) {
                Adesaogestao.buscarAdesao();
            } else {
                setTimeout(function() { window.location.reload(); }, 1500);
            }
        }
    }
};

// Auto-inicializar quando o script for carregado
ModalidadeModal.init();

