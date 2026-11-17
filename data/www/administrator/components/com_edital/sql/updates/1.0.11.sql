-- Solicitações de acesso aos perfis do Coleta MIR (MIR, gestor de TED por UG, convenente por CNPJ).
CREATE TABLE IF NOT EXISTS `#__coleta_solicitacao_acesso` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
`user_id` INT NOT NULL,
`tp_perfil` VARCHAR(24) NOT NULL,
`tp_identificador` VARCHAR(8) NULL DEFAULT NULL,
`co_identificador` VARCHAR(32) NULL DEFAULT NULL,
`no_unidade` VARCHAR(255) NULL DEFAULT NULL,
`ds_justificativa` TEXT NULL,
`st_solicitacao` VARCHAR(12) NOT NULL DEFAULT 'pendente',
`ds_motivo` TEXT NULL,
`dt_solicitacao` DATETIME NOT NULL,
`dt_decisao` DATETIME NULL DEFAULT NULL,
`decidido_por` INT NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `idx_user` (`user_id`),
KEY `idx_situacao` (`st_solicitacao`),
KEY `idx_unidade` (`tp_identificador`, `co_identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
