-- Espelho do data warehouse (EspelhoDwHelper) e ds_opcoes maior para as listas sincronizadas (409 convênios).
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

ALTER TABLE `#__tb_pergunta` MODIFY `ds_opcoes` MEDIUMTEXT NULL;
