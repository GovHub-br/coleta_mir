# Gera as imagens "Quem preenche o quê" e "Como começar" da página inicial, no visual dos
# slides do Gov Hub (gov-hub-docs/apresentacoes): cards #F2F1F6 arredondados, roxo #613EFF,
# azul-marinho #0A005A, sobretítulo em caixa alta e Reddit Sans (embutida no SVG, porque
# SVG usado como <img> não carrega fonte externa).
# Uso: python3 sql/gera_fluxos_inicio.py
import base64, html, os

RAIZ = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'data', 'www', 'media', 'com_edital')
SAIDA = os.path.join(RAIZ, 'images', 'inicio')

ROXO, MARINHO, ROXO700, SUAVE, TEXTO, MUTED = '#613EFF', '#0A005A', '#3F28A6', '#F2F1F6', '#2D3748', '#666666'


def fontes():
    regras = []
    for peso in (500, 700, 800):
        dados = base64.b64encode(open(os.path.join(RAIZ, 'fonts', 'reddit-%d.woff2' % peso), 'rb').read()).decode()
        regras.append("@font-face{font-family:'Reddit Sans';font-weight:%d;src:url(data:font/woff2;base64,%s) format('woff2');}" % (peso, dados))
    return ''.join(regras)


def e(t):
    return html.escape(t, quote=True)


class Svg:
    def __init__(self, w, h, titulo, descricao):
        self.w, self.h = w, h
        self.p = [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">' % (w, h, w, h),
            '<title>%s</title><desc>%s</desc>' % (e(titulo), e(descricao)),
            "<style>%s text{font-family:'Reddit Sans','Open Sans',Arial,sans-serif;}</style>" % fontes(),
        ]

    def texto(self, x, y, t, tam, cor, peso=500, anchor='start', espaco=0):
        ls = ' letter-spacing="%s"' % espaco if espaco else ''
        self.p.append('<text x="%s" y="%s" font-size="%s" font-weight="%s" fill="%s" text-anchor="%s"%s>%s</text>'
                      % (x, y, tam, peso, cor, anchor, ls, e(t)))

    def linhas(self, x, y, ls, tam, cor, peso=500, altura=1.25, anchor='start'):
        for i, t in enumerate(ls):
            self.texto(x, round(y + i * tam * altura, 1), t, tam, cor, peso, anchor)
        return y + len(ls) * tam * altura

    def ret(self, x, y, w, h, r, fill, extra=''):
        self.p.append('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s" %s/>' % (x, y, w, h, r, fill, extra))

    def chip(self, x, y, t, fundo, cor, tam=17):
        w = len(t) * tam * 0.56 + 36
        self.ret(x, y, round(w), 40, 20, fundo)
        self.texto(round(x + w / 2), y + 26, t, tam, cor, 700, 'middle')
        return w

    def seta(self, x1, x2, y, cor=ROXO):
        self.p.append('<path d="M%s %s H%s" stroke="%s" stroke-width="6" stroke-linecap="round" fill="none"/>' % (x1, y, x2 - 14, cor))
        self.p.append('<path d="M%s %s L%s %s L%s %s Z" fill="%s"/>' % (x2 - 20, y - 14, x2, y, x2 - 20, y + 14, cor))

    def salvar(self, nome):
        self.p.append('</svg>')
        open(os.path.join(SAIDA, nome), 'w', encoding='utf-8').write('\n'.join(self.p))


# Quem preenche o quê: três cards; o do gestor em destaque e uma seta dele para o responsável.
CARDS = [
    ('Quem cadastra', ['Servidor', 'do MIR'], ['Cadastra o TED ou o', 'convênio e os dados', 'de acompanhamento.'], ['Novo instrumento'], False),
    ('Quem acompanha', ['Gestor de TED', 'ou convenente'], ['Conta como o instrumento', 'é executado e indica', 'os projetos apoiados.'], ['UG ou CNPJ liberado'], True),
    ('Quem executa', ['Responsável', 'pelo projeto'], ['Registra as atividades', 'e as pessoas', 'beneficiadas.'], ['Entra pelo CPF'], False),
]
s = Svg(1500, 470, 'Quem preenche o quê',
        'Servidor do MIR cadastra o TED ou o convênio. Gestor de TED ou convenente, com a UG ou o CNPJ liberado, conta como o instrumento é executado e indica os projetos. Responsável pelo projeto entra pelo CPF e registra as atividades e as pessoas beneficiadas.')
