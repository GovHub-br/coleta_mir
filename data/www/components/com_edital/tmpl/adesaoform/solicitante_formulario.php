<div class="card tab-agrupamento solicitante active" data-tab-chave="solicitante">
            <div class="solicitante card-body p-4 active">
                <h3><i class="fa-solid fa-building"></i> Identificação</h3>
                <?php if ($this->user->id) : ?>
                <div class="row mt-4 mb-3">
                    <div class="col-md-12">
                        <div class=" p-3 ">
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="mb-2"><i class="fa-solid fa-user"></i> <strong>Nome</strong></p>
                                    <p class="text-muted"><?php echo $this->escape($this->user->name); ?></p>
                                    <p class="mb-1"><small><i class="fa-solid fa-file"></i> CPF</small></p>
                                    <p class="text-muted small"><?php echo $this->escape(preg_replace('/\d(?=\d{2})/', '*', $this->user->username ?? '')); ?></p>
                                    <p class="mb-1"><small><i class="fa-solid fa-envelope"></i> E-mail Institucional do Responsável</small></p>
                                    <p class="text-muted small"><?php echo $this->escape($this->user->email ?? ''); ?></p>
                                    <input type="hidden" id="ds_cpf" name="ds_cpf" value="<?php echo $this->escape($this->user->username ?? ''); ?>">
                                    <input type="hidden" id="no_responsavel" name="no_responsavel" value="<?php echo $this->escape($this->user->name ?? ''); ?>">
                                    <input type="hidden" id="ds_email_responsavel" name="ds_email_responsavel" value="<?php echo $this->escape($this->user->email ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                    <div class="row mt-4 mb-3" id="contato-solicitante-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='no_responsavel' class="form-label">Nome <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type='text' id='no_responsavel' name='no_responsavel'
                                        placeholder="Digite seu nome" class="form-control"
                                        autocomplete="name"
                                        value='<?php echo $this->escape($this->item->no_responsavel ?? ''); ?>'>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_email_responsavel' class="form-label">E-mail institucional do
                                    Responsável <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                    <input type='email' id='ds_email_responsavel' name='ds_email_responsavel'
                                        placeholder="Ex.: seuemail@gov.br" class="form-control"
                                        autocomplete="off"
                                        value='<?php echo $this->escape($this->item->ds_email_responsavel ?? ''); ?>'>
                                </div>
                                <small class="form-text text-muted">Este e-mail será usado para envio de confirmações e
                                    contato.</small>
                            </div>
                        </div>
                        <div class="row mt-4 mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for='ds_cpf' class="form-label">CPF do Responsável <span class="text-danger">*</span></label>
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
                        <div class="col-md-6 campos-senha mt-3">
                            <div class="form-group">
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
                        <div class="col-md-6 campos-senha mt-3">
                            <div class="form-group">
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
                    </div>
                <?php endif; ?>


            </div>
        </div>