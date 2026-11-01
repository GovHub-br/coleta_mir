#!/usr/bin/env python3
"""Gera o SQL de carga dos formulários Coleta MIR (TEDs e Convênios) para o com_edital.

Uso (na raiz do projeto): python3 sql/gera_formularios.py sql/coleta-mir-formularios.sql
Para levar um banco que já tem os formulários ao novo estado sem perder respostas, ver
sql/sincroniza_formularios.py.
"""
import json
import sys

PREFIX = "joom_"
OUT = sys.argv[1]

lines = []

# Entradas de ds_opcoes que configuram a pergunta (não são alternativas)
PARAMETROS = {"permite_outro", "fonte_dw", "chave_dw", "campo_dw", "sugestoes_dw", "filtro_dw", "resposta_unica", "colunas",
              "origem", "origem_propria", "projetos_execucao", "propria_quando"}


def q(v):
    if v is None:
        return "NULL"
    if isinstance(v, int):
        return str(v)
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"


def opcoes_json(opcoes, outro=False):
    """Formato do subform ds_opcoes: {"ds_opcoes0": {"nome":..., "valor":...}, ...}."""
    if opcoes is None and not outro:
        return None
    itens = []
    if isinstance(opcoes, dict):
        itens = [(k, str(v)) for k, v in opcoes.items()]
    else:
        for o in opcoes or []:
            itens.append(o if isinstance(o, tuple) else (o, o))
    if outro:
        itens.append(("permite_outro", "sim"))
    return json.dumps(
        {f"ds_opcoes{i}": {"nome": n, "valor": v} for i, (n, v) in enumerate(itens)},
        ensure_ascii=False,
    )


class Form:
    def __init__(self, sigla, nome, descricao, ordem_base, origem=None):
        self.sigla = sigla
        self.ordem_grupo = ordem_base
        self.ordem_pergunta = 0
        self.grupo = None
        self.chaves = {}
        lines.append(f"\n-- {'=' * 70}\n-- {nome}\n-- {'=' * 70}")
        lines.append(
            f"INSERT INTO `{PREFIX}tb_edital` (`state`, `ordering`, `created_by`, `modified_by`, `no_edital`, `ds_edital`,"
            f" `dt_abertura`, `dt_encerramento`, `dt_publicacao`, `tp_edital`, `co_edital_adesao`)\nVALUES (1, 0, 0, 0, {q(nome)}, {q(descricao)},"
            f" '2026-11-01 00:00:00', '2027-12-31 23:59:59', '2026-11-01 00:00:00', 'formulario', {f'@{origem.sigla}_edital' if origem else 'NULL'});"
        )
        lines.append(f"SET @{sigla}_edital = LAST_INSERT_ID();")

    def bloco(self, chave, nome, tab, icone, descricao=""):
        self.ordem_grupo += 1
        self.ordem_pergunta = 0
        self.grupo = f"@{self.sigla}_g_{chave}"
        lines.append(f"\n-- {nome}")
        lines.append(
            f"INSERT INTO `{PREFIX}tb_agrupamento` (`state`, `ordering`, `created_by`, `modified_by`, `no_agrupamento`,"
            f" `ds_agrupamento`, `no_titulo_tab`, `ds_classe_icone`, `co_editais`)\nVALUES (1, {self.ordem_grupo}, 0, 0,"
            f" {q(nome)}, {q(descricao)}, {q(tab)}, {q(icone)}, CAST(@{self.sigla}_edital AS CHAR));"
        )
        lines.append(f"SET {self.grupo} = LAST_INSERT_ID();")

    def p(self, tipo, titulo, descricao="", opcoes=None, req=False, outro=False, dep=None, chave=None):
        assert len(titulo) <= 255, titulo
        self.ordem_pergunta += 1
        dep_id, dep_valor = "NULL", None
        if dep:
            dep_chave, valores = dep
            assert dep_chave in self.chaves, dep_chave
            faltando = set(valores if isinstance(valores, list) else [valores]) - set(self.chaves[dep_chave])
            assert not faltando, (titulo, faltando)
            dep_id = f"@{self.sigla}_p_{dep_chave}"
            dep_valor = json.dumps(valores if isinstance(valores, list) else [valores], ensure_ascii=False)
        lines.append(
            f"INSERT INTO `{PREFIX}tb_pergunta` (`state`, `ordering`, `created_by`, `modified_by`, `ds_titulo`, `co_edital`,"
            f" `ds_descricao`, `tp_pergunta`, `ds_opcoes`, `tp_requerida`, `qt_dias_complementacao`, `co_agrupamento`,"
            f" `tp_etapa`, `depende_de`, `depende_de_valor`)\nVALUES (1, {self.ordem_pergunta}, 0, 0, {q(titulo)},"
            f" @{self.sigla}_edital, {q(descricao)}, {q(tipo)}, {q(opcoes_json(opcoes, outro))}, {q('sim' if req else 'nao')},"
            f" 5, {self.grupo}, 'adesao', {dep_id}, {q(dep_valor)});"
        )
        if chave:
            assert chave not in self.chaves, chave
            self.chaves[chave] = [o[1] if isinstance(o, tuple) else o for o in (opcoes or []) if not (isinstance(o, tuple) and o[0] in PARAMETROS)]
            lines.append(f"SET @{self.sigla}_p_{chave} = LAST_INSERT_ID();")

    def secao(self, titulo, texto=""):
        """Subtítulo de seção (ex.: "3.1 Vinculação ao PPA") como pergunta informativa."""
        html = f"<h5 class=\"mt-3\">{titulo}</h5>" + (f"<p>{texto}</p>" if texto else "")
        self.p("informativo", titulo, html)

    def aviso(self, titulo, html, classe="info"):
        self.p("informativo", titulo, html, opcoes={"classe": classe})


# --------------------------------------------------------------------------
# Listas compartilhadas entre os dois instrumentos
# --------------------------------------------------------------------------
SIM_NAO = ["Sim", "Não"]
DATA = "Formato dd/mm/aaaa."
MOEDA = {"min": "0", "step": "0.01"}


def dw(campo, base=None):
    """Pré-preenchimento a partir do data warehouse: campo_dw = "registro.coluna"."""
    return {**(base or {}), "campo_dw": campo}


def moeda_dw(campo):
    return dw(campo, MOEDA)


def colunas(*defs):
    """Colunas de uma pergunta do tipo "tabela" (lista de itens): (chave, rótulo, tipo[, obrigatória][, opções])."""
    lista = []
    for d in defs:
        chave, rotulo, tipo = d[:3]
        col = {"chave": chave, "rotulo": rotulo, "tipo": tipo}
        if len(d) > 3 and d[3]:
            col["obrigatoria"] = True
        if len(d) > 4:
            col["opcoes"] = d[4]
        lista.append(col)
    return {"colunas": json.dumps(lista, ensure_ascii=False)}
def fiscais(f, titulo):
    f.p("tabela", titulo,
        "Adicione o fiscal titular e o(s) suplente(s), um por item. " + AUTO,
        colunas(
            ("funcao", "Função", "lista", True, ["Titular", "Suplente"]),
            ("nome", "Nome completo", "linha", True),
            ("matricula", "Matrícula SIAPE", "linha", True),
            ("email", "E-mail funcional", "linha", True),
        ))


AUTO = "Campo a ser preenchido automaticamente a partir do Transferegov.br / SIAFI / Siorg quando a integração estiver disponível."

TAGS = ["Quilombola", "Capacitação", "Saúde", "Sustentabilidade", "Igualdade Racial"]

PPA = [
    "5802 - Políticas para Quilombolas, Comunidades Tradicionais de Matriz Africana, Povos de Terreiros e Povos Ciganos",
    "5803 - Juventude Negra Viva",
    "5804 - Promoção da Igualdade Étnico-Racial, Combate e Superação do Racismo",
    "Não se aplica",
]
PPA_COM_PROGRAMA = PPA[:3]

OBJETIVOS_ESPECIFICOS = [
    "0264 - Criar mecanismos de identificação e de enfrentamento à violência e à discriminação contra quilombolas, povos e comunidades tradicionais de matriz africana, povos de terreiros e ciganos",
    "0323 - Fomentar ações que fortaleçam as práticas agroecológicas, o fornecimento energético, o saneamento, a soberania alimentar, a valorização cultural, social, os saberes e fazeres dos quilombolas, povos e comunidades tradicionais de matriz africana, povos de terreiros e ciganos",
]

