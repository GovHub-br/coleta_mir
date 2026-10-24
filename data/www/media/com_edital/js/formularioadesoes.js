
var Formularioadesoes = {
    baseUrl: '',
    baseUrlRespostas: '',
    exportPdfUrl: '',
    _debounceTimer: null,
    _currentPage: 1,
    _limit: 10,
    _total: 0,
    init: function () {
        var self = this;
        jQuery(document).ready(function () {
            jQuery('#formBuscaAdesao').submit(function (e) {
                e.preventDefault();
                self._currentPage = 1;
                self.buscarAdesao();
            });

            jQuery('#inputBuscaAdesao').on('input', function () {
                clearTimeout(self._debounceTimer);
                self._debounceTimer = setTimeout(function () {
                    self._currentPage = 1;
                    self.buscarAdesao();
                }, 400);
            });

            jQuery('#selectSituacao, #selectEnte, #selectOrdem, #selectLimit').change(function () {
                clearTimeout(self._debounceTimer);
                var label = jQuery(this).find('option:selected').text();
                jQuery(this).siblings('.control-dropdown').find('span').text(label);
                self.sincronizarCardsSituacao();
                self._currentPage = 1;
                self.buscarAdesao(this.id === 'selectLimit');
            });

            jQuery('.edital-dashboard .stat-card[data-situacao]').on('click', function () {
                jQuery('#selectSituacao').val(jQuery(this).attr('data-situacao')).trigger('change');
            });
            self.sincronizarCardsSituacao();

            jQuery('#adesaoList').on('click', '.adesao-item-row', function () {
                var actions = jQuery(this).siblings('.adesao-actions');
                var isOpen = actions.css('display') === 'flex';
                
                // Fechar todos os outros itens
                jQuery('#adesaoList').find('.adesao-actions').css('display', 'none');
                jQuery('#adesaoList').find('.adesao-arrow i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                
                // Abrir/fechar o item clicado
                if (!isOpen) {
                    actions.css('display', 'flex');
                    jQuery(this).find('.adesao-arrow i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                }
            });


            // Listener para botão definir modalidade
            jQuery('#adesaoList').on('click', '.btn-modalidade', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var adesaoId = jQuery(this).data('adesao-id');
                var modalidadeAtual = jQuery(this).data('modalidade-atual') || '';
                if (adesaoId && typeof ModalidadeModal !== 'undefined') {
                    ModalidadeModal.abrir(adesaoId, 'lista', modalidadeAtual);
                }
            });

            // Listener para botão deferir
            jQuery('#adesaoList').on('click', '.btn-deferir', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var adesaoId = jQuery(this).data('adesao-id');
                if (adesaoId && typeof DeferirModal !== 'undefined') {
                    DeferirModal.abrir(adesaoId, 'lista');
                }
            });

            // Listener para botão indeferir
            jQuery('#adesaoList').on('click', '.btn-indeferir', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var adesaoId = jQuery(this).data('adesao-id');
                if (adesaoId && typeof IndeferirModal !== 'undefined') {
                    IndeferirModal.abrir(adesaoId, 'lista');
                }
            });

            // Listener para botão analisar recurso
            jQuery('#adesaoList').on('click', '.btn-avaliar-recurso', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var adesaoId = jQuery(this).data('adesao-id');
                if (adesaoId) {
                    window.location.href = self.baseUrlRespostas + (self.baseUrlRespostas.indexOf('?') === -1 ? '?' : '&') + 'id=' + adesaoId+'&layout=recurso';
                }
            });

            // Paginação
            jQuery(document).on('click', '.paginacao-btn', function () {
                var page = parseInt(jQuery(this).data('page'), 10);
                if (!isNaN(page)) {
                    self._currentPage = page;
                    self.buscarAdesao();
                    jQuery('html, body').animate({ scrollTop: jQuery('#adesaoList').offset().top - 20 }, 200);
                }
            });

            jQuery('#btnExportarPdf').on('click', function () {
                self.exportarPdf();
            });

            self.buscarAdesao();
        });
    },
    sincronizarCardsSituacao: function () {
        var situacao = jQuery('#selectSituacao').val();
        jQuery('.edital-dashboard .stat-card[data-situacao]').each(function () {
            var ativo = jQuery(this).attr('data-situacao') === situacao;
            jQuery(this).attr('aria-pressed', ativo ? 'true' : 'false');
        });
    },
    initTooltips: function(context) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
            return;
        }

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
    escapeHtmlAttr: function(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    },
    buildActionButton: function(className, adesaoId, iconClass, label, disabled, tooltip) {
        var wrapperAttrs = '';
        var buttonAttrs = ' type="button" class="btn-acao ' + className + '" data-adesao-id="' + adesaoId + '"';

        if (disabled) {
            buttonAttrs += ' disabled aria-disabled="true"';
            if (tooltip) {
                tooltip = this.escapeHtmlAttr(tooltip);
                wrapperAttrs = ' tabindex="0" data-bs-toggle="tooltip" title="' + tooltip + '" data-bs-title="' + tooltip + '"';
            }
        }

        return '<span class="adesao-action-wrapper"' + wrapperAttrs + '>'
            + '<button' + buttonAttrs + '>'
            + '<i class="' + iconClass + '"></i> ' + label
            + '</button>'
            + '</span>';
    },
    buscarAdesao: function (showLimitLoader) {
        var self = this;
        var $loader = jQuery('#limitLoader');
        var form = jQuery('#formBuscaAdesao');
        var data = form.serialize();
        var limit = parseInt(jQuery('#selectLimit').val(), 10);
        self._limit = isNaN(limit) || limit < 0 ? 10 : limit;
        var start = self._limit > 0 ? (self._currentPage - 1) * self._limit : 0;
        data += '&limit=' + self._limit + '&start=' + start;

        if (showLimitLoader) {
            $loader.prop('hidden', false);
        }

        jQuery.ajax({
            url: self.baseUrl + 'index.php?option=com_edital&task=formularioadesoes.buscar',
            type: 'POST',
            data: data,
            success: function (response) {
                self._total = response.total || 0;
                self.montarListaAdesao(response.items || []);
                self.montarPaginacao();
            },
            error: function (xhr, status, error) {
                console.error('Erro na busca de adesão:', error);
            },
            complete: function () {
                $loader.prop('hidden', true);
            }
        });
    },
    exportarPdf: function () {
        var self = this;
        var $loader = jQuery('#limitLoader');
        var $btn = jQuery('#btnExportarPdf');
        var url = (self.exportPdfUrl || '').replace(/&amp;/g, '&');
        var sep = url.indexOf('?') >= 0 ? '&' : '?';

        $loader.prop('hidden', false);
        $btn.prop('disabled', true);

        fetch(url + sep + jQuery('#formBuscaAdesao').serialize(), { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('pdf');
                }

                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^"]+)"?/i);
                var filename = match ? match[1] : 'relatorio.pdf';

                return response.blob().then(function (blob) {
                    return { blob: blob, filename: filename };
                });
            })
            .then(function (result) {
                if (result.blob.type && result.blob.type.indexOf('pdf') === -1 && result.blob.type.indexOf('octet-stream') === -1) {
                    throw new Error('pdf');
                }

                var link = document.createElement('a');
                link.href = window.URL.createObjectURL(result.blob);
                link.download = result.filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(link.href);
            })
            .catch(function () {
                window.alert('Não foi possível exportar o PDF.');
            })
            .finally(function () {
                $loader.prop('hidden', true);
                $btn.prop('disabled', false);
            });
    },
    montarPaginacao: function () {
        var self = this;
        var container = jQuery('#adesaoPaginacao');

        if (!container.length) {
            container = jQuery('<div id="adesaoPaginacao" class="adesao-paginacao"></div>');
            jQuery('#adesaoList').after(container);
        }

        container.empty();

        if (self._limit <= 0 || self._total <= 0) {
            return;
        }

        var totalPages = Math.ceil(self._total / self._limit);

        if (totalPages <= 1) {
            return;
        }

        var html = '';
        var current = self._currentPage;

        // Botão anterior
        html += '<button class="paginacao-btn paginacao-nav" data-page="' + (current - 1) + '"' +
            (current <= 1 ? ' disabled' : '') + '>' +
            '<i class="fas fa-chevron-left"></i></button>';

        // Números de página (janela de ±2 ao redor da página atual)
        var windowStart = Math.max(1, current - 2);
        var windowEnd   = Math.min(totalPages, current + 2);

        if (windowStart > 1) {
            html += '<button class="paginacao-btn" data-page="1">1</button>';
            if (windowStart > 2) {
                html += '<span class="paginacao-ellipsis">&hellip;</span>';
            }
        }

        for (var i = windowStart; i <= windowEnd; i++) {
            html += '<button class="paginacao-btn' + (i === current ? ' paginacao-active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (windowEnd < totalPages) {
            if (windowEnd < totalPages - 1) {
                html += '<span class="paginacao-ellipsis">&hellip;</span>';
            }
            html += '<button class="paginacao-btn" data-page="' + totalPages + '">' + totalPages + '</button>';
        }

        // Botão próximo
        html += '<button class="paginacao-btn paginacao-nav" data-page="' + (current + 1) + '"' +
            (current >= totalPages ? ' disabled' : '') + '>' +
            '<i class="fas fa-chevron-right"></i></button>';

        // Info
        var startItem = (current - 1) * self._limit + 1;
        var endItem   = Math.min(current * self._limit, self._total);
        html += '<span class="paginacao-info">' + startItem + '–' + endItem + ' de ' + self._total + '</span>';

        container.html(html);
    },
    montarListaAdesao: function (adesoes) {
        var self = this;
        var container = jQuery('#adesaoList');
        container.empty();

        if (!adesoes || adesoes.length === 0) {
            container.append('<p class="adesao-vazio">Nenhuma adesão encontrada.</p>');
            return;
        }

        var statusMap = {
            '':               { label: 'Em análise',    cls: 'status-analise',       icon: 'far fa-clock' },
            'analise':        { label: 'Em análise',    cls: 'status-analise',       icon: 'far fa-clock' },
            'complementacao': { label: 'Em Complementação',cls: 'status-complementacao',icon: 'far fa-comment-alt' },
            'pendente':       { label: 'Enviado',      cls: 'status-deferido',      icon: 'far fa-check-circle' },
            'indeferido':     { label: 'Indeferido',    cls: 'status-indeferido',    icon: 'far fa-times-circle' },
            'recurso':        { label: 'Recurso',       cls: 'status-recurso',       icon: 'fas fa-gavel' },
            'rascunho':       { label: 'Rascunho',      cls: 'status-analise',      icon: 'fa fa-pencil' }
        };

        var esferaMap = {
            'municipal':  { label: 'Município',  icon: 'fas fa-city' },
            'estadual':   { label: 'Estado',     icon: 'fas fa-map-marker-alt' },
            'consorcio':  { label: 'Consórcios/Associações',  icon: 'fas fa-handshake' },
            'federal':    { label: 'Federal',    icon: 'fas fa-flag' }
        };

        adesoes.forEach(function (a) {
            var st     = statusMap[a.st_adesao] || statusMap[''];
            var esfera = esferaMap[a.tp_esfera_governamental] || { label: a.tp_esfera_governamental, icon: 'fas fa-building' };
            var estado = a.sg_estado ? ' &middot; ' + a.sg_estado : '';
            var esferaLabel = esfera.label + estado;
            var motivoBloqueio = a.motivoBloqueio || '';

            if(a.tp_esfera_governamental === 'municipal' && a.municipios ) {
                esferaLabel = a.municipios;
            }

            if(a.tp_esfera_governamental === 'consorcio' && a.municipios ) {
                esferaLabel = a.municipios;
            }

            var item =
                '<div class="adesao-item">' +
                    '<div class="adesao-item-row">' +
                        '<div class="adesao-main">' +
                            '<div class="adesao-header">' +
                                '<span class="adesao-protocolo">' + a.nr_protocolo + '</span>' +
                                '<span class="adesao-status ' + st.cls + '"><i class="' + st.icon + '"></i> ' + st.label + '</span>' +
                            '</div>' +
                            '<div class="adesao-nome">' + a.no_oficial + '</div>' +
                            '<div class="adesao-meta">' +
                                '<span><i class="' + esfera.icon + '"></i> ' + esferaLabel +  '</span>' ;
                                    item +=
                                '<span><i class="far fa-clock"></i> ' + a.dt_criacao_formatado + '</span>' +
                            '</div></div>'
                            + '<div class="adesao-docs">' 
                            ;

                            if(a.st_adesao == 'rascunho'){
                                var progressoTotal = (a.progresso && a.progresso.total) ? Number(a.progresso.total) : 0;
                                var progressoRespondidos = (a.progresso && a.progresso.respondidos) ? Number(a.progresso.respondidos) : 0;
                                var pcProgresso = progressoTotal > 0 ? (progressoRespondidos / progressoTotal) : 0;
                                var percentual = Math.min(Math.max(pcProgresso * 100, 0), 100);
                                item += '<div class="adesao-progresso">' +
                                    '<div class="adesao-progresso-track">' +
                                        '<span class="adesao-progresso-fill" style="width: ' + percentual + '%"></span>' +
                                    '</div>' +
                                    '<span class="adesao-progresso-text">' + Math.round(percentual) + '%</span>' +
                                '</div>' ;
                            }
                            item+= '<div><a href="'+self.baseUrlRespostas + (self.baseUrlRespostas.indexOf('?') === -1 ? '?' : '&') + 'id=' + a.id + '" class="btn btn-primary">Ver respostas</a></div>';
                        
            item+=    '</div>' +
                        
                    '</div>' +
                    
                '</div>';

            container.append(item);
        });

        self.initTooltips(container[0]);
    }
}
