-- Add configurable complementary days per question
ALTER TABLE `#__tb_pergunta`
    ADD COLUMN `qt_dias_complementacao` INT(11) NULL DEFAULT 5 AFTER `ds_informacao_complementar`;

-- Ensure existing records have the default value
UPDATE `#__tb_pergunta`
SET `qt_dias_complementacao` = 5
WHERE `qt_dias_complementacao` IS NULL OR `qt_dias_complementacao` <= 0;

-- Add deadline at resposta level
ALTER TABLE `#__tb_resposta`
    ADD COLUMN `dt_limite` DATETIME NULL DEFAULT NULL AFTER `dt_alterado`;

-- Backfill requested rule: existing complemento responses expire in 30 days from now
UPDATE `#__tb_resposta`
SET `dt_limite` = DATE_ADD(NOW(), INTERVAL 30 DAY)
WHERE `st_situacao` = 'complementacao' AND (`dt_limite` IS NULL OR `dt_limite` = '0000-00-00 00:00:00');

-- Improve scheduler performance for expired complemento lookup
CREATE INDEX `#__tb_resposta_st_situacao_dt_limite` ON `#__tb_resposta`(`st_situacao`, `dt_limite`);