ODS = [
    "1. Erradicação da Pobreza",
    "2. Fome Zero e Agricultura Sustentável",
    "3. Saúde e Bem-Estar",
    "4. Educação de Qualidade",
    "5. Igualdade de Gênero",
    "6. Água Potável e Saneamento",
    "7. Energia Limpa e Acessível",
    "8. Trabalho Decente e Crescimento Econômico",
    "9. Indústria, Inovação e Infraestrutura",
    "10. Redução das Desigualdades",
    "11. Cidades e Comunidades Sustentáveis",
    "12. Consumo e Produção Responsáveis",
    "13. Ação Contra a Mudança Global do Clima",
    "14. Vida na Água",
    "15. Vida Terrestre",
    "16. Paz, Justiça e Instituições Eficazes",
    "17. Parcerias e meios de implementação",
    "18. Promoção da Igualdade Étnico-Racial",
]
ODS_18 = [
    "18.1. Eliminar o racismo e a discriminação",
    "18.2. Eliminar violências",
    "18.3. Garantir tratamento justo",
    "18.4. Representatividade",
    "18.5. Promover a reparação",
    "18.6. Assegurar moradias adequadas",
    "18.7. Assegurar à saúde",
    "18.8. Assegurar educação de qualidade",
    "18.9. Promover o reconhecimento dos saberes",
    "18.10. Eliminar a xenofobia",
]

AREAS_MIR = [
    "Diretoria de Políticas de Ações Afirmativas (DPA)",
    "Diretoria de Políticas de Combate e Superação do Racismo (DCR)",
    "Diretoria de Articulação Interfederativa (DAI)",
    "Diretoria de Avaliação, Monitoramento e Gestão da Informação (DAMGI)",
    "Diretoria de Políticas para Povos e Comunidades Tradicionais de Matriz Africana e de Terreiros (DPMAT) / Diretoria de Políticas para Quilombolas e Ciganos (DQC)",
    "Assessoria Especial de Controle Interno (AECI)",
    "Consultoria Jurídica (CONJUR)",
    "Assessoria Internacional (AI)",
    "Assessoria Especial de Assuntos Parlamentares (ASPAR)",
    "Diretoria de Ações Governamentais (DAG)",
    "Diretoria de Gestão e Administração (DGA), incluídas as unidades CGAL e DIGEP",
    "Assessoria Especial do Ministro (AEM)",
    "Ouvidoria (OUV)",
    "Gabinete do Ministro (GAB. MIN.)",
    "Gabinete da Secretaria (GAB)",
    "Assessoria Especial de Comunicação Social (ASCOM)",
    "Secretaria-Executiva (SE)",
    "Corregedoria (CORREG)",
]

OBJETIVOS_ESTRATEGICOS = [
    "Objetivo Estratégico 1 - Finalístico: garantir a criação de oportunidades para a inclusão de pessoas negras, na educação, no mercado de trabalho público e privado, e na política institucional, através da elaboração e acompanhamento das políticas de ações afirmativas intersetoriais",
    "Objetivo Estratégico 2 - Finalístico: promover a igualdade de direitos para toda a população negra, em especial para a juventude negra, enfrentando e superando o racismo em todas as suas formas de expressão, através de políticas públicas intersetoriais e transversais",
    "Objetivo Estratégico 3 - Finalístico: promover estratégias e políticas transversais para a igualdade racial em todo o território nacional, articulando e apoiando órgãos municipais, estaduais e do distrito federal, por meio da reestruturação, ampliação, consolidação e aprofundamento do alcance do SINAPIR",
    "Objetivo Estratégico 4 - Finalístico: desenvolver e aperfeiçoar instrumentos de avaliação de políticas públicas, promovendo a produção, organização e divulgação de informações, dados e indicadores, por meio da qualificação da capacidade de formulação, execução, monitoramento e avaliação das políticas de igualdade racial",
    "Objetivo Estratégico 5 - Finalístico (DPMAT): assegurar direitos e superar o racismo religioso e a intolerância religiosa contra Povos e Comunidades Tradicionais de Matriz Africana e Povos de Terreiros, por meio de medidas intersetoriais de promoção da liberdade religiosa, respeito à ancestralidade, justiça social e climática, territorialidade e valorização do etnodesenvolvimento",
    "Objetivo Estratégico 6 - Finalístico (DQC): promover a melhoria da qualidade de vida, o bem viver, a paz e a justiça climática para comunidades quilombolas e povos ciganos, garantindo o acesso e a política de incentivo e proteção social, por meio do fortalecimento das estratégias de cuidado socioambiental das atuais e futuras gerações",
]

AGENDAS = [
    "Mulheres",
    "Povos Indígenas",
    "Crianças e Adolescentes",
    "Primeira Infância (subgrupo)",
    "Meio Ambiente",
]

SITUACAO_ENTREGA = ["Não iniciado", "Em andamento", "Concluído"]

ATIVIDADES = [
    "1. Evento (seminário/congresso, palestra, festival, feira literária)",
    "2. Formação e capacitação (curso, oficina, formação de gestores)",
    "3. Pesquisa e produção de conhecimento (projeto de pesquisa, estudo/diagnóstico, relatório técnico)",
    "4. Produção de materiais (material didático, cartilha/mobilização, audiovisual - documentário, podcast)",
    "5. Bolsas e fomento (bolsa, auxílio financeiro, fomento a iniciativas)",
    "6. Entrega de bens e serviços ao público",
    "7. Produção artística e cultural",
]

PERFIL_PUBLICO = [
    "Comunidade acadêmica e escolar - Estudantes da educação básica",
    "Comunidade acadêmica e escolar - Estudantes de graduação",
    "Comunidade acadêmica e escolar - Estudantes de pós-graduação",
    "Comunidade acadêmica e escolar - Pesquisadores(as) e docentes",
    "Comunidade acadêmica e escolar - Profissionais da educação (rede pública)",
    "Gestão pública e controle social - Gestores(as) e servidores(as) federais",
    "Gestão pública e controle social - Gestores(as) e servidores(as) estaduais e municipais (SINAPIR)",
    "Gestão pública e controle social - Conselheiros(as) e delegados(as) de conferências",
    "Gestão pública e controle social - Agentes locais de promoção da igualdade racial (Griô, APPSIR)",
    "Trabalho, renda e inclusão produtiva - Trabalhadores(as) formais e informais",
    "Trabalho, renda e inclusão produtiva - Catadores(as) de material reciclável",
    "Trabalho, renda e inclusão produtiva - Agricultores(as) familiares",
    "Trabalho, renda e inclusão produtiva - Empreendedores(as) e agentes de turismo",
    "Cultura, comunicação e esporte - Artistas e produtores(as) culturais",
    "Cultura, comunicação e esporte - Mestres(as) e detentores(as) de saberes tradicionais",
    "Cultura, comunicação e esporte - Comunicadores(as), influenciadores(as) e mídias negras",
    "Cultura, comunicação e esporte - Atletas e praticantes esportivos",
    "Sociedade civil e público amplo - Organizações da sociedade civil e movimento negro",
    "Sociedade civil e público amplo - Lideranças comunitárias",
    "Sociedade civil e público amplo - Público em geral (eventos abertos, publicações)",
]

FAIXAS_ETARIAS = [f"{i} a {i + 4}" for i in range(0, 100, 5)] + ["100+"]

GENERO = [
    "Mulheres cisgênero",
    "Homens cisgênero",
    "Mulheres trans",
    "Homens trans",
    "Pessoas não-binárias",
    "Agêneros",
    "Outro",
]

COR_RACA = ["Branca", "Amarela", "Parda", "Preta", "Indígena"]

GPTE = [
    "Grupos étnicos - Indígenas",
    "Grupos étnicos - Quilombolas",
    "Grupos étnicos - Comunidades de terreiro / matriz africana",
    "Grupos étnicos - Ciganos",
    "Meio ambiente - Extrativistas (quebradeiras de coco babaçu, catadores de mangaba, apanhadores de flores sempre-vivas, andirobeiros, cipozeiros, extrativistas costeiros e marinhos)",
    "Meio ambiente - Pescadores artesanais",
    "Meio ambiente - Ribeirinhos",
    "Meio rural - Assentados da reforma agrária",
    "Meio rural - Acampados",
    "Meio rural - Agricultores familiares",
    "Meio rural - Beneficiários do Programa Nacional de Crédito Fundiário",
    "Situações conjunturais - Atingidos por empreendimentos de infraestrutura",
    "Situações conjunturais - Famílias de presos do sistema carcerário",
    "Situações conjunturais - Catadores de material reciclável",
    "Situações conjunturais - Pessoas em situação de rua",
    "Situações conjunturais - Desalojados ou desabrigados",
]
OUTROS_PCTS = (
    "Outros PCTs (Decreto nº 6.040/2007), quando não enquadrados acima: caiçaras, caboclos, catingueiros, faxinalenses,"
    " fundo e fecho de pasto, geraiszeiros, ilhéus, marroquianos, pantaneiros, pomeranos, retireiros do Araguaia,"
    " vazanteiros, veredeiros, benzedeiros, raizeiros. Use a opção \"Outro\" para informá-los."
)

