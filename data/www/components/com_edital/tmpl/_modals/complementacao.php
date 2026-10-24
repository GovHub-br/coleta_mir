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
/* Modal Complementação - Estilos específicos */
#modal-complementacao .section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #888;
    margin-bottom: 12px;
}

#modal-complementacao .mensagens-lista {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
}

#modal-complementacao .mensagem-opcao {
    padding: 12px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: .9rem;
    color: #333;
    cursor: pointer;
    background: #fafafa;
    transition: background .15s, border-color .15s;
    line-height: 1.5;
}

#modal-complementacao .mensagem-opcao:hover {
    background: #fffbeb;
    border-color: #fbbf24;
}

#modal-complementacao .mensagem-opcao.selected {
    background: #fef9c3;
    border-color: #d97706;
    font-weight: 500;
}

#modal-complementacao #complementacao-mensagem {
    background-color: #fafafa;
}

#modal-complementacao #complementacao-mensagem.is-invalid {
    border-color: #e74c3c;
}

#modal-complementacao .invalid-feedback {
    font-size: 12px;
    color: #e74c3c;
    margin-top: 6px;
}

#modal-complementacao .form-label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
    margin-bottom: 8px;
}

#modal-complementacao .form-control {
    font-size: 13px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    padding: 10px 14px;
    transition: border-color 0.15s ease;
}

#modal-complementacao .form-control:focus {
    border-color: #d97706;
    box-shadow: 0 0 0 0.2rem rgba(217, 119, 6, 0.15);
    outline: none;
}
</style>

<!-- Modal Solicitar Complementação -->
<div class="modal fade" id="modal-complementacao" tabindex="-1" aria-labelledby="modalComplementacaoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="modalComplementacaoLabel">Solicitar Complementação</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="info-text small">
                    Envie uma mensagem ao ente federativo solicitando documentos ou informações adicionais.
                </div>
                <br/>
                <p class="section-title">Mensagens padronizadas</p>
                <div class="mensagens-lista">
                    <div class="mensagem-opcao" data-valor="Solicitamos a complementação do Plano de Promoção da Igualdade Racial, que deve conter metas e indicadores alinhados ao PLANAPIR.">
                        Solicitamos a complementação do Plano de Promoção da Igualdade Racial, que deve conter metas e indicadores alinhados ao PLANAPIR.
                    </div>
                    <div class="mensagem-opcao" data-valor="É necessário apresentar ata de reunião do Conselho com data mais recente (últimos 6 meses).">
                        É necessário apresentar ata de reunião do Conselho com data mais recente (últimos 6 meses).
                    </div>
                    <div class="mensagem-opcao" data-valor="O documento de comprovação orçamentária precisa estar atualizado para o exercício vigente.">
                        O documento de comprovação orçamentária precisa estar atualizado para o exercício vigente.
                    </div>
                    <div class="mensagem-opcao" data-valor="Favor apresentar decreto de nomeação do gestor atual do órgão de igualdade racial.">
                        Favor apresentar decreto de nomeação do gestor atual do órgão de igualdade racial.
                    </div>
                </div>

                <p class="section-title">Mensagem personalizada</p>
                <div class="mb-3">
                    <textarea
                        class="form-control"
                        id="complementacao-mensagem"
                        rows="4"
                        placeholder="Digite a mensagem de solicitação..."
                        required
                    ></textarea>
                    <div class="invalid-feedback">
                        Por favor, informe a mensagem de solicitação.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-warning" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-warning" id="btn-confirmar-complementacao">
                    Enviar solicitação
                </button>
            </div>
        </div>
    </div>
</div>
