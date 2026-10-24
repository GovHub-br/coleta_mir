<?php
/**
 * @package    Com_Edital
 * @version    1.0.0
 * @author     Railson Rodrigues Leite
 * @copyright  2026 Railson Rodrigues Leite
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;
?>

<style>
/* Modal Modalidade - Estilos específicos */
#modal-modalidade .section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #888;
    margin-bottom: 12px;
}

#modal-modalidade .modalidade-wrapper {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
    transition: border-color .15s;
}

#modal-modalidade .modalidade-wrapper.is-invalid {
    border-color: #e74c3c;
}

#modal-modalidade .modalidade-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #fafafa;
    border-bottom: 1px solid #e0e0e0;
    font-size: .9rem;
    color: #aaa;
}

#modal-modalidade .modalidade-header.has-value {
    color: #333;
    font-weight: 500;
}

#modal-modalidade .modalidade-opcao {
    padding: 12px 16px;
    font-size: .9rem;
    color: #333;
    cursor: pointer;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}

#modal-modalidade .modalidade-opcao:last-child {
    border-bottom: 0;
}

#modal-modalidade .modalidade-opcao:hover {
    background: #f0f7ee;
}

#modal-modalidade .modalidade-opcao.selected {
    background: #eaf6ee;
    color: #1a5c2e;
    font-weight: 500;
}

#modal-modalidade .invalid-feedback {
    display: none;
    font-size: 12px;
    color: #e74c3c;
    margin-top: 6px;
    margin-bottom: 16px;
}
</style>

<!-- Modal Definir Modalidade -->
<div class="modal fade" id="modal-modalidade" tabindex="-1" aria-labelledby="modalModalidadeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="modalModalidadeLabel">Definir Modalidade de Gestão</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="info-text small">
                    Selecione a modalidade de gestão a ser atribuída a este ente federativo.
                </div>
                <br/>
                <p class="section-title">Modalidade de Gestão</p>

                <div class="modalidade-wrapper" id="modalidade-wrapper">
                    <div class="modalidade-header" id="modalidade-header">
                        <span id="modalidade-label">Selecione a modalidade</span>
                        <i class="fas fa-chevron-down" style="color: #aaa; font-size: 12px;"></i>
                    </div>
                    <div class="modalidade-opcao" data-valor="basica">Gestão Básica (1,5x)</div>
                    <div class="modalidade-opcao" data-valor="intermediaria">Gestão Intermediária (2x)</div>
                    <div class="modalidade-opcao" data-valor="plena">Gestão Plena (3x)</div>
                </div>
                <div class="invalid-feedback" id="modalidade-feedback">
                    Por favor, selecione a modalidade de gestão.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-modalidade">
                    <i class="fas fa-award"></i> Salvar modalidade
                </button>
            </div>
        </div>
    </div>
</div>
