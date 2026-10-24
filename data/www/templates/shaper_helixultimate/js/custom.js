/**
 * Scripts do portal SINAPIR (Helix Ultimate carrega este arquivo em todas as páginas).
 */
(function () {
    'use strict';

    function normalizar(texto) {
        return (texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function filtrarContatos(raiz) {
        var campo = raiz.querySelector('[data-sinapir-busca]');
        var selecao = raiz.querySelector('[data-sinapir-uf]');
        var linhas = raiz.querySelectorAll('tbody tr[data-busca]');
        var grupos = raiz.querySelectorAll('[data-uf-grupo]');
        var meta = raiz.querySelector('[data-sinapir-meta]');
        var vazio = raiz.querySelector('[data-sinapir-vazio]');
        var termo = normalizar(campo ? campo.value : '');
        var uf = selecao ? selecao.value : '';
        var visiveis = 0;
        var total = linhas.length;

        linhas.forEach(function (linha) {
            var okTermo = !termo || normalizar(linha.getAttribute('data-busca')).indexOf(termo) !== -1;
            var okUf = !uf || linha.getAttribute('data-uf') === uf;
            var mostra = okTermo && okUf;
            linha.classList.toggle('d-none', !mostra);
            if (mostra) {
                visiveis += 1;
            }
        });

        grupos.forEach(function (grupo) {
            var restantes = grupo.querySelectorAll('tbody tr[data-busca]:not(.d-none)');
            var mostraGrupo = restantes.length > 0 && (!uf || grupo.getAttribute('data-uf-grupo') === uf);
            grupo.classList.toggle('d-none', !mostraGrupo);

            var painel = grupo.querySelector('.accordion-collapse');
            var botao = grupo.querySelector('.accordion-button');
            if (mostraGrupo && termo && painel && botao) {
                painel.classList.add('show');
                botao.classList.remove('collapsed');
                botao.setAttribute('aria-expanded', 'true');
            }

            var contador = grupo.querySelector('[data-sinapir-qtd]');
            if (contador) {
                contador.textContent = restantes.length + (restantes.length === 1 ? ' município' : ' municípios');
            }
        });

        if (meta) {
            meta.textContent = visiveis + ' de ' + total + ' registro' + (total === 1 ? '' : 's');
        }
        if (vazio) {
            vazio.classList.toggle('d-none', visiveis > 0);
        }
    }

    function iniciarContatos(raiz) {
        if (raiz.getAttribute('data-filtro-pronto') === '1') {
            return;
        }
        raiz.setAttribute('data-filtro-pronto', '1');

        var campo = raiz.querySelector('[data-sinapir-busca]');
        var selecao = raiz.querySelector('[data-sinapir-uf]');

        if (campo) {
            campo.addEventListener('input', function () {
                filtrarContatos(raiz);
            });
        }
        if (selecao) {
            selecao.addEventListener('change', function () {
                filtrarContatos(raiz);
            });
        }

        filtrarContatos(raiz);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sinapir-contatos').forEach(iniciarContatos);
    });
})();
