<div class="card tab-agrupamento solicitante active" data-tab-chave="solicitante">
            <div class="solicitante card-body p-4 active">
                <h3><i class="fa-solid fa-building"></i> Identificação do Solicitante</h3>
                <div class="row mt-4 mb-3">
                    <h4>Solicitante <span class="text-danger">*</span></h4>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="esfera_estado"
                                   name="tp_esfera_governamental"
                                   value="estadual" <?php echo ($this->item->tp_esfera_governamental == 'estadual') ? 'checked' : ''; ?> <?php echo $travado ? 'disabled' : ''; ?>>
                            <label for="esfera_estado" class="form-check-label">Estado</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="esfera_municipio"
                                   name="tp_esfera_governamental"
                                   value="municipal" <?php echo ($this->item->tp_esfera_governamental == 'municipal') ? 'checked' : ''; ?> <?php echo $travado ? 'disabled' : ''; ?>>
                            <label for="esfera_municipio" class="form-check-label">Município</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="esfera-consorcio"
                                   name="tp_esfera_governamental"
                                   value="consorcio" <?php echo ($this->item->tp_esfera_governamental == 'consorcio') ? 'checked' : ''; ?> <?php echo $travado ? 'disabled' : ''; ?>>
                            <label for="esfera-consorcio" class="form-check-label">Consórcios/Associações de Municípios</label>
                        </div>
                    </div>
                    <?php if ($travado) : ?>
                        <input type="hidden"  name="tp_esfera_governamental" value="<?php echo $this->escape($esferaPrefill); ?>"/>
                    <?php endif; ?>
                </div>

                <?php if ($travado) : ?>
                    <div class="alert alert-info py-2" role="alert">
                        <i class="fa-solid fa-circle-info"></i>
                        A localidade foi preenchida automaticamente a partir do link de acesso enviado ao ente.
                        Em caso de divergência, entre em contato pelo e-mail
                        <a href="mailto:sinapir@igualdaderacial.gov.br">sinapir@igualdaderacial.gov.br</a>.
                    </div>
                <?php endif; ?>

                <div class="formSolicitante <?php echo $travado ? '' : 'd-none'; ?>" >
                    <div class="row mt-4 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_cnpj' id="labelCnpjSolicitante" class="form-label">CNPJ Solicitante <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type='text' id='ds_cnpj' name='ds_cnpj' class="form-control"
                                           value='<?php echo $this->item->ds_cnpj; ?>' maxlength="18"
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
                                       value='<?php echo $this->escape((string) $this->item->no_oficial); ?>'
                                       placeholder="<?php echo $this->escape($placeholderNomeOficial); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4 mb-3" id="localizacao-solicitante-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Para Consórcios/Associações, selecione um estado por vez para adicionar os municípios integrantes.
                                    </div>
                                <?php endif; ?>
                                <label for='sg_estado' id="labelEstadoSolicitante" class="form-label">UF do Solicitante <span class="text-danger">*</span></label>
                                <select id='sg_estado' name='sg_estado' class="form-control" <?php echo $travado ? 'disabled' : ''; ?>>
                                    <option value="">Selecione um estado</option>
                                    <?php
                                    $options = $this->sg_estado_options;
                                    foreach ($options as $option) {
                                        $selected = ($this->item->sg_estado == $option->value) ? 'selected' : '';
                                        echo '<option value="' . $option->value . '" ' . $selected . '>' . $option->text . '</option>';
                                    }
                                    ?>
                                </select>
                                <?php if ($travado) : ?>
                                    <input type="hidden" name="sg_estado" value="<?php echo $this->escape($prefill['uf']); ?>"/>
                                <?php endif; ?>
                                <?php if (!$travado) : ?>
                                    <small class="form-text text-muted orientacao-consorcio d-none">Selecione apenas um estado por vez.</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group" id="municipios-select-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Exibido para Município e Consórcios/Associações. Município permite uma seleção; Consórcios/Associações permitem vários municípios integrantes.
                                    </div>
                                <?php endif; ?>
                                <label for='ds_municipios_select' class="form-label" id="label-municipio">Municípios <span class="text-danger municipios-required-asterisk">*</span></label>
                                <div class="municipios-input-wrapper">
                                    <!-- Container para as tags integrado -->
                                    <div id="municipios-tags-container" class="municipios-tags-container">
                                        <?php if ($travado) : foreach ($prefill['municipios'] as $municipio) : ?>
                                            <div class="municipio-tag" data-co-ibge="<?php echo (int) $municipio->co_ibge; ?>">
                                                <span><?php echo $this->escape($municipio->no_municipio . ' (' . $municipio->sg_estado . ')'); ?></span>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                    <!-- Select integrado -->
                                    <select id='ds_municipios_select' class="form-control municipios-select-integrated" <?php echo $travado ? 'style="display:none"' : ''; ?>>
                                        <option value="">Selecione um estado primeiro</option>
                                    </select>
                                </div>
                                <!-- Campos hidden para armazenar os valores selecionados -->
                                <div id="municipios-hidden-inputs">
                                    <?php if ($travado) : foreach ($prefill['municipios'] as $municipio) : ?>
                                        <input type="hidden" name="ds_municipios[]" value="<?php echo (int) $municipio->co_ibge; ?>" data-co-ibge="<?php echo (int) $municipio->co_ibge; ?>"/>
                                    <?php endforeach; endif; ?>
                                </div>
                                <small class="form-text text-muted"><?php echo $travado
                                    ? 'Localidade definida pelo link de acesso recebido pelo ente.'
                                    : 'Selecione o município no campo acima. Clique no X para remover.'; ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2 mb-3">
                        <div class="col-12">
                            <div class="form-group d-none" id="municipios-emails-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Exibido apenas para Consórcios/Associações. Um e-mail deve ser informado para cada município integrante.
                                    </div>
                                <?php endif; ?>
                                <label class="form-label">E-mails dos municípios integrantes <span class="text-danger">*</span></label>
                                <div id="municipios-emails-container" class="municipios-emails-container"></div>
                                <small class="form-text text-muted">Informe um e-mail de contato para cada município selecionado nos Consórcios/Associações.</small>
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
                                           value='<?php echo $this->item->ds_email_responsavel ?? $this->user->email; ?>' <?php echo $this->user->id ? 'readonly' : ''; ?>>
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
                                           value='<?php echo $this->item->ds_emails_alternativos; ?>'>
                                </div>
                            </div>
                        </div>
                        <?php
                            //se nao for usuario logado pede a senha
                        if(!$this->user->id || $isPreview): ?>
                        <div class="col-md-6 campos-senha">
                            <div class="form-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Exibido quando o usuário não estiver autenticado.
                                    </div>
                                <?php endif; ?>
                                <label for='ds_senha' class="form-label">Senha <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                    <input type='password' id='ds_senha' name='ds_senha'
                                            autocomplete="new-password"
                                           placeholder="Digite sua senha" class="form-control"
                                           value="<?php echo $this->escape($this->item->ds_senha ?? ''); ?>">
                                </div>
                                <small class="form-text text-muted">Mínimo de 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.</small>
                            </div>
                        </div>
                        <div class="col-md-6 campos-senha">
                            <div class="form-group">
                                <?php if ($isPreview) : ?>
                                    <div class="adesao-preview-condition" role="note">
                                        Exibido quando o usuário não estiver autenticado.
                                    </div>
                                <?php endif; ?>
                                <label for='ds_confirma_senha' class="form-label">Confirme a Senha <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                    <input type='password' id='ds_confirma_senha' name='ds_confirma_senha'
                                            autocomplete="new-password"
                                           placeholder="Confirme sua senha" class="form-control"
                                           value="<?php echo $this->escape($this->item->ds_confirma_senha ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <small id="campos-obrigatorios-consorcio" class="d-none form-text">*Campos obrigatórios</small>
                </div>
            </div>
        </div>