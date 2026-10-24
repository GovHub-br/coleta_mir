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
/* Modal Deferimento - Estilos específicos */
#modal-deferir .section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #888;
    margin-bottom: 12px;
}

#modal-deferir .modalidade-wrapper {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
    transition: border-color .15s;
}

#modal-deferir .modalidade-wrapper.is-invalid {
    border-color: #e74c3c;
}

#modal-deferir .modalidade-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #fafafa;
    border-bottom: 1px solid #e0e0e0;
    font-size: .9rem;
    color: #aaa;
}

#modal-deferir .modalidade-header.has-value {
    color: #333;
    font-weight: 500;
}

#modal-deferir .modalidade-opcao {
    padding: 12px 16px;
    font-size: .9rem;
    color: #333;
    cursor: pointer;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    transition: background .15s;
}

#modal-deferir .modalidade-opcao:last-child {
    border-bottom: 0;
}

#modal-deferir .modalidade-opcao:hover {
    background: #f0f7ee;
}

#modal-deferir .modalidade-opcao.selected {
    background: #eaf6ee;
    color: #1a5c2e;
    font-weight: 500;
}

#modal-deferir .invalid-feedback {
    display: none;
    font-size: 12px;
    color: #e74c3c;
    margin-top: 6px;
    margin-bottom: 16px;
}

#modal-deferir #deferir-observacoes {
    background-color: #fafafa;
}

#modal-deferir .form-label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 8px;
}

#modal-deferir .form-control {
    font-size: 13px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}

#modal-deferir .form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
    outline: none;
}
</style>

<!-- Modal Deferir Solicitação -->
<div class="modal fade" id="modal-deferir" tabindex="-1" aria-labelledby="modalDeferirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="modalDeferirLabel">Deferir Solicitação</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="info-text small">
                    Confirme o deferimento e defina a modalidade de gestão do ente federativo.
                </div>
                <br/>
                <p class="section-title">Modalidade de Gestão</p>

                <div class="modalidade-wrapper" id="deferir-modalidade-wrapper">
                    <div class="modalidade-header" id="deferir-modalidade-header">
                        <span id="deferir-modalidade-label">Selecione a modalidade</span>
                        <i class="fas fa-chevron-down" style="color: #aaa; font-size: 12px;"></i>
                    </div>
                    <div class="modalidade-opcao" data-valor="basica">Gestão Básica (1,5x)</div>
                    <div class="modalidade-opcao" data-valor="intermediaria">Gestão Intermediária (2x)</div>
                    <div class="modalidade-opcao" data-valor="plena">Gestão Plena (3x)</div>
                </div>
                <div class="invalid-feedback" id="deferir-modalidade-feedback">
                    Por favor, selecione a modalidade de gestão.
                </div>

                <div class="mb-3">
                    <label for="deferir-observacoes" class="form-label">
                        Observações <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <textarea
                        class="form-control"
                        id="deferir-observacoes"
                        rows="4"
                        placeholder="Adicione observações sobre o deferimento..."
                    ></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-success" id="btn-confirmar-deferir">
                    Confirmar deferimento
                </button>
            </div>
        </div>
    </div>
</div>
