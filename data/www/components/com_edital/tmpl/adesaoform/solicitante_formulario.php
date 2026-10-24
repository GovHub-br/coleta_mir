<div class="card tab-agrupamento solicitante active" data-tab-chave="solicitante">
            <div class="solicitante card-body p-4 active">
                <h3><i class="fa-solid fa-building"></i> Identificação do Solicitante</h3>
                <div class="row mt-4 mb-3">
                    <div class="col-md-12">
                        <div class=" p-3 ">
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="mb-2"><strong><?php echo $this->item->adesaoOrigem->no_oficial; ?></strong></p>
                                    <p class="text-muted">CNPJ: <?php echo preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->item->adesaoOrigem->ds_cnpj); ?></p>
                                    <p class="mb-1"><small><i class="fa-solid fa-envelope"></i> E-mail Institucional do Responsável</small></p>
                                    <p class="text-muted small"><?php echo $this->item->adesaoOrigem->ds_email_responsavel;
                                    
                                    if(!empty($this->item->adesaoOrigem->ds_emails_alternativos)) {
                                        echo ' / ' . $this->item->adesaoOrigem->ds_emails_alternativos;
                                    }
                                    
                                    ?></p>
                                
                                    <?php if($this->item->adesaoOrigem->tp_esfera_governamental != 'estadual') { ?>
                                        <p class="mb-2"><strong>Municípios por UF</strong></p>
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
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="co_adesao_origem" value="<?php echo (int) $this->item->co_adesao_origem; ?>" />
                <div class="row mt-4 mb-3 d-none">
                    <input type="radio" name="tp_esfera_governamental" value="<?php echo $this->item->adesaoOrigem->tp_esfera_governamental; ?>" checked/>
                </div>


                <div class="formSolicitante d-none" >
                    <div class="row mt-4 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_cnpj' id="labelCnpjSolicitante" class="form-label">CNPJ Solicitante <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type='text' id='ds_cnpj' name='ds_cnpj' class="form-control"
                                           value='<?php echo $this->item->adesaoOrigem->ds_cnpj; ?>' maxlength="18"
                                           placeholder="00.000.000/0000-00">
                                </div>
                                <div id="cnpj-error" class="form-text text-danger" style="display: none;">CNPJ inválido</div>
                                <small class="form-text text-muted">Somente números. O sistema buscará os dados automaticamente.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_cpf' class="form-label">CPF do Solicitante <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type='text' id='ds_cpf' name='ds_cpf' class="form-control"
                                           value='<?php echo htmlspecialchars($this->user->username ?? '', ENT_QUOTES, 'UTF-8'); ?>'
                                           maxlength="14" inputmode="numeric" autocomplete="off"
                                           <?php echo $this->user->id ? 'readonly' : ''; ?>
                                           placeholder="000.000.000-00">
                                </div>
                                <small id="cpf-error" class="form-text text-danger" style="display: none;">CPF inválido</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4 mb-3 d-none" id="nome-oficial-group">
                        <div class="col-md-6">
                            <div class="form-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Campo exibido somente para Consórcios/Associações de Municípios. Para Estado e Município, o nome é obtido da localização selecionada.
                                    </div>
                                <?php endif; ?>
                                <label for='no_oficial' id="labelNomeOficial" class="form-label"><?php echo $this->escape($labelNomeOficial); ?> <span class="text-danger">*</span></label>
                                <input type='text' id='no_oficial' name='no_oficial' class="form-control"
                                       value='<?php echo $this->escape((string) $this->item->adesaoOrigem->no_oficial); ?>'
                                       placeholder="<?php echo $this->escape($placeholderNomeOficial); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4 mb-3" id="localizacao-solicitante-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='sg_estado' id="labelEstadoSolicitante" class="form-label">UF do Solicitante <span class="text-danger">*</span></label>
                                <select id='sg_estado' name='sg_estado' class="form-control" readonly>
                                    <option value="">Selecione um estado</option>
                                    <?php
                                    $options = $this->sg_estado_options;
                                    foreach ($options as $option) {
                                        $selected = ($this->item->adesaoOrigem->sg_estado_uf == $option->value) ? 'selected' : '';
                                        echo '<option value="' . $option->value . '" ' . $selected . '>' . $option->text . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group" id="municipios-select-group">
                                <label for='ds_municipios_select' class="form-label" id="label-municipio">Municípios <span class="text-danger municipios-required-asterisk">*</span></label>
                                <div class="municipios-input-wrapper">
                                    <!-- Container para as tags integrado -->
                                    <div id="municipios-tags-container" class="municipios-tags-container">
                                        <?php foreach ($this->item->adesaoOrigem->ds_municipios_cod_ibge as $municipio) : ?>
                                            <div class="municipio-tag" data-co-ibge="<?php echo (int) $municipio; ?>">
                                                <span><?php echo $this->escape($municipio); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- Select integrado -->
                                    <select id='ds_municipios_select' class="form-control municipios-select-integrated" style="display:none;">
                                        <option value="222" selected>Selecione um estado primeiro</option>
                                    </select>
                                </div>
                                <!-- Campos hidden para armazenar os valores selecionados -->
                                <?php
                                foreach ($this->item->adesaoOrigem->ds_municipios_cod_ibge as $k=>$municipio) { 

                                    ?>
                                    <input type="hidden" name="ds_municipios[]" value="<?php echo (int) $municipio; ?>" data-co-ibge="<?php echo (int) $municipio; ?>"/>
                                <?php } ?>
                                <div id="municipios-hidden-inputs">
                                </div>
                                <small class="form-text text-muted"><?php echo $travado
                                    ? 'Localidade definida pelo link de acesso recebido pelo ente.'
                                    : 'Selecione o município no campo acima. Clique no X para remover.'; ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4 mb-3" id="contato-solicitante-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_email_responsavel' class="form-label">E-mail institucional do
                                    Responsável <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type='email' id='ds_email_responsavel' name='ds_email_responsavel'
                                           placeholder="Ex.: seuemail@gov.br" class="form-control"
                                           autocomplete="off"
                                           value='<?php echo $this->item->adesaoOrigem->ds_email_responsavel; ?>' <?php echo $this->user->id ? 'readonly' : ''; ?>>
                                </div>
                                <small class="form-text text-muted">Este e-mail será usado para envio de confirmações e
                                    contato.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_emails_alternativos' class="form-label">E-mail Alternativo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type='email' id='ds_emails_alternativos' name='ds_emails_alternativos'
                                            autocomplete="off"
                                           placeholder="Ex.: seuemail@gov.br" class="form-control"
                                           value='<?php echo $this->item->adesaoOrigem->ds_emails_alternativos; ?>'>
                                </div>
                            </div>
                        </div>
                    </div>
                    <small id="campos-obrigatorios-consorcio" class="d-none form-text">*Campos obrigatórios</small>
                </div>
            </div>
        </div>