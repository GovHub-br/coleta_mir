#!/bin/bash
# Gera o seed (sql/coletamir-seed.sql) a partir do banco do container, sem dados pessoais.
# Uso: sql/gera_seed.sh sql/coletamir-seed.sql
set -euo pipefail
OUT="$1"
EXC="action_logs action_logs_users coleta_dw_opcao coleta_dw_registro coleta_dw_sincronizacao coleta_solicitacao_acesso history session scheduler_logs tb_adesao tb_municipio_adesao tb_resposta updates user_keys user_profiles user_usergroup_map users user_notes user_mfa messages messages_cfg privacy_requests privacy_consents mail_queue webauthn_credentials finder_links finder_links_terms finder_logging finder_terms finder_terms_common finder_tokens finder_tokens_aggregate finder_taxonomy finder_taxonomy_map oidc_identities redirect_links banner_tracks tuf_metadata"
IGN=""; for t in $EXC; do IGN+="--ignore-table=\$MYSQL_DATABASE.joom_$t "; done
OPTS="--single-transaction --no-tablespaces --skip-comments --skip-dump-date --skip-triggers --set-gtid-purged=OFF --default-character-set=utf8mb4"
D="docker exec coleta_mir-coletamir_db-1 sh -c"
{
  echo "-- Seed do Coleta MIR: estrutura completa e configuração do Joomla e dos formulários."
  echo "-- Sem dados pessoais: usuários, sessões, logs, solicitações de acesso, adesões, respostas e o"
  echo "-- espelho do DW (joom_coleta_dw_*, refeito sozinho) ficam só com a estrutura."
  echo "-- O administrador é criado depois da primeira subida (ver README)."
  echo
  $D "mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" $OPTS --no-data \"\$MYSQL_DATABASE\" 2>/dev/null" | sed -E 's/ AUTO_INCREMENT=[0-9]+//; s/DEFINER=`[^`]+`@`[^`]+` //'
  $D "mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" $OPTS --no-create-info --complete-insert $IGN \"\$MYSQL_DATABASE\" 2>/dev/null"
} > "$OUT"
# Chaves guardadas nos parâmetros das extensões (captcha, atualizador): zeradas; cada
# ambiente configura as suas no administrador.
python3 -I - "$OUT" <<'PY'
import re, sys
p = sys.argv[1]
t = open(p, encoding="utf-8").read()
n = 0
for chave in ("secret", "secretKey", "secret_key", "privateKey", "private_key", "update_token", "client_secret", "api_token", "apikey", "api_key", "password", "smtppass"):
    t, k = re.subn(r'(\\"%s\\":\\")[^"\\]+(\\")' % chave, r'\1\2', t)
    n += k
open(p, "w", encoding="utf-8").write(t)
print(f"{n} chave(s) zerada(s) nos parâmetros", file=sys.stderr)
PY