x = 0
for i, (k, titulo, desc, chips, destaque) in enumerate(CARDS):
    w = 440
    fundo, cor_t, cor_d, cor_k = (ROXO, '#FFFFFF', '#FFFFFF', '#E9DFFF') if destaque else (SUAVE, MARINHO, TEXTO, ROXO700)
    s.ret(x, 10, w, 450, 36, fundo)
    s.texto(x + 44, 74, k.upper(), 17, cor_k, 700, espaco=3)
    y = s.linhas(x + 44, 136, titulo, 46, cor_t, 800, 1.05)
    s.linhas(x + 44, y + 34, desc, 24, cor_d, 500, 1.35)
    cx = x + 44
    for c in chips:
        cx += s.chip(cx, 376, c, '#FFFFFF' if destaque else ROXO, ROXO if destaque else '#FFFFFF') + 10
    if i == 1:
        s.seta(x + w + 22, x + w + 98, 235)
        s.texto(x + w + 60, 290, 'pelo', 16, ROXO700, 700, 'middle')
        s.texto(x + w + 60, 310, 'CPF', 16, ROXO700, 700, 'middle')
    x += w + (40 if i == 0 else 120)
s.salvar('quem-preenche.svg')

# Como começar: passos em pílula, como o gh-flow dos slides, e a exceção do responsável.
PASSOS = [
    ('Passo 1', 'Crie sua conta', 'CPF, e-mail e senha', SUAVE, MARINHO, ROXO700),
    ('Passo 2', 'Peça seu perfil', 'UG, CNPJ ou MIR', SUAVE, MARINHO, ROXO700),
    ('Passo 3', 'O MIR libera', 'veja em Meus acessos', ROXO, '#FFFFFF', '#E9DFFF'),
    ('Passo 4', 'Preencha', 'em Meus instrumentos', MARINHO, '#FFFFFF', '#C9C2FF'),
]
s = Svg(1500, 330, 'Como começar',
        'Passo 1: crie sua conta com CPF, e-mail e senha. Passo 2: peça seu perfil informando a UG, o CNPJ ou o perfil MIR. Passo 3: o MIR libera, e você acompanha em Meus acessos. Passo 4: preencha em Meus instrumentos. Responsável por projeto pula os passos 2 e 3, porque o projeto aparece pelo CPF.')
w, gap = 315, 80
for i, (k, t, d, fundo, cor_t, cor_k) in enumerate(PASSOS):
    x = i * (w + gap)
    s.ret(x, 10, w, 150, 75, fundo)
    s.texto(x + 48, 58, k.upper(), 16, cor_k, 700, espaco=3)
    s.texto(x + 48, 100, t, 34, cor_t, 800)
    s.texto(x + 48, 134, d, 20, cor_t if fundo != SUAVE else TEXTO, 500)
    if i < len(PASSOS) - 1:
        s.seta(x + w + 14, x + w + gap - 14, 85)
# exceção: pílula tracejada sob os passos 2 e 3, como o gh-flow__step--back
x0, x1 = w + gap, 3 * (w + gap) - gap
s.ret(x0, 210, x1 - x0, 96, 48, '#FFFFFF', 'stroke="%s" stroke-width="3" stroke-dasharray="10 8"' % ROXO700)
s.texto((x0 + x1) / 2, 250, 'Responsável por projeto pula os passos 2 e 3', 24, MARINHO, 800, 'middle')
s.texto((x0 + x1) / 2, 282, 'o projeto aparece em Meus instrumentos pelo CPF', 20, TEXTO, 500, 'middle')
s.salvar('como-comecar.svg')
