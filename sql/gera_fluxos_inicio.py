# Gera as imagens "Quem preenche o quê" e "Como começar" da página inicial. O layout segue os
# slides do Gov Hub (gov-hub-docs/apresentacoes: cards, sobretítulo em caixa alta, passos lado
# a lado); cores, fonte, pesos, cantos e bordas copiam o topo da página (media/com_edital/css/inicio.css):
# destaque com o degradê do card azul, cards brancos com a borda azul dos botões de ação.
# A Rawline vai embutida no SVG, porque SVG usado como <img> não carrega fonte externa, e
# recortada só com os caracteres usados (precisa de fonttools: pip install fonttools).
# Uso: python3 sql/gera_fluxos_inicio.py
import base64, html, io, os

from fontTools import subset
from fontTools.ttLib import TTFont

RAIZ = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'data', 'www', 'media', 'com_edital')
SAIDA = os.path.join(RAIZ, 'images', 'inicio')

# Estilos do topo da página. A imagem aparece com 1110px de largura (1500 na imagem), então
# cada px da página vale K unidades aqui.
K = 1500 / 1110
AZUL, ESCURO, CINZA, CLARO = '#1351B4', '#0C326F', '#555555', '#DBE8FB'
RAIO, BORDA = round(12 * K), round(2 * K, 1)
SOBRE = round(12.8 * K)           # .coleta-inicio-sobretitulo: 0.8rem, 400, caixa alta, .08em, opacidade .85
TITULO = round(30 * K)            # títulos 700, como o h1 do card azul
TEXTO = round(18.4 * K)           # .coleta-inicio-lead: 1.15rem, 400, linha 1.5
ACAO, ACAO_SUB = round(20.8 * K), round(14.4 * K)   # .coleta-inicio-acao strong / small


def fonte_recortada(peso, caracteres):
    fonte = TTFont(os.path.join(RAIZ, 'fonts', 'rawline-%d.woff' % peso))
    opcoes = subset.Options()
    opcoes.flavor = 'woff'
    recorte = subset.Subsetter(opcoes)
    recorte.populate(text=caracteres)
    recorte.subset(fonte)
    saida = io.BytesIO()
    fonte.flavor = 'woff'
    fonte.save(saida)
    return base64.b64encode(saida.getvalue()).decode()


def e(t):
    return html.escape(t, quote=True)


class Svg:
    def __init__(self, w, h, titulo, descricao):
        self.w, self.h = w, h
        self.p = [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">' % (w, h, w, h),
            '<title>%s</title><desc>%s</desc>' % (e(titulo), e(descricao)),
            # linear-gradient(135deg, #0c326f, #1351b4) do .coleta-inicio-hero
            '<defs><linearGradient id="hero" x1="0" y1="0" x2="1" y2="1">'
            '<stop offset="0" stop-color="%s"/><stop offset="1" stop-color="%s"/></linearGradient></defs>' % (ESCURO, AZUL),
        ]
        self.textos = []

    def texto(self, x, y, t, tam, cor, peso=400, anchor='start', espaco=0, opacidade=1):
        self.textos.append((peso, t))
        extra = (' letter-spacing="%s"' % espaco if espaco else '') + (' opacity="%s"' % opacidade if opacidade != 1 else '')
        self.p.append('<text x="%s" y="%s" font-size="%s" font-weight="%s" fill="%s" text-anchor="%s"%s>%s</text>'
                      % (x, y, tam, peso, cor, anchor, extra, e(t)))

    def sobre(self, x, y, t, cor):
        self.texto(x, y, t.upper(), SOBRE, cor, 400, espaco=round(SOBRE * 0.08, 2), opacidade=0.85)

    def linhas(self, x, y, ls, tam, cor, peso=400, altura=1.5):
        for i, t in enumerate(ls):
            self.texto(x, round(y + i * tam * altura, 1), t, tam, cor, peso)
        return y + len(ls) * tam * altura

    def card(self, x, y, w, h, estilo):
        """estilo: 'hero' (degradê do card azul), 'cheio' (botão Entrar) ou 'borda' (botão Solicitar acesso)."""
        if estilo == 'borda':
            m = BORDA / 2
            self.p.append('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="#FFFFFF" stroke="%s" stroke-width="%s"/>'
                          % (x + m, y + m, w - BORDA, h - BORDA, RAIO, AZUL, BORDA))
        else:
            self.p.append('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"/>'
                          % (x, y, w, h, RAIO, 'url(#hero)' if estilo == 'hero' else AZUL))

    def chip(self, x, y, t, fundo, cor):
        tam = round(14.4 * K)
        w = len(t) * tam * 0.56 + 32
        self.p.append('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"/>' % (x, y, round(w), round(tam * 2.1), round(8 * K), fundo))
        self.texto(round(x + w / 2), round(y + tam * 1.42), t, tam, cor, 700, 'middle')
        return w

    def seta(self, x1, x2, y, cor=AZUL):
        self.p.append('<path d="M%s %s H%s" stroke="%s" stroke-width="5" stroke-linecap="round" fill="none"/>' % (x1, y, x2 - 14, cor))
        self.p.append('<path d="M%s %s L%s %s L%s %s Z" fill="%s"/>' % (x2 - 18, y - 12, x2, y, x2 - 18, y + 12, cor))

    def salvar(self, nome):
        regras = []
        for peso in (400, 700):
            usados = ''.join(t for p, t in self.textos if p == peso)
            if usados:
                regras.append("@font-face{font-family:'Rawline';font-weight:%d;src:url(data:font/woff;base64,%s) format('woff');}"
                              % (peso, fonte_recortada(peso, usados)))
        self.p.insert(2, "<style>%s text{font-family:'Rawline',Arial,sans-serif;}</style>" % ''.join(regras))
        self.p.append('</svg>')
        open(os.path.join(SAIDA, nome), 'w', encoding='utf-8').write('\n'.join(self.p))


