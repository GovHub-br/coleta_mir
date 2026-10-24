SET @db := DATABASE();

SET @tem_depende_de := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND COLUMN_NAME = 'depende_de'
);

SET @sql := IF(
    @tem_depende_de = 0,
    'ALTER TABLE `#__tb_pergunta` ADD COLUMN `depende_de` INT NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tem_valor := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND COLUMN_NAME = 'depende_de_valor'
);

SET @sql := IF(
    @tem_valor = 0,
    'ALTER TABLE `#__tb_pergunta` ADD COLUMN `depende_de_valor` TEXT NULL',
    'ALTER TABLE `#__tb_pergunta` MODIFY `depende_de_valor` TEXT NULL'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tem_ocultar := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND COLUMN_NAME = 'depende_de_ocultar'
);

SET @sql := IF(
    @tem_ocultar = 0,
    'ALTER TABLE `#__tb_pergunta` ADD COLUMN `depende_de_ocultar` INT NULL DEFAULT NULL, ADD COLUMN `depende_de_ocultar_valor` TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `#__tb_pergunta` SET `depende_de` = NULL WHERE `depende_de` = 0;
UPDATE `#__tb_pergunta` SET `depende_de_ocultar` = NULL WHERE `depende_de_ocultar` = 0;

SET @tem_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND CONSTRAINT_NAME = 'FK_#__tb_pergunta_depende_de'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(
    @tem_fk = 0,
    'ALTER TABLE `#__tb_pergunta` ADD CONSTRAINT `FK_#__tb_pergunta_depende_de` FOREIGN KEY (`depende_de`) REFERENCES `#__tb_pergunta` (`id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @tem_fk_ocultar := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND CONSTRAINT_NAME = 'FK_#__tb_pergunta_depende_de_ocultar'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(
    @tem_fk_ocultar = 0,
    'ALTER TABLE `#__tb_pergunta` ADD CONSTRAINT `FK_#__tb_pergunta_depende_de_ocultar` FOREIGN KEY (`depende_de_ocultar`) REFERENCES `#__tb_pergunta` (`id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