VULNERABILIDADES = [
    "Insegurança alimentar",
    "População em situação de rua",
    "Crianças e adolescentes",
    "Pessoas idosas",
    "Pessoas com deficiência",
    "Minorias étnicas, raciais e religiosas",
    "Comunidade LGBTQIAPN+",
    "Refugiados e imigrantes",
    "Mulheres",
]

REGIOES = ["Norte", "Nordeste", "Centro-Oeste", "Sudeste", "Sul"]
ABRANGENCIA = ["Municipal", "Estadual", "Regional", "Nacional", "Internacional"]

TERRITORIOS = [
    "Território quilombola",
    "Território de povos ciganos",
    "Comunidade de terreiro / matriz africana",
    "Periferia urbana com predominância negra",
    "Território rural",
    "Unidade prisional",
    "Escola pública",
    "Unidade de saúde pública",
    "Unidade da assistência social (CRAS/CREAS)",
    "Casa da Igualdade Racial",
]

TIPOS_EVIDENCIA = ["Foto", "Vídeo", "Lista de presença", "Ata", "Relatório de atividade", "Outro"]

# TEDs dos PDFs: a resposta guarda só o código do instrumento (nr_instrumento no DW); a descrição
# fica na pergunta "Descrição do instrumento". A sincronização com o DW traz os demais.
TEDS = [
    ("931295", "931295"),
    ("972366", "972366"),
    ("930522", "930522"),
    ("946708", "946708"),
    ("972575", "972575"),
    ("947942", "947942"),
    ("972712", "972712"),
    ("977697", "977697"),
    ("955619", "955619"),
    ("951425", "951425"),
    ("966020", "966020"),
    ("958214", "958214"),
]

NAO_HA_INFO = "Não há informações de relevância territorial a serem coletadas no período"


def bloco_3(f, instrumento, natureza, natureza_outro=True):
    """Bloco 3 é praticamente igual nos dois instrumentos."""
    f.bloco(
        "b3", f"Bloco 3 - Enquadramento programático ({'TED' if f.sigla == 'ted' else 'Convênio'})", "Enquadramento", "fa fa-sitemap",
        "<p>Preenchido pelo servidor do MIR, com base em análise do plano de trabalho. Requer subjetividade e conhecimento da administração pública.</p>",
    )
    f.secao("3.1 Vinculação ao PPA")
    f.p("lista", "Programa do PPA", f"Pergunta obrigatória. Para {instrumento} sem vinculação ao PPA, selecione \"Não se aplica\".", PPA + [("campo_dw", "instrumento.programa_ppa"), ("chave_dw", "ppa_programa")], req=True, chave="ppa")
    f.p("texto", "Objetivo Geral / Objetivos Estratégicos / Público alvo",
        "Preenchido a partir do Programa do PPA selecionado; pode ser complementado.",
        dw("ppa_programa.objetivo_geral"), dep=("ppa", PPA_COM_PROGRAMA))
    f.p("lista", "Objetivos Específicos", "Obrigatório quando houver Programa do PPA selecionado.", OBJETIVOS_ESPECIFICOS + [("fonte_dw", "ppa_objetivos_especificos")], req=True, dep=("ppa", PPA_COM_PROGRAMA))
    f.p("checkbox", "Entregas e medidas institucionais do PPA (opcional)",
        "Vinculação opcional às entregas e às medidas institucionais do Programa do PPA selecionado.",
        [("fonte_dw", "ppa_entregas_medidas")], dep=("ppa", PPA_COM_PROGRAMA))
    f.p("checkbox", "Relação com Agendas Transversais do PPA (opcional)",
        "Ex.: Agenda Transversal de Igualdade Racial. Consulte: gov.br/planejamento → Orçamento → Agendas Transversais e Multissetoriais.",
        AGENDAS + [f"Não se aplica / {instrumento} sem vinculação a agenda transversal"])

    f.secao("3.2 ODS vinculadas")
    f.p("lista", "ODS principal", "", ODS, chave="ods")
    f.p("lista", "ODS 18 - Igualdade Étnico-Racial (meta específica)",
        "Orientação: consulte as metas nacionais do ODS 18 na Portaria Conjunta MPI/MIR/SG-PR nº 198, de 24/09/2025.",
        ODS_18, dep=("ods", ODS[-1]))

    f.secao("3.3 Alinhamento ao Planejamento Estratégico Institucional", "Conforme Planejamento Estratégico Institucional - Portaria n.º 3 de janeiro de 2025.")
    f.p("checkbox", "Áreas do MIR", "Por extenso, conforme Decreto nº 11.346/2023.", AREAS_MIR + [("fonte_dw", "pei_areas")])
    f.p("checkbox", "Objetivos Estratégicos", "", OBJETIVOS_ESTRATEGICOS + [("fonte_dw", "pei_objetivos")])
    f.p("texto", "Indicador / Meta", "Indique os indicadores e metas vinculados ao(s) objetivo(s) estratégico(s) selecionado(s).")

    f.secao(f"3.4 Natureza do {instrumento}")
    f.p("checkbox", "Natureza do objeto", "Marcação múltipla permitida.", natureza, outro=natureza_outro)


def bloco_5_beneficiarios(f, instrumento):
    """Itens 5.3 e 5.4 são idênticos nos dois instrumentos."""
    f.secao("5.3 Beneficiários diretos")
    f.p("numero", "Quantitativo de beneficiários diretos", "", {"min": "0", "step": "1"})
    f.p("radio", "O quantitativo de beneficiários diretos é", "", ["Real", "Estimado"], chave="tipo_qtd")
    f.p("linha", "Fonte da estimativa de beneficiários diretos", "", dep=("tipo_qtd", "Estimado"))
    f.p("numero", "Quantitativo de beneficiários indiretos estimados", "", {"min": "0", "step": "1"})
    f.p("texto", "Memória de cálculo da estimativa",
        "Explique como chegou ao número - ex.: região atingida pela iniciativa, média de pessoas do grupo familiar, nº de pessoas constantes na instituição.")
    f.p("checkbox", "Perfil do público-alvo",
        "Perfil = papel social. O perfil captura em que condição a pessoa é atendida (estudante, gestor, trabalhador, artista); quem ela é será preenchido nos campos de cor/raça, GPTE, gênero e idade.",
        PERFIL_PUBLICO)
    f.p("distribuicao_numerica", "Faixa etária - quantidade de pessoas atendidas por faixa etária", "", FAIXAS_ETARIAS)
    f.p("checkbox", "Faixa etária - dado não coletado", f"Marque quando o {instrumento == 'TED' and 'gestor' or 'convenente'} não dispuser da informação.", ["Dado não coletado"])
    f.p("distribuicao_numerica", "Recorte de gênero - quantidade de pessoas atendidas por categoria", "", GENERO)
    f.p("linha", "Recorte de gênero - especifique a categoria \"Outro\"", "")
    f.p("linha", "Nacionalidade dos grupos beneficiados", "Informe uma ou mais nacionalidades, separadas por ponto e vírgula.")
    f.p("distribuicao_numerica", "Cor ou raça das pessoas beneficiadas", "Classificação IBGE - número absoluto por categoria.", COR_RACA)
    f.p("checkbox", "Povos e Comunidades Tradicionais e Grupos Específicos beneficiados",
        "Seleção principal conforme os 16 GPTEs do Cadastro Único (MDS). " + OUTROS_PCTS, GPTE, outro=True)
    f.p("checkbox", "Vulnerabilidades específicas", "", VULNERABILIDADES)
    f.p("checkbox", "Região(ões) atendida(s)", "", REGIOES)
    f.p("radio", "Abrangência geográfica", "", ABRANGENCIA, chave="abrangencia")
    f.p("municipio", "Município da execução",
        "Informe onde a atividade aconteceu de fato (base IBGE). O código IBGE do município é registrado automaticamente.",
        dep=("abrangencia", "Municipal"))

    f.secao("5.4 Territórios prioritários", "Marque os aplicáveis.")
    f.p("checkbox", "Territórios prioritários", "", TERRITORIOS, outro=True)
    f.p("linha", "Nome do território (se aplicável)", "Ex.: Quilombo Kalunga, Território Cigano de Sousa/PB.")


def evidencias(f, titulo_secao, dep=None):
    f.secao(titulo_secao,
            "Associe cada documento a um município, data e tipo de atividade. São aceitos arquivos e links: para vídeos, informe o link (URL).")
    f.p("radio", "Tipo de evidência", "", TIPOS_EVIDENCIA, dep=dep)
    f.p("linha", "Município / Local da evidência", "", dep=dep)
    f.p("linha", "Data da evidência", DATA, dep=dep)
    f.p("arquivo", "Arquivo da evidência", "", dep=dep)
    f.p("url", "Link da evidência (vídeos e arquivos online)", "", dep=dep)
    f.p("linha", "Descrição da evidência", "", dep=dep)