# Quem preenche o quê: três cards; o do gestor com o degradê do card azul e uma seta dele para o responsável.
CARDS = [
    ('Quem cadastra', ['Servidor', 'do MIR'], ['Cadastra o TED ou o', 'convênio e os dados', 'de acompanhamento.'], 'Novo instrumento', 'borda'),
    ('Quem acompanha', ['Gestor de TED', 'ou convenente'], ['Conta como o instrumento', 'é executado e indica', 'os projetos apoiados.'], 'UG ou CNPJ liberado', 'hero'),
    ('Quem executa', ['Responsável', 'pelo projeto'], ['Registra as atividades', 'e as pessoas', 'beneficiadas.'], 'Entra pelo CPF', 'borda'),
]
s = Svg(1500, 460, 'Quem preenche o quê',
        'Servidor do MIR cadastra o TED ou o convênio. Gestor de TED ou convenente, com a UG ou o CNPJ liberado, conta como o instrumento é executado e indica os projetos. Responsável pelo projeto entra pelo CPF e registra as atividades e as pessoas beneficiadas.')
x = 0
for i, (k, titulo, desc, chip, estilo) in enumerate(CARDS):
    w = 440
    claro = estilo == 'hero'
    s.card(x, 0, w, 460, estilo)
    s.sobre(x + 44, 64, k, '#FFFFFF' if claro else AZUL)
    y = s.linhas(x + 44, 64 + TITULO * 1.6, titulo, TITULO, '#FFFFFF' if claro else AZUL, 700, 1.2)
    s.linhas(x + 44, y + 22, desc, TEXTO, '#FFFFFF' if claro else CINZA)
    s.chip(x + 44, 372, chip, '#FFFFFF' if claro else AZUL, AZUL if claro else '#FFFFFF')
    if i == 1:
        s.seta(x + w + 22, x + w + 98, 230)
        s.texto(x + w + 60, 280, 'pelo', SOBRE, AZUL, 700, 'middle')
        s.texto(x + w + 60, 280 + SOBRE * 1.3, 'CPF', SOBRE, AZUL, 700, 'middle')
    x += w + (40 if i == 0 else 120)
s.salvar('quem-preenche.svg')

# Como começar: passos lado a lado, nos estilos dos botões de ação e do card azul.
PASSOS = [
    ('Passo 1', 'Crie sua conta', 'CPF, e-mail e senha', 'borda'),
    ('Passo 2', 'Peça seu perfil', 'UG, CNPJ ou MIR', 'borda'),
    ('Passo 3', 'O MIR libera', 'veja em Meus acessos', 'cheio'),
    ('Passo 4', 'Preencha', 'em Meus instrumentos', 'hero'),
]
s = Svg(1500, 150, 'Como começar',
        'Passo 1: crie sua conta com CPF, e-mail e senha. Passo 2: peça seu perfil informando a UG, o CNPJ ou o perfil MIR. Passo 3: o MIR libera, e você acompanha em Meus acessos. Passo 4: preencha em Meus instrumentos.')
w, gap = 315, 80
for i, (k, t, d, estilo) in enumerate(PASSOS):
    x = i * (w + gap)
    claro = estilo != 'borda'
    s.card(x, 0, w, 150, estilo)
    s.sobre(x + 36, 44, k, '#FFFFFF' if claro else AZUL)
    s.texto(x + 36, 44 + ACAO * 1.5, t, ACAO, '#FFFFFF' if claro else AZUL, 700)
    s.texto(x + 36, 44 + ACAO * 1.5 + ACAO_SUB * 1.9, d, ACAO_SUB, CLARO if claro else CINZA, 400)
    if i < len(PASSOS) - 1:
        s.seta(x + w + 14, x + w + gap - 14, 75)
s.salvar('como-comecar.svg')
