(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var preview = document.querySelector('.edital-adesaoform.is-preview');

        if (!preview) {
            return;
        }

        var links = Array.from(preview.querySelectorAll('.nav-link-agrupamento'));
        var sections = Array.from(preview.querySelectorAll('.tab-agrupamento[data-tab-chave]'));

        var parseValores = function (raw) {
            raw = String(raw || '').trim();
            if (!raw) {
                return [];
            }
            if (raw.charAt(0) === '[') {
                try {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        return parsed.map(function (item) { return String(item); }).filter(Boolean);
                    }
                } catch (e) {
                    return [raw];
                }
            }
            return [raw];
        };

        var rotulosSolicitante = {
            estadual: 'Estado',
            municipal: 'Município',
            consorcio: 'Consórcios/Associações de Municípios'
        };

        var tituloPergunta = function (sourceId) {
            if (sourceId === 'solicitante') {
                return 'Solicitante';
            }

            var source = preview.querySelector('.pergunta-field[data-pergunta-id="' + sourceId + '"]');
            var sourceLabel = source ? source.querySelector('.form-label') : null;
            return sourceLabel ? sourceLabel.textContent.replace(/\*/g, '').trim() : 'a pergunta anterior';
        };

        var textoValores = function (raw, sourceId) {
            return parseValores(raw).map(function (valor) {
                if (sourceId === 'solicitante' && rotulosSolicitante[valor]) {
                    valor = rotulosSolicitante[valor];
                }

                return '“' + valor + '”';
            }).join(', ');
        };

        Array.from(preview.querySelectorAll('.pergunta-field[data-depende-de], .pergunta-field[data-depende-ocultar]')).forEach(function (field) {
            var partes = [];

            if (field.dataset.dependeDe) {
                partes.push('Exibido quando “' + tituloPergunta(field.dataset.dependeDe) + '” for ' + textoValores(field.dataset.dependeDeValor, field.dataset.dependeDe) + '.');
            }

            if (field.dataset.dependeOcultar) {
                partes.push('Ocultado quando “' + tituloPergunta(field.dataset.dependeOcultar) + '” for ' + textoValores(field.dataset.dependeOcultarValor, field.dataset.dependeOcultar) + '.');
            }

            if (partes.length === 0) {
                return;
            }

            var condition = document.createElement('div');
            condition.className = 'adesao-preview-condition';
            condition.textContent = partes.join(' ');
            field.insertBefore(condition, field.firstChild);
        });

        function setActive(targetKey) {
            links.forEach(function (link) {
                var isActive = link.dataset.tabTarget === targetKey;
                link.classList.toggle('active', isActive);

                if (isActive) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }

        links.forEach(function (link) {
            link.setAttribute('role', 'link');

            link.addEventListener('click', function () {
                var targetKey = link.dataset.tabTarget;
                var target = sections.find(function (section) {
                    return section.dataset.tabChave === targetKey;
                });

                if (!target) {
                    return;
                }

                setActive(targetKey);
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                var visible = entries
                    .filter(function (entry) { return entry.isIntersecting; })
                    .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });

                if (visible.length > 0) {
                    setActive(visible[0].target.dataset.tabChave);
                }
            }, {
                rootMargin: '-15% 0px -65% 0px',
                threshold: [0, 0.2, 0.5]
            });

            sections.forEach(function (section) {
                observer.observe(section);
            });
        }
    });
})();