def documentos(f, categorias):
    f.p("radio", "Categoria do documento", "", categorias)
    f.p("linha", "Nome / Descrição do arquivo", "")
    f.p("linha", "Data do documento", DATA)
    f.p("arquivo", "Arquivo do documento", "")


INTRO_PERFIS = (
    "<p><strong>Perfis de preenchimento:</strong></p><ul>"
    "<li><strong>Blocos 1 a 3, 5.0 e 6:</strong> {mir}</li>"
    "<li><strong>Bloco 4 e projetos (5.1):</strong> {gestor} (atualização periódica), em formulário próprio ({sufixo_gestor})</li>"
    "<li><strong>Atividades e beneficiários (5.2 em diante):</strong> executor de cada projeto, pelo link enviado pelo {gestor_curto}, em formulário próprio (execução), um preenchimento por atividade</li></ul>"
    "<p>A sugestão de periodicidade de atualização é trimestral.</p>"
    "<p>Campos marcados como automáticos serão preenchidos a partir do Transferegov.br / SIAFI / Siorg quando a integração estiver disponível; até lá, preencha manualmente.</p>"
)

# ==========================================================================
# COLETA MIR - TEDs
# ==========================================================================
ted = Form(
    "ted", "Coleta MIR - TEDs (MIR)",
    "<p>Instrumento para coleta de dados de forma territorializada dos Termos de Execução Descentralizada (TEDs) celebrados pelo Ministério da Igualdade Racial (MIR).</p>"
    + INTRO_PERFIS.format(mir="servidor do MIR", gestor="gestor do TED na Unidade Descentralizada", sufixo_gestor="gestor", gestor_curto="gestor do TED"),
    10,
)

ted.bloco("b1", "Bloco 1 - Identificação do TED", "Identificação", "fa fa-id-card",
          "<p>Apresentação formal e institucional do TED.</p>")
ted.p("lista", "Número do TED", "Código identificador no Transferegov.br e Siorg. " + AUTO, TEDS + [("fonte_dw", "teds"), ("chave_dw", "instrumento"), ("resposta_unica", "sim")], req=True)
ted.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
ted.p("linha", "Número SEI", "Número do processo de formalização no SEI/MIR.", dw("instrumento.nr_processo_sei"))
ted.p("linha", "Processos SEI relacionados (se houver)",
      "Outros processos vinculados ao TED (produtos, atividades correlatas), para rastreabilidade e entendimento das entregas; separar por ponto e vírgula.")
ted.p("texto", "Objeto do TED", "Descrição resumida do objeto pactuado conforme Plano de Trabalho. " + AUTO, dw("instrumento.objeto"))
ted.p("checkbox", "Palavras-chave / Tags", "Até 5 termos temáticos para indexação.", TAGS, outro=True)
ted.p("numero", "Valor total (R$)", AUTO, moeda_dw("instrumento.vl_firmado"))
ted.p("numero", "Valor empenhado até a data de coleta (R$)", AUTO, moeda_dw("instrumento.vl_empenhado"))
ted.p("numero", "Valor liquidado (R$)", "Puxado do SIAFI. " + AUTO, moeda_dw("instrumento.vl_liquidado"))
ted.p("numero", "Valor pago (R$)", "Puxado do SIAFI. " + AUTO, moeda_dw("instrumento.vl_pago"))
ted.p("linha", "Data de início da vigência para execução", DATA, dw("instrumento.dt_inicio_vigencia"))
ted.p("linha", "Data de término", DATA, dw("instrumento.dt_fim_vigencia"))
ted.p("radio", "Existem termos aditivos?",
      "Orientação: termos aditivos não geram novo registro - são apensados ao registro do TED original, atualizando automaticamente valores e vigência.",
      SIM_NAO, chave="aditivo")
ted.p("linha", "Número e data do último aditivo", "", dep=("aditivo", "Sim"))
ted.p("linha", "Data de celebração (assinatura)", DATA, dw("instrumento.dt_assinatura"))

ted.bloco("b2", "Bloco 2 - Responsáveis (TED)", "Responsáveis", "fa fa-users")
ted.secao("2.1 Unidade do MIR")
ted.p("linha", "Unidade descentralizadora responsável (Secretaria)", "Secretaria ou unidade responsável no MIR. " + AUTO, dw("instrumento.unidade_mir"))
ted.p("linha", "Código da unidade descentralizadora (UG/SIAFI)", "Código da unidade gestora no SIAFI. " + AUTO, dw("instrumento.ug_mir"))
fiscais(ted, "Fiscais do TED")
ted.secao("2.3 Instrumento jurídico relacionado (se houver)")
ted.p("checkbox", "Tipo de instrumento complementar", "", ["ACT", "Convênio", "Chamada Pública / Edital"], outro=True)
ted.p("linha", "Número / Referência do instrumento complementar", "Se houver instrumento paralelo vinculado a este TED.")

bloco_3(ted, "TED", ["Pesquisa", "Extensão", "Ensino", "Desenvolvimento Institucional / Tecnológico", "Capacitação"])
ted.secao("3.5 Fonte de recursos")
ted.p("texto", "Programa orçamentário / Fonte de recursos",
      "Preenchido automaticamente a partir das Notas de Crédito e de Empenho do SIAFI vinculadas ao TED (fonte de recursos e natureza de despesa); incluir código da ação e UO.", dw("instrumento.programa_orcamentario"))
ted.secao("3.6 Justificativa da celebração")
ted.p("texto", "Justificativa", "Importada automaticamente do campo \"justificativa\" do Plano de Ação no Transferegov; o servidor pode complementar.", dw("instrumento.justificativa"))
ted.p("texto", "Instrumentos anteriores relacionados", "TEDs, convênios ou editais anteriores com o mesmo parceiro ou objeto similar.")


ted.bloco("b50", "Bloco 5.0 - Modalidade de resposta (TED)", "Modalidade", "fa fa-random",
         "<p>Definição, pelo servidor do MIR, de como o Bloco 5 será respondido pelo executor.</p>")
ted.secao("5.0 Modalidade de resposta desta seção",
          "Definição do gestor do MIR. Se territorializada, os itens 5.1 a 5.4 deverão ser preenchidos por executor/atividade; se exclusiva, a própria Unidade Descentralizada preenche os dados de forma consolidada, sem abertura por executor.")
ted.p("radio", "Modalidade de resposta", "", [
    "Resposta exclusiva pela Unidade Descentralizada (dados consolidados)",
    "Resposta territorializada pelos executores na ponta (um registro por executor/atividade)",
], req=True)

ted.bloco("b6", "Bloco 6 - Fiscalização e prestação de contas (TED)", "Fiscalização", "fa fa-search",
          "<p>Registro de alertas pelo Fiscal do TED ao longo da execução. Por dizer respeito ao controle interno, recomenda-se que opere como um módulo do MIR, separado dos campos preenchidos pelo executor.</p>")
ted.secao("6.1 Pontos de fiscalização", "A ser atualizado pelo servidor do MIR.")
ted.p("checkbox", "Pontos de fiscalização", "",
      ["Atraso na entrega de produtos", "Dificuldade de acesso a documentos", "Inconsistência financeira"], outro=True)
ted.p("texto", "Descrição e encaminhamentos dos pontos críticos", "Registre os alertas e as medidas tomadas ou recomendadas.")
ted.secao("6.2 Relatório de cumprimento do objeto (RCO)")
ted.p("arquivo", "Documento do RCO", "Anexar o arquivo do Relatório de Cumprimento do Objeto.")
ted.p("radio", "Tipo do RCO",
      "Indicar se o RCO encaminhado é parcial ou final. Parcerias de médio e longo prazo, por recomendação da AGU, devem ter relatórios parciais.",
      ["Parcial", "Final"])
ted.p("radio", "Status do RCO",
      "O status \"Em elaboração\" permite ao MIR acompanhar se o RCO está sendo produzido dentro do prazo de 120 dias após o encerramento do TED (Resolução TCU nº 344/2022).",
      ["Não iniciado", "Em elaboração", "Entregue ao MIR", "Aprovado pelo MIR"], chave="status_rco")
ted.p("linha", "Data de entrega ao MIR", DATA, dep=("status_rco", ["Entregue ao MIR", "Aprovado pelo MIR"]))
ted.aviso("Prazo do RCO", "<p>Atenção: o RCO deve ser entregue em até 120 dias após o encerramento do TED (Resolução TCU nº 344/2022).</p>", "warning")

ted_ges = Form(
    "ted_ges", "Coleta MIR - TEDs (gestor)",
    "<p>Atualização periódica da execução dos Termos de Execução Descentralizada (TEDs) celebrados pelo MIR.</p>"
    "<p><strong>Quem preenche:</strong> gestor do TED na Unidade Descentralizada: forma de execução, contrapartida e os projetos/ações apoiados, com seus responsáveis. "
    "Os Blocos 1 a 3 e 6 são preenchidos pelo servidor do MIR em formulário próprio.</p>"
    "<p>Ao enviar, cada projeto recebe um link para o formulário de execução, em que o executor registra as atividades e os beneficiários.</p>"
    "<p>A sugestão de periodicidade de atualização é trimestral.</p>",
    20,
)

