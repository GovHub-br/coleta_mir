var AdesaoRespostasRecurso = {
    _acaoPendente: null,
    _isSubmitting: false,

    init: function () {
        var self = this;

        $('#btn-deferir-recurso, #btn-indeferir-recurso').on('click', function () {
            self._acaoPendente = $(this).val();
        });

        $("#formAvaliacaoRecurso").submit(function (e) {
            e.preventDefault();
            var acao = self._acaoPendente;

            if (acao !== 'deferir' && acao !== 'indeferir') {
                return; // Ação inválida, não faz nada
            }

            self.confirmarAcao(acao);
        });

        $("#btnConfirmarAcaoRecurso").on('click', function () {
            if (self._isSubmitting) {
                return;
            }

            var modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmacaoRecurso'));
            if (modal) modal.hide();
            self.submitRecurso(self._acaoPendente);
        });
    },

    setLoadingState: function (isLoading, acao) {
        var $btnConfirmar = $('#btnConfirmarAcaoRecurso');
        var $btnDeferir = $('#btn-deferir-recurso');
        var $btnIndeferir = $('#btn-indeferir-recurso');
        var $btnCancelarModal = $('#modalConfirmacaoRecurso [data-bs-dismiss="modal"]');

        if (isLoading) {
            this._isSubmitting = true;

            if (!$btnConfirmar.data('originalHtml')) {
                $btnConfirmar.data('originalHtml', $btnConfirmar.html());
            }
            if (!$btnDeferir.data('originalHtml')) {
                $btnDeferir.data('originalHtml', $btnDeferir.html());
            }
            if (!$btnIndeferir.data('originalHtml')) {
                $btnIndeferir.data('originalHtml', $btnIndeferir.html());
            }

            $btnConfirmar
                .prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processando...');

            $btnCancelarModal.prop('disabled', true);

            if (acao === 'deferir') {
                $btnDeferir
                    .prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deferindo...');
            } else {
                $btnIndeferir
                    .prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Indeferindo...');
            }

            $btnDeferir.add($btnIndeferir).not(':disabled').prop('disabled', true);
            return;
        }

        this._isSubmitting = false;

        if ($btnConfirmar.data('originalHtml')) {
            $btnConfirmar.html($btnConfirmar.data('originalHtml'));
        }
        if ($btnDeferir.data('originalHtml')) {
            $btnDeferir.html($btnDeferir.data('originalHtml'));
        }
        if ($btnIndeferir.data('originalHtml')) {
            $btnIndeferir.html($btnIndeferir.data('originalHtml'));
        }

        $btnConfirmar.prop('disabled', false);
        $btnDeferir.prop('disabled', false);
        $btnIndeferir.prop('disabled', false);
        $btnCancelarModal.prop('disabled', false);
    },

    confirmarAcao: function (acao) {
        var self = this;
        self._acaoPendente = acao;

        var titulo = acao === 'deferir' ? 'Deferir recurso' : 'Indeferir recurso';
        var mensagem = acao === 'deferir'
            ? 'Esta ação irá <strong>deferir</strong> a adesão. Deseja prosseguir?'
            : 'Esta ação irá <strong>indeferir</strong> a adesão. Deseja prosseguir?';
        var btnCls = acao === 'deferir' ? 'btn-success' : 'btn-danger';
        var btnLabel = acao === 'deferir' ? 'Sim, deferir' : 'Sim, indeferir';

        $('#modalConfirmacaoRecurso .modal-title').text(titulo);
        $('#modalConfirmacaoRecurso .modal-body p').html(mensagem);
        $('#btnConfirmarAcaoRecurso')
            .removeClass('btn-success btn-danger')
            .addClass(btnCls)
            .text(btnLabel);

        var modal = new bootstrap.Modal(document.getElementById('modalConfirmacaoRecurso'));
        modal.show();
    },

    submitRecurso: function (acao) {
        var self = this;

        if (self._isSubmitting) {
            return;
        }

        var form = $("#formAvaliacaoRecurso");

        console.debug("Form data:", form.serialize(), "Ação:", acao);

        //criar formadata que pode conter arquivos
        var formData = new FormData(form[0]);
        formData.append('acao', acao); // Adicionar a ação ao FormData


        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                self.setLoadingState(true, acao);
            },
            success: function (response) {
                response = typeof response === 'string' ? JSON.parse(response) : response; // Garantir que a resposta seja um objeto JSON
                if (response.success) {
                    var messages = {};
                    messages[response.messageType || 'success'] = [response.message || 'Recurso avaliado com sucesso.'];
                    Joomla.renderMessages(messages);
                    setTimeout(function () {
                        location.href = document.getElementById('urlVoltar').value; // Redirecionar após 2 segundos para a URL de volta
                    }, 2000);
                } else {
                    Joomla.renderMessages({ 'error': [response.message || 'Erro ao avaliar recurso.'] });
                }
            },
            error: function () {
                alert('Erro na requisição. Por favor, tente novamente.');
            },
            complete: function () {
                self.setLoadingState(false, acao);
            }
        });
    }
}

$(document).ready(function () {
    AdesaoRespostasRecurso.init();
});
