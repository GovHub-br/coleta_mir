#!/usr/bin/env python3
"""Gera o SQL que leva o banco local ao estado do SQL gerado (gera_formularios.py), sem recriar
os formulários que já existem: atualiza no lugar (preserva as respostas) e insere os novos.

Uso: python3 sql/sincroniza_formularios.py sql/coleta-mir-formularios.sql <sigla>=<id do edital> ... > saida.sql
     ex.: ted=18 ted_ges=19 ted_ex=0 conv=20 conv_ges=21 conv_ex=0
     siglas sem id (ou id 0) são inseridas como novas, com o trecho do SQL gerado.
Lê o estado atual do banco pelo docker-compose (rodar na raiz do projeto).
"""
import re
import subprocess
import sys

GERADO = open(sys.argv[1], encoding="utf-8").read()
ALVOS = dict(a.split("=") for a in sys.argv[2:])


def mysql(sql):
    out = subprocess.run(
        ["docker-compose", "-f", "docker-compose-dev.yml", "exec", "-T", "coletamir_db", "sh", "-c",
         'mysql --default-character-set=utf8mb4 -B -N -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'],
        input=sql, capture_output=True, text=True, check=True).stdout
    return [l.split("\t") for l in out.splitlines() if l]


def campos(tupla):
    """Campos de "(a, 'b', @c, CAST(@d AS CHAR))" como texto SQL, sem alterar os literais."""
    res, i, atual, prof = [], 0, "", 0
    while i < len(tupla):
        c = tupla[i]
        if c == "'":
            j = i + 1
            while True:
                if tupla[j] == "\\":
                    j += 2
                elif tupla[j] == "'" and j + 1 < len(tupla) and tupla[j + 1] == "'":
                    j += 2
                elif tupla[j] == "'":
                    break
                else:
                    j += 1
            atual += tupla[i:j + 1]
            i = j + 1
            continue
        if c == "(":
            prof += 1
        elif c == ")":
            prof -= 1
        if c == "," and prof == 0:
            res.append(atual.strip())
            atual = ""
        else:
            atual += c
        i += 1
    res.append(atual.strip())
    return res


def texto(lit):
    return lit[1:-1].replace("''", "'").replace("\\\\", "\\") if lit.startswith("'") else lit


# Trechos por formulário: do INSERT do edital até o próximo
partes = re.split(r"(?=\n-- =+\n-- [^\n]+\n-- =+\n)", GERADO)
secoes = {}
for parte in partes:
    m = re.search(r"SET @(\w+)_edital = LAST_INSERT_ID\(\);", parte)
    if m:
        secoes[m.group(1)] = parte.split("\n\nCOMMIT;")[0]

saida = ["SET NAMES utf8mb4;", "START TRANSACTION;"]