ted_ges.bloco("b0", "Identificação do TED", "Identificação", "fa fa-id-card",
             "<p>Selecione o TED ao qual esta atualização se refere.</p>")
ted_ges.p("lista", "Número do TED", "Código identificador no Transferegov.br e Siorg.", TEDS + [("fonte_dw", "teds"), ("chave_dw", "instrumento")], req=True)
ted_ges.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
ted_ges.p("linha", "Período de referência da atualização", "Ex.: 3º trimestre de 2026.", req=True)
ted_ges.p("linha", "Responsável pelo preenchimento - nome completo", "", req=True)
ted_ges.p("linha", "Responsável pelo preenchimento - cargo", "", req=True)
ted_ges.p("telefone", "Responsável pelo preenchimento - telefone", "Com DDD.", req=True)
ted_ges.p("email", "Responsável pelo preenchimento - e-mail", "", req=True)

ted_ges.bloco("b4", "Bloco 4 - Execução (TED)", "Execução", "fa fa-tasks",
          "<p>Atualização periódica pela Unidade Descentralizada (gestor do TED no órgão que recebeu os recursos).</p>")
ted_ges.secao("4.1 Cronograma de execução")
ted_ges.p("radio", "O TED está sendo executado dentro do prazo previsto?", "", SIM_NAO, chave="prazo")
ted_ges.p("texto", "Justificativa de desvio", "Descreva atrasos, obstáculos e medidas adotadas.", dep=("prazo", "Não"))
ted_ges.secao("4.2 Forma de execução dos recursos",
          "Os mecanismos NÃO são excludentes: marque todos os que se aplicam. Fundação de apoio e NEAB têm funções distintas: registre cada uma no quadro de partícipes.")
ted_ges.p("checkbox", "Mecanismos utilizados", "Marque TODOS os que se aplicam.",
      ["Chamada pública / edital", "Concessão de bolsas e auxílios", "Repasse a subexecutores (NEABs, outras instituições)"], outro=True)
ted_ges.p("tabela", "Identificação do(s) subexecutor(es) / intermediário(s)",
         "Informe o nome e, se aplicável, o CNPJ e clique em \"Adicionar à lista\". Adicione quantos forem necessários.",
         colunas(("nome", "Nome", "linha", True), ("cnpj", "CNPJ", "cnpj")))
ted_ges.secao("4.3 Contrapartida prevista (se houver)")
ted_ges.p("radio", "Há contrapartida financeira?", "", SIM_NAO, chave="contrapartida")
ted_ges.p("texto", "Descrição da contrapartida", "Ex.: mão de obra, infraestrutura, espaço físico, insumos.", dep=("contrapartida", "Sim"))

ted_ges.bloco("b5", "Bloco 5 - Projetos e executores (TED)", "Projetos", "fa fa-sitemap",
          "<p>Atualização periódica pela Unidade Descentralizada (gestor do TED no órgão que recebeu os recursos).</p>")
ted_ges.aviso("Orientação - TEDs multi-objeto",
          "<p>Em TEDs multi-objeto, os beneficiários são informados por projeto/ação, e não somados no nível do TED, preservando a rastreabilidade de qual instituição beneficiou quais grupos, em que território e sobre qual tema.</p>")
TED_PARCEIRA = [
    "Sim, as atividades finalísticas do TED são executadas exclusivamente por organização parceira",
    "Não, as atividades finalísticas do TED são executadas exclusivamente pela unidade descentralizada",
    "Ambos, as atividades finalísticas são executadas tanto por parceiros à organização quanto pela unidade finalística",
]
ted_ges.secao("5.1 Execução por organização parceira")
ted_ges.p("radio", "Esta atividade é executada por uma organização parceira à sua?",
      "Por exemplo, sua organização é responsável pela abertura de editais para selecionar organizações para executarem atividades específicas - cursos, oficinas, elaboração de materiais, etc.",
      TED_PARCEIRA, req=True, chave="parceira")
ted_ges.p("tabela", "Projetos / ações apoiados e instituições executoras",
      "Adicione um item por projeto/ação apoiada, com o responsável que receberá o link de execução do projeto.",
      {**colunas(
          ("instituicao", "Instituição executora", "linha", True),
          ("cnpj", "CNPJ (quando se aplicar)", "cnpj"),
          ("tema", "Tema / objeto do projeto", "linha", True),
          ("posicao", "Posição na cadeia", "lista", True,
           ["Repassador (2ª ponta)", "Executor direto (3ª ponta)", "Subexecutor / fundação de apoio (4ª ponta)"]),
          ("valor", "Valor gasto (R$)", "moeda"),
          ("responsavel", "Responsável pelo projeto", "linha", True),
          ("email", "E-mail do responsável", "linha", True),
      ), "projetos_execucao": "execução pela própria Unidade Descentralizada",
         "propria_quando": json.dumps(TED_PARCEIRA[1:], ensure_ascii=False)},
      dep=("parceira", [TED_PARCEIRA[0], TED_PARCEIRA[2]]))
ted_ges.aviso("Links de execução",
              "<p>Ao enviar este formulário, cada projeto da lista recebe um link para o formulário de execução, a ser enviado ao responsável pelo projeto: "
              "nele são registradas as atividades executadas no período e os beneficiários alcançados, uma atividade por preenchimento. "
              "Quando a própria Unidade Descentralizada executa atividades, o registro é feito pelo botão \"Registrar atividades\".</p>")

ted_ex = Form(
    "ted_ex", "Coleta MIR - TEDs (execução)",
    "<p>Registro das atividades executadas e dos beneficiários alcançados nos Termos de Execução Descentralizada (TEDs) celebrados pelo MIR: um preenchimento por atividade.</p>"
    "<p><strong>Quem preenche:</strong> o responsável por cada projeto, pelo link enviado pelo gestor do TED, ou o próprio gestor, quando a Unidade Descentralizada executa diretamente. "
    "O TED, o período e o projeto vêm definidos pelo gestor.</p>",
    25,
    origem=ted_ges,
)

ted_ex.bloco("b0", "Identificação da atividade (TED)", "Identificação", "fa fa-id-card",
             "<p>TED, período e projeto definidos pelo gestor do TED.</p>")
ted_ex.p("lista", "Número do TED", "", TEDS + [("fonte_dw", "teds"), ("chave_dw", "instrumento"), ("origem", "pergunta:Número do TED")], req=True)
ted_ex.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
ted_ex.p("linha", "Período de referência da atualização", "", {"origem": "pergunta:Período de referência da atualização"}, req=True)
ted_ex.p("linha", "Instituição executora", "", {"origem": "projeto:instituicao", "origem_propria": "Unidade Descentralizada (execução direta)"}, req=True)
ted_ex.p("linha", "Projeto / ação", "Tema ou objeto do projeto.", {"origem": "projeto:tema"}, req=True)
ted_ex.p("linha", "Responsável pelo preenchimento - nome completo", "", req=True)
ted_ex.p("linha", "Responsável pelo preenchimento - cargo", "", req=True)
ted_ex.p("telefone", "Responsável pelo preenchimento - telefone", "Com DDD.", req=True)
ted_ex.p("email", "Responsável pelo preenchimento - e-mail", "", req=True)

ted_ex.bloco("b5", "Bloco 5 - Atividade e beneficiários (TED)", "Atividade", "fa fa-map-marker",
             "<p>Uma atividade por preenchimento: para registrar outra atividade do mesmo projeto, preencha o formulário de novo pelo mesmo link.</p>")
ted_ex.aviso("Mensagem ao usuário",
             "<p><strong>Neste momento você deve inserir uma atividade por vez que tenha sido executada no período a ser coletado.</strong></p>",
             "warning")
ted_ex.secao("5.2 Produtos previstos no plano de trabalho (pré-execução)",
          "Um produto do Plano de Trabalho pode se desdobrar em várias atividades: acompanhamos o produto (o que foi pactuado) e a atividade (o que aconteceu de fato).")
ted_ex.p("linha", "Produto previsto no Plano de Trabalho",
          "Escolha uma das metas/etapas do plano de trabalho do TED selecionado ou digite.",
          {"sugestoes_dw": "metas_instrumento", "filtro_dw": "teds"})
