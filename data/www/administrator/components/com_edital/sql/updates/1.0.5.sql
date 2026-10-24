ALTER TABLE `#__tb_edital`
    ADD COLUMN `tp_edital` varchar(24) DEFAULT 'adesao' NULL;

ALTER TABLE `#__tb_edital`
    ADD COLUMN `co_edital_adesao` int DEFAULT NULL NULL;
