ALTER TABLE `#__tb_edital`
    ADD COLUMN `tp_esfera_solicitante` VARCHAR(24) NULL DEFAULT NULL;

ALTER TABLE `#__tb_agrupamento`
    ADD COLUMN `co_editais` VARCHAR(255) NULL DEFAULT NULL;
