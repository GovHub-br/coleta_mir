-- Adicionar coluna ds_informacao_complementar2 na tabela #__tb_pergunta
ALTER TABLE `#__tb_pergunta` ADD COLUMN `ds_informacao_complementar2` TEXT NULL AFTER `ds_informacao_complementar`;
