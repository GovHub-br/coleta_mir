<?php

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;

?>
<div class="page-protocolo">
	<div class="aviso-sucesso-container">
		<i class="fas fa-check"></i>
		<h2>Envio realizado com sucesso!</h2>
		<p>Recebemos seus documentos. Obrigado por enviar as informações.</p>
	</div>

    <div class="row">
		<div class="col-md-12">
			<div class="detalhe-protocolo-container">
				<h3><i class="fas fa-file-alt"></i> Detalhes do Protocolo</h3>
				<div class="protocolo-details">
					<div class="protocolo-card protocolo-card-numero">
						<div class="protocolo-label">Número do Protocolo</div>
						<div class="protocolo-value">SINAPIR-<?php echo HTMLHelper::_('date', $this->item->created_at, 'Y'); ?>-<?php echo str_pad($this->item->id, 4, '0', STR_PAD_LEFT); ?></div>
					</div>
                    <div class="protocolo-card protocolo-card-status">
						<div class="protocolo-label">Município</div>
						<div class="protocolo-value"><?php echo $this->item->ds_municipios; ?></div>
					</div>
					<div class="protocolo-card full-width">
                        <div class="protocolo-label">Data e Hora do Envio</div>
						<div class="protocolo-value"><?php echo HTMLHelper::_('date', $this->item->created_at, 'd \d\e F \d\e Y, \à\s H:i'); ?></div>
					</div>
                    <div class="protocolo-card full-width">
                        <div class="col-md-12">
                            <p class="mb-2"><strong>Dados dos Consórcios/Associações</strong><br/><?php echo $this->item->adesaoOrigem->no_oficial; ?></p>
                            <p class="text-muted small">CNPJ: <?php echo preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->item->adesaoOrigem->ds_cnpj); ?></p>
                            <p class=""><small><i class="fa-solid fa-envelope"></i> E-mail Institucional do Responsável</small></p>
                            <p class="text-muted small"><?php echo $this->item->adesaoOrigem->ds_email_responsavel;
                            
                            if(!empty($this->item->adesaoOrigem->ds_emails_alternativos)) {
                                echo ' / ' . $this->item->adesaoOrigem->ds_emails_alternativos;
                            }
                            
                            ?></p>
                        
                            <p class="">Municípios por UF</p>
                            <p class="text-muted small">
                                <?php
                                $municipiosPorUf = [];
                                $municipiosRaw = (string) ($this->item->adesaoOrigem->ds_municipios ?? '');

                                if ($municipiosRaw !== '') {
                                    preg_match_all('/([^,]+?)\s*\(([A-Z]{2})\)/u', $municipiosRaw, $matches, PREG_SET_ORDER);

                                    foreach ($matches as $match) {
                                        $nomeMunicipio = trim($match[1]);
                                        $ufMunicipio = trim($match[2]);

                                        if ($nomeMunicipio === '' || $ufMunicipio === '') {
                                            continue;
                                        }

                                        if (!isset($municipiosPorUf[$ufMunicipio])) {
                                            $municipiosPorUf[$ufMunicipio] = [];
                                        }

                                        $municipiosPorUf[$ufMunicipio][] = $nomeMunicipio;
                                    }
                                }

                                if (!empty($municipiosPorUf)) {
                                    foreach ($municipiosPorUf as $uf => $municipios) {
                                        echo '<strong>' . $this->escape($uf) . ':</strong> ' . $this->escape(implode(', ', $municipios)) . '<br>';
                                    }
                                } else {
                                    echo 'Nenhum município informado.';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
				</div>
			</div>
			</div>
		</div>
	</div>
</div>