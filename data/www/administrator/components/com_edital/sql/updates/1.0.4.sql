-- Enable repeatable masked telephone inputs for the contact-phone question.
UPDATE `#__tb_pergunta`
SET `tp_pergunta` = 'telefones'
WHERE `id` = 34
  AND `ds_titulo` = 'Telefone(s), inclusive celular(es), com DDD';
