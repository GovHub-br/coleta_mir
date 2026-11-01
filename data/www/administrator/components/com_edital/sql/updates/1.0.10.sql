-- Perfis (grupos de usuários, ids separados por vírgula) que podem iniciar o formulário pelo botão "Novo instrumento".
ALTER TABLE `#__tb_edital`
    ADD COLUMN `co_grupos` VARCHAR(255) NULL DEFAULT NULL;
