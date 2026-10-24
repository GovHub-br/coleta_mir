/**
 * Minha Adesão - JavaScript
 */

var MinhaAdesao = {
    init: function () {
        $("#btn-resolver-documento").click(function (e) {
            e.preventDefault();
            MinhaAdesao.resolverDocumento();
        });

        $("#btn-entrar-recurso").click(function (e) {
            e.preventDefault();
            MinhaAdesao.entrarRecurso();
        });
    },
    resolverDocumento: function () {
        jQuery('#documentos-tab').trigger('click');
        jQuery('html, body').animate({ scrollTop: jQuery('#documentos .doc-item.complementacao:first').offset().top - 100 }, 500);
    },
    entrarRecurso: function () {
        debugger;
        jQuery('#recurso-tab').trigger('click');
        jQuery('html, body').animate({ scrollTop: jQuery('#recurso').offset().top - 100 }, 500);
    }
};


document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tabs if needed
    const triggerTabList = document.querySelectorAll('.adesao-tabs button[data-bs-toggle="tab"]');

    if (triggerTabList.length > 0 && typeof bootstrap !== 'undefined') {
        triggerTabList.forEach(function (triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);

            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Abre a aba indicada pelo hash da URL
        if (window.location.hash) {
            var hashTab = document.querySelector('.adesao-tabs button[data-bs-target="' + window.location.hash + '"]');
            if (hashTab) hashTab.click();
        }
    }

    // Handle select change for adesoes
    const selectAdesoes = document.getElementById('adesoes_realizadas');
    if (selectAdesoes) {
        selectAdesoes.addEventListener('change', function () {
            const adesaoId = this.value;
            if (adesaoId) {
                // Reload page with selected adesao
                const url = new URL(window.location.href);
                url.searchParams.set('id', adesaoId);
                window.location.href = url.toString();
            }
        });
    }

    // ── Upload de documento via modal ────────────────────────────────────────
    initUploadResposta();
});

function initUploadResposta() {
    var modalEl = document.getElementById('modalEnviarDoc');
    if (!modalEl) return;

    var respostaIdAtual = null;
    var reloadOnClose = false;

    // Recarrega a página quando o modal fechar (se upload foi bem-sucedido)
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (reloadOnClose) {
            window.location.hash = 'documentos';
            window.location.reload();
        }
    });

    // Quando o modal abre, captura os dados do botão que o disparou
    modalEl.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;

        respostaIdAtual = btn.getAttribute('data-resposta-id');
        var docTitle = btn.getAttribute('data-doc-title') || '';

        // Preenche o título do documento no modal
        var titleEl = document.getElementById('modal-doc-title');
        if (titleEl) titleEl.textContent = docTitle;

        // Limpa o input e alerta anteriores
        var fileInput = document.getElementById('input-doc-arquivo');
        if (fileInput) fileInput.value = '';
        reloadOnClose = false;
        setModalAlert(null);

        // Restaura botões ao estado inicial
        var btnEnviar = document.getElementById('btn-confirmar-envio');
        var btnCancelar = document.getElementById('btn-cancelar-envio');
        var btnFechar = document.getElementById('btn-fechar-envio');
        if (btnEnviar) {
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i class="fas fa-upload"></i> Enviar';
            btnEnviar.classList.remove('d-none');
        }
        if (btnCancelar) btnCancelar.classList.remove('d-none');
        if (btnFechar) btnFechar.classList.add('d-none');
    });

    // Clique no botão "Enviar" dentro do modal
    var btnConfirmar = document.getElementById('btn-confirmar-envio');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function () {
            var fileInput = document.getElementById('input-doc-arquivo');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                setModalAlert('danger', 'Selecione um arquivo antes de enviar.');
                return;
            }

            if (!respostaIdAtual) {
                setModalAlert('danger', 'Erro interno: documento não identificado.');
                return;
            }

            // Obtém o token CSRF do Joomla
            var csrfToken = (typeof Joomla !== 'undefined' && Joomla.getOptions('csrf.token'))
                ? Joomla.getOptions('csrf.token')
                : null;

            var formData = new FormData();
            formData.append('resposta_id', respostaIdAtual);
            formData.append('arquivo', fileInput.files[0]);
            if (csrfToken) {
                formData.append(csrfToken, '1');
            }

            // Estado de carregamento
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enviando...';
            setModalAlert(null);

            fetch('index.php?option=com_edital&task=minhaadesao.uploadResposta&format=json', {
                method: 'POST',
                body: formData,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Erro na comunicação com o servidor (HTTP ' + response.status + ').');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        setModalAlert('success', data.message || 'Documento enviado com sucesso!');
                        btnConfirmar.classList.add('d-none');
                        var btnCancelarEl = document.getElementById('btn-cancelar-envio');
                        if (btnCancelarEl) btnCancelarEl.classList.add('d-none');
                        var btnFecharEl = document.getElementById('btn-fechar-envio');
                        if (btnFecharEl) btnFecharEl.classList.remove('d-none');
                        reloadOnClose = true;
                    } else {
                        setModalAlert('danger', data.message || 'Erro ao enviar documento.');
                        btnConfirmar.disabled = false;
                        btnConfirmar.innerHTML = '<i class="fas fa-upload"></i> Enviar';
                    }
                })
                .catch(function (err) {
                    setModalAlert('danger', err.message || 'Ocorreu um erro inesperado.');
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '<i class="fas fa-upload"></i> Enviar';
                });
        });
    }
}

/**
 * Exibe ou remove o alerta dentro do modal de upload.
 *
 * @param {string|null} type  'success', 'danger', etc. — null para esconder.
 * @param {string}      msg   Texto da mensagem.
 */
function setModalAlert(type, msg) {
    var alertEl = document.getElementById('modal-upload-alert');
    if (!alertEl) return;

    if (!type) {
        alertEl.className = 'alert d-none';
        alertEl.textContent = '';
        return;
    }

    alertEl.className = 'alert alert-' + type;
    alertEl.textContent = msg;
}

jQuery(document).ready(function ($) {

    MinhaAdesao.init();

    //recurso
    $("#recurso-form").submit(function (e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        
        // Disable button to prevent multiple submissions
        submitBtn.prop('disabled', true);
        var originalText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enviando...');
        
        var formData = new FormData(this);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                response = typeof response === 'string' ? JSON.parse(response) : response;
                if (response.success) {
                    Joomla.renderMessages({ 'success': [response.message || 'Recurso enviado com sucesso!'] });
                    //scroll to top
                    $('html, body').animate({ scrollTop: 0 }, 'fast');
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    Joomla.renderMessages({ 'error': [response.message || 'Erro ao enviar recurso.'] });
                    // Re-enable button on error
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                }
            },
            error: function () {
                alert('Ocorreu um erro ao enviar o recurso. Tente novamente.');
                // Re-enable button on error
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });


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
            $fileName.html('<i class="fas fa-file-alt me-2"></i><strong>' + fileName + '</strong> (' + fileSize + ' MB)').show();
            $dropArea.addClass('file-selected').removeClass('file-error');
            $input.removeClass('is-invalid');

            // Atualizar contagem de documentos
            if (typeof AdesaoForm !== 'undefined' && AdesaoForm.atualizarContagemDocumentos) {
                setTimeout(() => AdesaoForm.atualizarContagemDocumentos(), 50);
            }
        }

        // Click to upload
        $dropArea.on('click', function () {
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
                $fileInput.removeClass('is-invalid');

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

    // Observar mudanças na UI do upload (ex.: remoção sem disparar "change")
    // Isso evita contadores "travados" quando o arquivo é limpo via UI/JS.
    if (window.MutationObserver) {

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
});