ted_ex.p("radio", "Situação da entrega", "", SITUACAO_ENTREGA)
ted_ex.p("numero", "% de execução", "", {"min": "0", "max": "100", "step": "1"})
ted_ex.p("texto", "Atividades realizadas", "")
ted_ex.p("radio", "Atividade a ser coletada", "", ATIVIDADES, outro=True)
bloco_5_beneficiarios(ted_ex, "TED")
evidencias(ted_ex, "5.5 Evidências de execução territorial (upload por atividade)")
ted_ex.secao("Dificuldades encontradas na execução", "Campo aberto - atualização periódica.")
ted_ex.p("texto", "Dificuldades burocráticas, acesso a documentos, mobilização de pessoas", "Registre dificuldades à medida que ocorrem, não apenas ao final.")
ted_ex.secao("Observações complementares e upload de documentos")
ted_ex.p("texto", "Observações complementares", "Informações adicionais relevantes não contempladas nos campos acima.")
documentos(ted_ex, [
    "Plano de Trabalho",
    "Relatório Parcial",
    "RCO (Relatório de Cumprimento do Objeto)",
    "Fotos de eventos/atividades",
    "Atas de reunião",
    "Listas de presença",
    "Vídeo",
    "Outro",
])
ted_ex.p("checkbox", "Relevância territorial no período", "", [NAO_HA_INFO])


# ==========================================================================
# COLETA MIR - CONVÊNIOS
# ==========================================================================
cv = Form(
    "conv", "Coleta MIR - Convênios (MIR)",
    "<p>Instrumento para coleta de dados de forma territorializada dos convênios celebrados pelo Ministério da Igualdade Racial (MIR), na qualidade de concedente.</p>"
    "<p>O Coleta MIR não substitui o registro dos atos de execução, acompanhamento e fiscalização, nem a prestação de contas, exigidos no Transferegov.br (Decreto nº 11.531/2023); "
    "seu propósito é complementar esses registros com informações territorializadas.</p>"
    + INTRO_PERFIS.format(mir="servidor do MIR (concedente)", gestor="convenente", sufixo_gestor="convenente", gestor_curto="convenente"),
    30,
)

cv.bloco("b1", "Bloco 1 - Identificação do convênio", "Identificação", "fa fa-id-card",
         "<p>Apresentação formal e institucional do convênio.</p>")
cv.p("lista", "Número do convênio",
     "Número do instrumento no Transferegov.br e código Siorg (se houver). " + AUTO,
     [("fonte_dw", "convenios"), ("chave_dw", "instrumento"), ("resposta_unica", "sim")], req=True)
cv.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
cv.p("radio", "Tipo de instrumento", "Conforme o Decreto nº 11.531/2023.", ["Convênio", "Contrato de repasse", ("campo_dw", "instrumento.tipo_instrumento")], chave="tipo_instr")
cv.p("linha", "Número SEI", "Número do processo de formalização no SEI/MIR.", dw("instrumento.nr_processo_sei"))
cv.p("linha", "Processos SEI relacionados (se houver)",
     "Outros processos vinculados ao convênio (plano de trabalho, termos aditivos, prestação de contas), para rastreabilidade e entendimento das entregas; separar por ponto e vírgula.")
cv.p("texto", "Objeto do convênio", "Descrição resumida do objeto pactuado conforme o plano de trabalho. " + AUTO, dw("instrumento.objeto"))
cv.p("checkbox", "Palavras-chave / Tags", "Até 5 termos temáticos para indexação.", TAGS, outro=True)
cv.p("numero", "Valor de repasse da União (R$)", "Recursos do concedente, conforme o termo de convênio. " + AUTO, moeda_dw("instrumento.valor_repasse"))
cv.p("numero", "Valor da contrapartida do convenente (R$)", "Financeira ou em bens e serviços economicamente mensuráveis (ver item 4.3). " + AUTO, moeda_dw("instrumento.valor_contrapartida"))
cv.p("numero", "Valor global do convênio (R$)", "Soma do repasse da União e da contrapartida. " + AUTO, moeda_dw("instrumento.vl_firmado"))
cv.p("numero", "Valor liberado / desembolsado até a data de coleta (R$)", "Puxado do Transferegov.br / SIAFI. " + AUTO, moeda_dw("instrumento.vl_pago"))
cv.p("linha", "Vigência - data de início", "Conforme o termo de convênio e seus aditivos. " + DATA, dw("instrumento.dt_inicio_vigencia"))
cv.p("linha", "Vigência - data de término", "Conforme o termo de convênio e seus aditivos. " + DATA, dw("instrumento.dt_fim_vigencia"))
cv.p("radio", "Existem termos aditivos?",
     "Orientação: termos aditivos não geram novo registro - são apensados ao registro do convênio original, atualizando automaticamente valores e vigência.",
     SIM_NAO + [("campo_dw", "instrumento.existem_aditivos")], chave="aditivo")
cv.p("linha", "Número e data do último aditivo", "", dep=("aditivo", "Sim"))
cv.p("linha", "Data de celebração (assinatura)", DATA, dw("instrumento.dt_assinatura"))
cv.p("linha", "Data de publicação do extrato", "Publicação do extrato do convênio no Diário Oficial da União. " + DATA, dw("instrumento.dt_publicacao"))

cv.bloco("b2", "Bloco 2 - Responsáveis (Convênio)", "Responsáveis", "fa fa-users")
cv.secao("2.1 Concedente (MIR)")
cv.p("linha", "Unidade concedente responsável (Secretaria)", "Secretaria ou unidade responsável no MIR. " + AUTO)
cv.p("linha", "Código da unidade concedente (UG/SIAFI)", "Código da unidade gestora no SIAFI. " + AUTO, dw("instrumento.ug_mir"))
fiscais(cv, "Fiscais do convênio")
cv.secao("2.2 Convenente e intervenientes")
cv.p("linha", "Convenente", "Denominação e CNPJ, conforme o cadastro no Transferegov.br. " + AUTO, dw("instrumento.parceiro"))
cv.p("radio", "Natureza do convenente", "Conforme o Decreto nº 11.531/2023.", [
    "Administração pública estadual",
    "Administração pública distrital",
    "Administração pública municipal",
    "Consórcio público",
    "Entidade privada sem fins lucrativos",
    ("campo_dw", "instrumento.natureza_convenente"),
])
cv.p("linha", "Dirigente do convenente", "Nome completo, cargo e e-mail institucional. " + AUTO)
cv.p("linha", "Responsável pela execução / coordenação do convênio",
     "Nome completo, cargo, telefone e e-mail. É a pessoa que preencherá os Blocos 4 e 5.")
cv.p("texto", "Interveniente(s) (se houver)",
     "Órgão ou entidade que participa do instrumento para manifestar consentimento ou assumir obrigações: nome e CNPJ.")
cv.p("linha", "Mandatária", "Instituição financeira oficial federal que opera o contrato de repasse.", dep=("tipo_instr", "Contrato de repasse"))
cv.secao("2.3 Instrumento jurídico relacionado (se houver)")
cv.p("checkbox", "Tipo de instrumento complementar", "", [
    "Acordo de cooperação técnica",
    "Acordo de adesão",
    "Chamada pública / edital",
    "Termo de fomento / termo de colaboração (Lei nº 13.019/2014)",
    "Contrato administrativo (Lei nº 14.133/2021)",
], outro=True)
cv.p("linha", "Número / Referência do instrumento complementar", "Se houver instrumento paralelo vinculado a este convênio.")

bloco_3(cv, "convênio", [
    "Pesquisa",
    "Extensão",
    "Ensino",
    "Desenvolvimento Institucional / Tecnológico",
    "Capacitação",
    "Fomento a iniciativas da sociedade civil",
    "Obras, reformas e serviços de engenharia",
    "Aquisição de bens",
    "Prestação de serviços",
    "Eventos",
])
EMENDAS = ["Emenda parlamentar individual", "Emenda parlamentar de bancada", "Emenda parlamentar de comissão"]
cv.secao("3.5 Origem e fonte dos recursos")
cv.p("checkbox", "Origem da programação orçamentária", "", EMENDAS + ["Programação própria do MIR", ("campo_dw", "instrumento.origem_programacao")], outro=True, chave="origem")
cv.p("linha", "Número da emenda e autoria", AUTO, dep=("origem", EMENDAS))
cv.p("texto", "Programa orçamentário / Fonte de recursos",
     "Preenchido automaticamente a partir das Notas de Crédito e de Empenho do SIAFI vinculadas ao convênio (fonte de recursos e natureza de despesa); incluir código da ação e UO.")
cv.secao("3.6 Justificativa da celebração")
cv.p("texto", "Justificativa", "Importada automaticamente do campo \"justificativa\" do plano de trabalho no Transferegov.br; o servidor pode complementar.")
cv.p("texto", "Instrumentos anteriores relacionados", "TEDs, convênios ou editais anteriores com o mesmo convenente ou objeto similar.")


cv.bloco("b50", "Bloco 5.0 - Modalidade de resposta (Convênio)", "Modalidade", "fa fa-random",
         "<p>Definição, pelo servidor do MIR, de como o Bloco 5 será respondido pelo executor.</p>")
