
var Adesoespreenchimento = {
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

            jQuery('#selectEnte, #selectOrdem, #selectLimit, #selectMovimento').change(function () {
                clearTimeout(self._debounceTimer);
                var label = jQuery(this).find('option:selected').text();
                jQuery(this).siblings('.control-dropdown').find('span').text(label);
                self.sincronizarCards();
                self._currentPage = 1;
                self.buscarAdesao(this.id === 'selectLimit');
            });

            jQuery('.adesoes-preenchimento .stat-card').on('click', function () {
                var card = jQuery(this);
                if (card.attr('data-total')) {
                    jQuery('#selectEnte, #selectMovimento').val('');
                } else if (card.attr('data-ente')) {
                    jQuery('#selectEnte').val(card.attr('data-ente'));
                } else {
                    var movimento = card.attr('data-movimento');
                    var selectMovimento = jQuery('#selectMovimento');
                    selectMovimento.val(selectMovimento.val() === movimento ? '' : movimento);
                }
                jQuery('#selectEnte, #selectMovimento').each(function () {
                    jQuery(this).siblings('.control-dropdown').find('span').text(jQuery(this).find('option:selected').text());
                });
                jQuery('#selectEnte').trigger('change');
            });
            self.sincronizarCards();

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
    sincronizarCards: function () {
        var ente = jQuery('#selectEnte').val();
        var movimento = jQuery('#selectMovimento').val();
        jQuery('.adesoes-preenchimento .stat-card').each(function () {
            var card = jQuery(this);
            var ativo = card.attr('data-total') ? !ente && !movimento
                : card.attr('data-ente') ? card.attr('data-ente') === ente
                : card.attr('data-movimento') === movimento;
            card.attr('aria-pressed', ativo ? 'true' : 'false');
        });
    },
    escapeHtml: function (value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
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
            url: 'index.php?option=com_edital&task=adesoespreenchimento.buscar',
            type: 'POST',
            data: data,
            success: function (response) {
                self._total = response.total || 0;
                self.montarListaAdesao(response.items || []);
                self.montarPaginacao();
            },
            error: function (xhr, status, error) {
                console.error('Erro na busca de adesões em preenchimento:', error);
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

        html += '<button class="paginacao-btn paginacao-nav" data-page="' + (current - 1) + '"' +
            (current <= 1 ? ' disabled' : '') + '>' +
            '<i class="fas fa-chevron-left"></i></button>';

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

        html += '<button class="paginacao-btn paginacao-nav" data-page="' + (current + 1) + '"' +
            (current >= totalPages ? ' disabled' : '') + '>' +
            '<i class="fas fa-chevron-right"></i></button>';

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
            container.append('<p class="adesao-vazio">Nenhuma adesão em preenchimento encontrada.</p>');
            return;
        }

        var esferaMap = {
            'municipal':  { label: 'Município',  icon: 'fas fa-city' },
            'estadual':   { label: 'Estado',     icon: 'fas fa-map-marker-alt' },
            'consorcio':  { label: 'Consórcios/Associações',  icon: 'fas fa-handshake' },
            'federal':    { label: 'Federal',    icon: 'fas fa-flag' }
        };

        adesoes.forEach(function (a) {
            var esfera = esferaMap[a.tp_esfera_governamental] || { label: a.tp_esfera_governamental, icon: 'fas fa-building' };
            var estado = a.sg_estado ? ' &middot; ' + self.escapeHtml(a.sg_estado) : '';
            var esferaLabel = esfera.label + estado;
            var percentual = Math.max(0, Math.min(100, parseInt(a.pc_preenchimento, 10) || 0));
            var preenchidas = parseInt(a.qt_preenchidas, 10) || 0;
            var perguntas = parseInt(a.qt_perguntas, 10) || 0;
            var email = a.ds_email_responsavel || '';

            if (a.tp_esfera_governamental === 'municipal' && a.municipios) {
                esferaLabel = self.escapeHtml(a.municipios);
            }

            if (a.tp_esfera_governamental === 'consorcio' && a.municipios) {
                esferaLabel = self.escapeHtml(a.municipios);
            }

            var emailHtml = email
                ? '<span><i class="far fa-envelope"></i> <a href="mailto:' + self.escapeHtml(email) + '">' + self.escapeHtml(email) + '</a></span>'
                : '';

            var item =
                '<div class="adesao-item">' +
                    '<div class="adesao-item-row adesao-item-row-static">' +
                        '<div class="adesao-main">' +
                            '<div class="adesao-header">' +
                                '<span class="adesao-protocolo">' + self.escapeHtml(a.nr_protocolo) + '</span>' +
                                '<span class="adesao-status status-rascunho"><i class="far fa-edit"></i> Rascunho</span>' +
                            '</div>' +
                            '<div class="adesao-nome">' + self.escapeHtml(a.no_oficial) + '</div>' +
                            '<div class="adesao-meta">' +
                                '<span><i class="' + esfera.icon + '"></i> ' + esferaLabel + '</span>' +
                                emailHtml +
                                '<span><i class="far fa-clock"></i> Atualizado em ' + self.escapeHtml(a.dt_atualizacao_formatado) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="adesao-progresso">' +
                            '<div class="adesao-progresso-label">Preenchimento</div>' +
                            '<div class="adesao-progresso-bar">' +
                                '<div class="adesao-progresso-fill" style="width:' + percentual + '%"></div>' +
                            '</div>' +
                            '<div class="adesao-progresso-meta">' +
                                '<strong>' + percentual + '%</strong>' +
                                '<span>' + preenchidas + ' de ' + perguntas + ' campos</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            container.append(item);
        });
    }
};
