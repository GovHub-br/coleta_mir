CREATE TABLE IF NOT EXISTS `#__tb_edital` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`no_edital` VARCHAR(255)  NOT NULL ,
`ds_edital` TEXT NULL ,
`dt_abertura` DATETIME NULL  DEFAULT NULL ,
`dt_encerramento` DATETIME NULL  DEFAULT NULL ,
`dt_publicacao` DATETIME NULL  DEFAULT NULL ,
`tp_edital` VARCHAR(24) NULL DEFAULT 'adesao',
`co_edital_adesao` INT NULL DEFAULT NULL ,
`tp_esfera_solicitante` VARCHAR(24) NULL DEFAULT NULL ,
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__tb_pergunta` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`ds_titulo` VARCHAR(255)  NOT NULL ,
`co_edital` INT(10)  NOT NULL  DEFAULT 0,
`ds_descricao` TEXT NULL ,
`tp_pergunta` VARCHAR(255)  NULL  DEFAULT "",
`ds_opcoes` MEDIUMTEXT NULL ,
`tp_requerida` VARCHAR(255)  NULL  DEFAULT "",
`ds_informacao_complementar` TEXT NULL ,
`qt_dias_complementacao` INT(11) NULL DEFAULT 5,
`ds_subtitulo` VARCHAR(255)  NULL  DEFAULT "",
`tp_etapa` VARCHAR(255)  NOT NULL ,
`ds_informacao_complementar2` TEXT NULL ,
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `#__tb_pergunta_co_edital` ON `#__tb_pergunta`(`co_edital`);

CREATE TABLE IF NOT EXISTS `#__tb_adesao` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`asset_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',

`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`tp_esfera_governamental` VARCHAR(255)  NOT NULL ,
`co_edital` INT(10)  NOT NULL  DEFAULT 0,
`ds_cnpj` VARCHAR(255)  NOT NULL ,
`no_oficial` VARCHAR(255)  NOT NULL ,
`ds_email_responsavel` VARCHAR(255)  NOT NULL ,
`dt_criacao` DATETIME NULL  DEFAULT NULL ,
`sg_estado` TEXT NULL ,
`ds_municipios` TEXT NULL ,
`st_adesao` VARCHAR(255)  NOT NULL ,
`ds_observacao_interna` TEXT NULL ,
`ds_resposta` TEXT NULL ,
`dt_limite_situacao` DATETIME NULL  DEFAULT NULL ,
`ds_emails_alternativos` VARCHAR(255)  NULL  DEFAULT "",
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `#__tb_adesao_co_edital` ON `#__tb_adesao`(`co_edital`);

-- Espelho do data warehouse (EspelhoDwHelper).
CREATE TABLE IF NOT EXISTS `#__coleta_dw_opcao` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
`fonte` VARCHAR(64) NOT NULL,
`valor` VARCHAR(1000) NOT NULL,
`nome` TEXT NOT NULL,
`filtro` VARCHAR(191) NOT NULL DEFAULT '',
`ordem` INT NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
KEY `idx_fonte_filtro` (`fonte`, `filtro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__coleta_dw_registro` (
`registro` VARCHAR(32) NOT NULL,
`chave` VARCHAR(191) NOT NULL,
`dados` MEDIUMTEXT NOT NULL,
`dt_sincronizacao` DATETIME NOT NULL,
PRIMARY KEY (`registro`, `chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__coleta_dw_sincronizacao` (
`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
`dt_inicio` DATETIME NOT NULL,
`dt_fim` DATETIME NULL,
`st_sincronizacao` VARCHAR(20) NOT NULL,
`qt_opcoes` INT NOT NULL DEFAULT 0,
`qt_registros` INT NOT NULL DEFAULT 0,
`qt_perguntas` INT NOT NULL DEFAULT 0,
`ds_mensagem` TEXT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__tb_resposta` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified_by` INT(11)  NULL  DEFAULT 0,
`co_pergunta` INT(10)  NOT NULL  DEFAULT 0,
`co_adesao` INT(10)  NOT NULL  DEFAULT 0,
`ds_resposta` TEXT NULL ,
`dt_criacao` DATETIME NULL  DEFAULT NULL ,
`dt_alterado` DATETIME NULL  DEFAULT NULL ,
`dt_limite` DATETIME NULL DEFAULT NULL,
`ds_comentario_avaliador` TEXT NULL ,
`st_situacao` VARCHAR(255)  NULL  DEFAULT "",
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_created_by` (`created_by`)
,KEY `idx_modified_by` (`modified_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `#__tb_resposta_co_pergunta` ON `#__tb_resposta`(`co_pergunta`);

CREATE INDEX `#__tb_resposta_co_adesao` ON `#__tb_resposta`(`co_adesao`);

CREATE INDEX `#__tb_resposta_st_situacao_dt_limite` ON `#__tb_resposta`(`st_situacao`, `dt_limite`);


INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Edital','com_edital.edital','{"special":{"dbtable":"#__tb_edital","key":"id","type":"EditalTable","prefix":"Joomla\\\\Component\\\\Edital\\\\Administrator\\\\Table\\\\"}}', CASE 
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE 
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_edital\/forms\/edital.xml", "hideFields":["checked_out","checked_out_time","params","language" ,"ds_edital"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"group_id","targetTable":"#__usergroups","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_edital.edital')
) LIMIT 1;

INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Pergunta','com_edital.pergunta','{"special":{"dbtable":"#__tb_pergunta","key":"id","type":"PerguntaTable","prefix":"Joomla\\\\Component\\\\Edital\\\\Administrator\\\\Table\\\\"}}', CASE 
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE 
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_edital\/forms\/pergunta.xml", "hideFields":["checked_out","checked_out_time","params","language" ,"ds_informacao_complementar2"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"group_id","targetTable":"#__usergroups","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"co_edital","targetTable":"#__tb_edital","targetColumn":"id","displayColumn":"no_edital"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_edital.pergunta')
) LIMIT 1;

INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Adesão','com_edital.adesao','{"special":{"dbtable":"#__tb_adesao","key":"id","type":"AdesaoTable","prefix":"Joomla\\\\Component\\\\Edital\\\\Administrator\\\\Table\\\\"}}', CASE 
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE 
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_edital\/forms\/adesao.xml", "hideFields":["checked_out","checked_out_time","params","language" ,"ds_resposta"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"group_id","targetTable":"#__usergroups","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"co_edital","targetTable":"#__tb_edital","targetColumn":"id","displayColumn":"no_edital"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_edital.adesao')
) LIMIT 1;