cv.secao("5.0 Modalidade de resposta desta seção",
         "Definição do fiscal do convênio (servidor do MIR). Se territorializada, os itens 5.1 a 5.5 deverão ser preenchidos por executor/atividade; se consolidada, o próprio convenente preenche os dados de forma consolidada, sem abertura por executor.")
cv.p("radio", "Modalidade de resposta", "", [
    "Resposta consolidada pelo convenente (dados consolidados)",
    "Resposta territorializada por executor / atividade (um registro por executor/atividade)",
], req=True)

cv.bloco("b6", "Bloco 6 - Fiscalização e prestação de contas (Convênio)", "Fiscalização", "fa fa-search",
         "<p>Registro de alertas pelo fiscal do convênio ao longo da execução. Por dizer respeito ao controle interno, recomenda-se que opere como um módulo do MIR, separado dos campos preenchidos pelo convenente.</p>")
cv.secao("6.1 Acompanhamento e fiscalização", "A ser atualizado pelo servidor do MIR.")
cv.p("checkbox", "Pontos de fiscalização", "Marque todos os que se aplicam.", [
    "Atraso na execução de metas e etapas",
    "Dificuldade de acesso a documentos",
    "Inconsistência financeira",
    "Divergência em relação ao plano de trabalho",
], outro=True)
cv.p("radio", "Visita de acompanhamento realizada no período?", "", SIM_NAO, chave="visita")
cv.p("linha", "Data da visita de acompanhamento", DATA, dep=("visita", "Sim"))
cv.p("texto", "Descrição e encaminhamentos dos pontos críticos", "Registre os alertas e as medidas tomadas ou recomendadas.")
cv.secao("6.2 Prestação de contas")
cv.p("arquivo", "Documento da prestação de contas", "Anexar o arquivo ou informar o número do registro no Transferegov.br no campo seguinte.")
cv.p("linha", "Número do registro da prestação de contas no Transferegov.br", "")
cv.p("radio", "Tipo de prestação de contas",
     "Indicar se a prestação de contas registrada é parcial ou final. Convênios de médio e longo prazo podem ter prestações de contas parciais.",
     ["Parcial", "Final"])
cv.p("radio", "Situação da prestação de contas",
     "O status \"Em elaboração pelo convenente\" permite ao MIR acompanhar se a prestação de contas está sendo produzida dentro do prazo previsto no Decreto nº 11.531/2023.",
     [
         "Não iniciada",
         "Em elaboração pelo convenente",
         "Apresentada ao MIR",
         "Em análise pelo MIR",
         "Aprovada",
         "Aprovada com ressalvas",
         "Reprovada",
         "Em tomada de contas especial",
     ])
cv.p("linha", "Data de apresentação pelo convenente", DATA)
cv.p("radio", "Saldo remanescente devolvido?", "", ["Sim", "Não", "Não se aplica"], chave="saldo")
cv.p("linha", "Data de devolução do saldo remanescente", DATA, dep=("saldo", "Sim"))
cv.aviso("Prazo da prestação de contas",
         "<p>Atenção: a prestação de contas final deve ser apresentada no prazo de 60 dias, e os saldos remanescentes devolvidos em até 30 dias, contados do término da vigência do convênio (Decreto nº 11.531/2023).</p>",
         "warning")

cv_ges = Form(
    "conv_ges", "Coleta MIR - Convênios (convenente)",
    "<p>Atualização periódica da execução dos convênios celebrados pelo MIR, na qualidade de concedente.</p>"
    "<p><strong>Quem preenche:</strong> convenente: forma de execução, contrapartida, bens e os projetos/ações apoiados, com seus responsáveis. "
    "Os Blocos 1 a 3 e 6 são preenchidos pelo servidor do MIR em formulário próprio.</p>"
    "<p>Ao enviar, cada projeto recebe um link para o formulário de execução, em que o executor registra as atividades e os beneficiários.</p>"
    "<p>A sugestão de periodicidade de atualização é trimestral.</p>",
    40,
)

cv_ges.bloco("b0", "Identificação do convênio", "Identificação", "fa fa-id-card",
            "<p>Informe o convênio ao qual esta atualização se refere.</p>")
cv_ges.p("lista", "Número do convênio", "Número do instrumento no Transferegov.br.", [("fonte_dw", "convenios"), ("chave_dw", "instrumento")], req=True)
cv_ges.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
cv_ges.p("linha", "Período de referência da atualização", "Ex.: 3º trimestre de 2026.", req=True)
cv_ges.p("linha", "Responsável pelo preenchimento - nome completo", "", req=True)
cv_ges.p("linha", "Responsável pelo preenchimento - cargo", "", req=True)
cv_ges.p("telefone", "Responsável pelo preenchimento - telefone", "Com DDD.", req=True)
cv_ges.p("email", "Responsável pelo preenchimento - e-mail", "", req=True)

cv_ges.bloco("b4", "Bloco 4 - Execução (Convênio)", "Execução", "fa fa-tasks",
         "<p>Atualização periódica pelo convenente (responsável pela execução do convênio).</p>")
cv_ges.secao("4.1 Cronograma de execução")
cv_ges.p("radio", "O convênio está sendo executado dentro do cronograma físico e financeiro previsto no plano de trabalho?", "", SIM_NAO, chave="prazo")
cv_ges.p("texto", "Justificativa de desvio",
     "Descreva atrasos, obstáculos e medidas adotadas; indique se há proposta de termo aditivo (prorrogação de vigência ou alteração do plano de trabalho).",
     dep=("prazo", "Não"))
cv_ges.p("radio", "Situação da execução", "", [
    "Plano de trabalho aprovado, sem início da execução",
    "Em execução",
    "Execução concluída, em prestação de contas",
    "Suspensa",
    "Extinta / rescindida",
    ("campo_dw", "instrumento.situacao_execucao"),
])
cv_ges.secao("4.2 Forma de execução do objeto",
         "Os mecanismos NÃO são excludentes: marque todos os que se aplicam. Registre cada interveniente, executor ou contratado no quadro de partícipes (item 5.1).")
cv_ges.p("checkbox", "Mecanismos utilizados", "Marque TODOS os que se aplicam.", [
    "Execução direta pelo convenente",
    "Contratação de terceiros pelo convenente (Lei nº 14.133/2021)",
    "Chamada pública / edital",
    "Concessão de bolsas e auxílios",
    "Repasse a outras instituições (intervenientes, organizações da sociedade civil, outros entes)",
], outro=True)
cv_ges.p("tabela", "Identificação do(s) interveniente(s) / executor(es)",
        "Informe o nome e, se aplicável, o CNPJ e clique em \"Adicionar à lista\". Adicione quantos forem necessários.",
        colunas(("nome", "Nome", "linha", True), ("cnpj", "CNPJ", "cnpj")))
cv_ges.p("tabela", "Contratações realizadas pelo convenente para a execução do objeto (Lei nº 14.133/2021)",
        "Adicione um item por contratação.",
        colunas(
            ("contratado", "Contratado", "linha", True),
            ("cnpj", "CNPJ", "cnpj"),
            ("objeto", "Objeto da contratação", "linha", True),
            ("modalidade", "Modalidade", "lista", True,
             ["Pregão", "Concorrência", "Dispensa", "Inexigibilidade", "Outra"]),
            ("valor", "Valor (R$)", "moeda"),
        ))
cv_ges.secao("4.3 Contrapartida pactuada (se houver)")
cv_ges.p("radio", "Há contrapartida pactuada?", "", SIM_NAO + [("campo_dw", "instrumento.contrapartida_pactuada")], chave="contrapartida")
cv_ges.p("radio", "Modalidade da contrapartida", "", [
    "Financeira (depositada na conta bancária específica do convênio)",
    "Bens e serviços economicamente mensuráveis (entidades privadas sem fins lucrativos)",
], dep=("contrapartida", "Sim"))
cv_ges.p("numero", "Valor da contrapartida aportado até o período (R$)",
     "Contrapartida financeira: valor depositado. Bens e serviços: valor estimado.", MOEDA, dep=("contrapartida", "Sim"))
cv_ges.p("texto", "Descrição da contrapartida", "Ex.: mão de obra, infraestrutura, espaço físico, insumos.", dep=("contrapartida", "Sim"))
cv_ges.secao("4.4 Bens adquiridos com recursos do convênio")
cv_ges.p("radio", "Foram adquiridos bens permanentes com recursos do convênio no período (bens remanescentes)?", "", SIM_NAO, chave="bens")
cv_ges.p("texto", "Descrição e destinação dos bens", "Identifique os bens e a destinação prevista ao término da vigência.", dep=("bens", "Sim"))

cv_ges.bloco("b5", "Bloco 5 - Projetos e executores (Convênio)", "Projetos", "fa fa-sitemap",
         "<p>Atualização periódica pelo convenente (responsável pela execução do convênio).</p>")
