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
/* Modal Indeferimento - Estilos específicos */




#modal-indeferir .motivos-lista {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

#modal-indeferir .motivo-opcao {
    display: flex;
    align-items: flex-start;
    padding: 12px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: .9rem;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    background: #fafafa;
}

#modal-indeferir .motivo-opcao:hover {
    background: #f0f4ff;
    border-color: #b0c4ff;
}

#modal-indeferir .motivo-opcao.selected {
    background: #d8d8d8;
    border-color: #4A2712;
    font-weight: 500;
}

#modal-indeferir .motivo-opcao input[type="radio"] {
    display: none;
}

#modal-indeferir .motivo-opcao label {
    flex: 1;
    cursor: pointer;
    margin: 0;
    font-size: 13.5px;
    line-height: 1.5;
}

#modal-indeferir #indeferir-justificativa {
    background-color: #fafafa;
}

#modal-indeferir .form-label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 8px;
}

#modal-indeferir .form-control {
    font-size: 13px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}


#modal-indeferir .form-control.is-invalid {
    border-color: #e74c3c;
}

#modal-indeferir .invalid-feedback {
    font-size: 12px;
    color: #e74c3c;
    margin-top: 6px;
}

#modal-indeferir .btn-cancelar {
    background: #fff;
    border: 1.5px solid #ccc;
    color: #444;
    padding: 8px 20px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.15s ease;
}

</style>

<!-- Modal Indeferir Solicitação -->
<div class="modal fade" id="modal-indeferir" tabindex="-1" aria-labelledby="modalIndeferirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="modalIndeferirLabel">Indeferir Solicitação</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="info-text small">
                    Informe o motivo do indeferimento. O ente federativo terá <strong>30 dias para interpor recurso</strong>.
                </div>
                <br/>
                <p class="section-title">Motivos padronizados</p>
                <div class="motivos-lista">
                    <div class="motivo-opcao" data-valor="A solicitação foi indeferida por não atender aos requisitos mínimos estabelecidos no Decreto nº 8.136/2013.">
                        <input type="radio" name="motivo_indeferimento" id="motivo1" value="decreto">
                        <label for="motivo1">A solicitação foi indeferida por não atender aos requisitos mínimos estabelecidos no Decreto nº 8.136/2013.</label>
                    </div>
                    <div class="motivo-opcao" data-valor="Após análise, verificou-se que o ente não possui estrutura mínima para adesão ao SINAPIR.">
                        <input type="radio" name="motivo_indeferimento" id="motivo2" value="estrutura">
                        <label for="motivo2">Após análise, verificou-se que o ente não possui estrutura mínima para adesão ao SINAPIR.</label>
                    </div>
                    <div class="motivo-opcao" data-valor="A documentação apresentada não comprova o funcionamento efetivo do órgão e/ou conselho de igualdade racial.">
                        <input type="radio" name="motivo_indeferimento" id="motivo3" value="documentacao">
                        <label for="motivo3">A documentação apresentada não comprova o funcionamento efetivo do órgão e/ou conselho de igualdade racial.</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="indeferir-justificativa" class="form-label">Justificativa <span style="color: #e74c3c;">*</span></label>
                    <textarea 
                        class="form-control" 
                        id="indeferir-justificativa" 
                        rows="5" 
                        placeholder="Descreva detalhadamente o motivo do indeferimento..."
                        required
                    ></textarea>
                    <div class="invalid-feedback">
                        Por favor, informe a justificativa para o indeferimento.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-indeferir">
                    Confirmar Indeferimento
                </button>
            </div>
        </div>
    </div>
</div>
