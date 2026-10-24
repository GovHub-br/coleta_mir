SET @db := DATABASE();

SET @tem_fonte := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = '#__tb_pergunta'
      AND COLUMN_NAME = 'depende_de_fonte'
);

SET @sql := IF(
    @tem_fonte = 0,
    'ALTER TABLE `#__tb_pergunta` ADD COLUMN `depende_de_fonte` VARCHAR(24) NULL DEFAULT NULL, ADD COLUMN `depende_de_ocultar_fonte` VARCHAR(24) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
