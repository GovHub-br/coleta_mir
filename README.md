# Coleta MIR

Sistema de coleta de informações sobre TEDs e convênios do Ministério da Igualdade Racial,
feito em Joomla (componente `com_edital`), com login pelo Gov.br e pré-preenchimento a partir
do data warehouse do [data-application-mir](https://github.com/GovHub-br/data-application-mir).

## Subir o ambiente

1. Copie o `.env.example` para `.env` e preencha as senhas, as chaves do site
   (`JOOMLA_SECRET` e `JOOMLA_CIPHER_KEY`, diferentes em cada ambiente) e, se for usar, o
   cliente do Gov.br e o acesso ao DW. O `.env` nunca vai para o git.
2. Suba os containers:

   ```bash
   docker compose -f docker-compose-dev.yml up -d
   ```

   Na primeira subida, o MySQL carrega `sql/coletamir-seed.sql`: a estrutura do banco e a
   configuração do Joomla e dos formulários, sem nenhum usuário nem resposta.
3. Crie o primeiro administrador:

   ```bash
   docker compose -f docker-compose-dev.yml exec -u www-data coletamir_joomla \
     php cli/joomla.php user:add --usergroup="Super Users"
   ```

   O comando pergunta nome, usuário, e-mail e senha.

## Formulários

`sql/gera_formularios.py` gera `sql/coleta-mir-formularios.sql` com os formulários de TED e de
convênio; `sql/sincroniza_formularios.py` gera o SQL que atualiza um banco já em uso.

## Seed do banco

`sql/gera_seed.sh` refaz o `sql/coletamir-seed.sql` a partir do banco do container. Ele leva só
estrutura e configuração: usuários, sessões, logs, solicitações de acesso, adesões, respostas e
o espelho do DW ficam de fora, e as chaves guardadas nos parâmetros das extensões saem zeradas.
Confira o arquivo antes de commitar.