for sigla, secao in secoes.items():
    if sigla not in ALVOS:
        continue
    edital = int(ALVOS[sigla] or 0)
    if edital == 0:
        saida.append(secao)
        continue

    saida.append(f"\n-- {sigla} -> edital {edital} (atualização no lugar)")
    saida.append(f"SET @{sigla}_edital = {edital};")
    stmts = [re.sub(r"^(--[^\n]*\n|\s)+", "", s).strip() for s in re.split(r";\n", secao)]
    stmts = [s for s in stmts if s]
    grupos, perguntas = [], []
    for st in stmts:
        if st.startswith("INSERT INTO `joom_tb_edital`"):
            v = campos(st[st.find("VALUES (") + 8:-1])
            saida.append(f"UPDATE joom_tb_edital SET no_edital = {v[4]}, ds_edital = {v[5]}, co_edital_adesao = {v[10]} WHERE id = {edital};")
        elif st.startswith("INSERT INTO `joom_tb_agrupamento`"):
            grupos.append({"v": campos(st[st.find("VALUES (") + 8:-1])})
        elif st.startswith("SET @") and "_g_" in st:
            grupos[-1]["var"] = st.split()[1]
        elif st.startswith("INSERT INTO `joom_tb_pergunta`"):
            perguntas.append({"v": campos(st[st.find("VALUES (") + 8:-1]), "grupo": grupos[-1], "var": None})
        elif st.startswith("SET @") and "_p_" in st:
            perguntas[-1]["var"] = st.split()[1]

    # Agrupamentos: pareados por posição com os do banco
    db_grupos = mysql(f"SELECT id FROM joom_tb_agrupamento WHERE co_editais = '{edital}' ORDER BY ordering, id;")
    for i, g in enumerate(grupos):
        v = g["v"]
        if i < len(db_grupos):
            gid = db_grupos[i][0]
            saida.append(f"UPDATE joom_tb_agrupamento SET ordering = {v[1]}, no_agrupamento = {v[4]}, ds_agrupamento = {v[5]}, "
                         f"no_titulo_tab = {v[6]}, ds_classe_icone = {v[7]} WHERE id = {gid};")
            saida.append(f"SET {g['var']} = {gid};")
        else:
            saida.append("INSERT INTO `joom_tb_agrupamento` (`state`, `ordering`, `created_by`, `modified_by`, `no_agrupamento`, "
                         f"`ds_agrupamento`, `no_titulo_tab`, `ds_classe_icone`, `co_editais`) VALUES ({', '.join(v)});")
            saida.append(f"SET {g['var']} = LAST_INSERT_ID();")
    sobra_grupos = [r[0] for r in db_grupos[len(grupos):]]

    # Perguntas: pareadas por título (n-ésima ocorrência)
    db_perg = mysql(f"SELECT p.id, p.ds_titulo FROM joom_tb_pergunta p LEFT JOIN joom_tb_agrupamento a ON a.id = p.co_agrupamento "
                    f"WHERE p.co_edital = {edital} ORDER BY a.ordering, p.ordering, p.id;")
    livres = {}
    for pid, titulo in db_perg:
        livres.setdefault(titulo, []).append(pid)
    usados = set()
    for n, p in enumerate(perguntas):
        v = p["v"]
        titulo = texto(v[4])
        var = p["var"] or f"@{sigla}_q{n}"
        p["qvar"] = var
        if livres.get(titulo):
            pid = livres[titulo].pop(0)
            usados.add(pid)
            saida.append(f"UPDATE joom_tb_pergunta SET ordering = {v[1]}, ds_descricao = {v[6]}, tp_pergunta = {v[7]}, ds_opcoes = {v[8]}, "
                         f"tp_requerida = {v[9]}, co_agrupamento = {v[11]}, depende_de = NULL, depende_de_valor = NULL WHERE id = {pid};")
            saida.append(f"SET {var} = {pid};")
        else:
            saida.append("INSERT INTO `joom_tb_pergunta` (`state`, `ordering`, `created_by`, `modified_by`, `ds_titulo`, `co_edital`, "
                         "`ds_descricao`, `tp_pergunta`, `ds_opcoes`, `tp_requerida`, `qt_dias_complementacao`, `co_agrupamento`, `tp_etapa`) "
                         f"VALUES ({', '.join(v[:13])});")
            saida.append(f"SET {var} = LAST_INSERT_ID();")
    for p in perguntas:
        v = p["v"]
        if v[13] != "NULL":
            saida.append(f"UPDATE joom_tb_pergunta SET depende_de = {v[13]}, depende_de_valor = {v[14]} WHERE id = {p['qvar']};")

    sobra = [pid for pid, _ in db_perg if pid not in usados]
    if sobra:
        ids = ", ".join(sobra)
        saida.append(f"-- perguntas que saíram do formulário: {len(sobra)}")
        saida.append(f"UPDATE joom_tb_pergunta SET depende_de = NULL, depende_de_valor = NULL WHERE depende_de IN ({ids});")
        saida.append(f"DELETE FROM joom_tb_resposta WHERE co_pergunta IN ({ids});")
        saida.append(f"DELETE FROM joom_tb_pergunta WHERE id IN ({ids});")
    for gid in sobra_grupos:
        saida.append(f"DELETE FROM joom_tb_agrupamento WHERE id = {gid} AND NOT EXISTS (SELECT 1 FROM joom_tb_pergunta WHERE co_agrupamento = {gid});")

saida.append("COMMIT;")
print("\n".join(saida))