cv_ges.aviso("Orientação - convênios com mais de uma meta",
         "<p>Em convênios com mais de uma meta, ação ou executor, os beneficiários são informados por meta/ação, e não somados no nível do convênio, preservando a rastreabilidade de qual instituição beneficiou quais grupos, em que território e sobre qual tema.</p>")
cv_ges.secao("5.1 Execução por organização distinta do convenente")
CV_TERCEIROS = [
    "Sim, as atividades finalísticas do convênio são executadas exclusivamente por terceiros (interveniente, contratado ou parceiro)",
    "Não, as atividades finalísticas do convênio são executadas exclusivamente pelo convenente",
    "Ambos, as atividades finalísticas são executadas tanto pelo convenente quanto por terceiros",
]
cv_ges.p("radio", "Esta atividade é executada por organização distinta do convenente?",
     "Por exemplo: o convenente contrata empresa, seleciona organizações por chamada pública ou repassa recursos a outra instituição para executar cursos, oficinas, obras, elaboração de materiais, etc.",
     CV_TERCEIROS, req=True, chave="terceiros")
cv_ges.p("tabela", "Projetos / ações apoiados e instituições executoras",
     "Adicione um item por projeto/ação apoiada, com o responsável que receberá o link de execução do projeto. Nota: o MIR (concedente) ocupa a 1ª ponta da cadeia de execução.",
     {**colunas(
         ("instituicao", "Instituição executora", "linha", True),
         ("cnpj", "CNPJ (quando se aplicar)", "cnpj"),
         ("tema", "Tema / objeto do projeto", "linha", True),
         ("posicao", "Posição na cadeia", "lista", True,
          ["Convenente (2ª ponta)", "Executor direto (3ª ponta)", "Subexecutor / contratado / parceiro (4ª ponta)"]),
         ("valor", "Valor gasto (R$)", "moeda"),
         ("responsavel", "Responsável pelo projeto", "linha", True),
         ("email", "E-mail do responsável", "linha", True),
     ), "projetos_execucao": "execução direta pelo convenente",
        "propria_quando": json.dumps(CV_TERCEIROS[1:], ensure_ascii=False)},
     dep=("terceiros", [CV_TERCEIROS[0], CV_TERCEIROS[2]]))
cv_ges.aviso("Links de execução",
             "<p>Ao enviar este formulário, cada projeto da lista recebe um link para o formulário de execução, a ser enviado ao responsável pelo projeto: "
             "nele são registradas as atividades executadas no período e os beneficiários alcançados, uma atividade por preenchimento. "
             "Quando o próprio convenente executa atividades, o registro é feito pelo botão \"Registrar atividades\".</p>")

cv_ex = Form(
    "conv_ex", "Coleta MIR - Convênios (execução)",
    "<p>Registro das atividades executadas e dos beneficiários alcançados nos convênios celebrados pelo MIR: um preenchimento por atividade.</p>"
    "<p><strong>Quem preenche:</strong> o responsável por cada projeto, pelo link enviado pelo convenente, ou o próprio convenente, quando executa diretamente. "
    "O convênio, o período e o projeto vêm definidos pelo convenente.</p>",
    45,
    origem=cv_ges,
)

cv_ex.bloco("b0", "Identificação da atividade (Convênio)", "Identificação", "fa fa-id-card",
            "<p>Convênio, período e projeto definidos pelo convenente.</p>")
cv_ex.p("lista", "Número do convênio", "", [("fonte_dw", "convenios"), ("chave_dw", "instrumento"), ("origem", "pergunta:Número do convênio")], req=True)
cv_ex.p("texto", "Descrição do instrumento", "Parceiro e objeto, preenchidos a partir do instrumento escolhido.", dw("instrumento.descricao", {"rows": "3"}))
cv_ex.p("linha", "Período de referência da atualização", "", {"origem": "pergunta:Período de referência da atualização"}, req=True)
cv_ex.p("linha", "Instituição executora", "", {"origem": "projeto:instituicao", "origem_propria": "Convenente (execução direta)"}, req=True)
cv_ex.p("linha", "Projeto / ação", "Tema ou objeto do projeto.", {"origem": "projeto:tema"}, req=True)
cv_ex.p("linha", "Responsável pelo preenchimento - nome completo", "", req=True)
cv_ex.p("linha", "Responsável pelo preenchimento - cargo", "", req=True)
cv_ex.p("telefone", "Responsável pelo preenchimento - telefone", "Com DDD.", req=True)
cv_ex.p("email", "Responsável pelo preenchimento - e-mail", "", req=True)

cv_ex.bloco("b5", "Bloco 5 - Atividade e beneficiários (Convênio)", "Atividade", "fa fa-map-marker",
            "<p>Uma atividade por preenchimento: para registrar outra atividade do mesmo projeto, preencha o formulário de novo pelo mesmo link.</p>")
cv_ex.aviso("Mensagem ao usuário",
            "<p><strong>Neste momento você deve inserir uma atividade por vez que tenha sido executada no período a ser coletado.</strong></p>",
            "warning")
cv_ex.secao("5.2 Metas e etapas previstas no plano de trabalho (pré-execução)",
         "Uma meta do plano de trabalho pode se desdobrar em várias atividades: acompanhamos a meta e a etapa (o que foi pactuado) e a atividade (o que aconteceu de fato).")
cv_ex.p("linha", "Meta / etapa prevista no plano de trabalho",
          "Escolha uma das metas do plano de trabalho do convênio selecionado ou digite.",
          {"sugestoes_dw": "metas_instrumento", "filtro_dw": "convenios"})
cv_ex.p("radio", "Situação da entrega", "", SITUACAO_ENTREGA)
cv_ex.p("numero", "% de execução", "", {"min": "0", "max": "100", "step": "1"})
cv_ex.p("texto", "Atividades realizadas", "")
cv_ex.p("radio", "Atividade a ser coletada", "",
     ATIVIDADES + ["8. Obras, reformas e serviços de engenharia (construção, reforma, adequação de espaços)"], outro=True)
bloco_5_beneficiarios(cv_ex, "convênio")
cv_ex.p("radio", "Relevância territorial no período", "", [
    "Há informações de relevância territorial a serem coletadas no período",
    NAO_HA_INFO,
], chave="relevancia")
evidencias(cv_ex, "5.5 Evidências de execução territorial (upload por atividade)",
           dep=("relevancia", "Há informações de relevância territorial a serem coletadas no período"))
cv_ex.secao("5.6 Dificuldades encontradas na execução", "Campo aberto - atualização periódica.")
cv_ex.p("texto", "Dificuldades burocráticas, acesso a documentos, mobilização de pessoas",
     "Registre dificuldades à medida que ocorrem, não apenas ao final, inclusive as relativas ao uso do Transferegov.br e ao relacionamento com o concedente.")
cv_ex.secao("5.7 Observações complementares e upload de documentos")
cv_ex.p("texto", "Observações complementares", "Informações adicionais relevantes não contempladas nos campos acima.")
documentos(cv_ex, [
    "Plano de trabalho",
    "Termo de convênio e termos aditivos",
    "Relatório parcial de execução",
    "Prestação de contas (parcial ou final)",
    "Fotos de eventos/atividades",
    "Atas de reunião",
    "Listas de presença",
    "Vídeo",
    "Outro",
])

# --------------------------------------------------------------------------
header = f"""-- Carga dos formulários Coleta MIR - TEDs e Coleta MIR - Convênios (com_edital).
-- Baseado nos documentos "2026.09.30 Coleta MIR TEDs - Versão final.pdf" e
-- "2026.09.30 Coleta MIR Convênios - Proposta.pdf".
--
-- Cria três editais do tipo 'formulario' por instrumento, conforme o perfil de preenchimento:
--   * (MIR): Blocos 1, 2, 3, 5.0 e 6 - servidor do MIR;
--   * (gestor / convenente): Bloco 4 e projetos (5.1) - gestor do TED ou convenente;
--   * (execução): atividades e beneficiários (5.2 em diante), um preenchimento por atividade, pelo link
--     de cada projeto. Ligado ao anterior por co_edital_adesao.
-- Cada bloco vira um agrupamento (aba) com suas perguntas. As dependências entre perguntas (depende_de) usam os
-- ids gerados nesta mesma execução. Prefixo de tabelas: {PREFIX}
--
-- Rodar uma única vez: cada execução cria novos registros.

SET NAMES utf8mb4;
START TRANSACTION;
"""
with open(OUT, "w", encoding="utf-8") as fh:
    fh.write(header + "\n".join(lines) + "\n\nCOMMIT;\n")

n = sum(1 for l in lines if l.startswith(f"INSERT INTO `{PREFIX}tb_pergunta`"))
print(f"perguntas: {n}")
