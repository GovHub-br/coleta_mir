import sys, html
# Gera os fluxos da página inicial (raias por ator, paleta GovHub).
# Uso: python3 sql/gera_fluxos_inicio.py data/www/media/com_edital/images/inicio
OUT = sys.argv[1]
F = "font-family=\"Inter, Raleway, 'Segoe UI', Arial, sans-serif\""
MIR, PESSOA, GESTOR, RESP, DEC = '#5B21B6', '#7A34F3', '#9520B6', '#F97316', '#C520A4'
LIGHT = {MIR: '#EDE7F8', PESSOA: '#F0E8FE', GESTOR: '#F3E6F8', RESP: '#FEEEE2'}
DARK = {MIR: '#3B1578', PESSOA: '#4E1FA0', GESTOR: '#5E1473', RESP: '#9A4A0E'}

def e(s): return html.escape(s, quote=True)

class D:
    def __init__(s, w, h, titulo, desc):
        s.w, s.h, s.o = w, h, []
        s.o.append(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" role="img" {F}>')
        s.o.append(f'<title>{e(titulo)}</title><desc>{e(desc)}</desc>')
        s.o.append('<defs><marker id="seta" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M1 1L9 5L1 9z" fill="context-stroke"/></marker></defs>')
        s.o.append(f'<rect width="{w}" height="{h}" fill="#FFFFFF"/>')
    def raw(s, x): s.o.append(x)
    def text(s, x, y, t, size=12, cor='#202020', peso=400, anchor='middle'):
        s.o.append(f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{peso}" fill="{cor}" text-anchor="{anchor}" dominant-baseline="central">{e(t)}</text>')
    def raia(s, y, h, nome, cor, linhas=None):
        s.o.append(f'<rect x="20" y="{y}" width="{s.w-40}" height="{h}" rx="10" fill="{LIGHT[cor]}" opacity="0.55"/>')
        s.o.append(f'<rect x="20" y="{y}" width="160" height="{h}" rx="10" fill="{cor}"/>')
        ls = linhas or [nome]
        y0 = y + h/2 - (len(ls)-1)*9
        for i, l in enumerate(ls):
            s.text(100, y0 + i*18, l, 14, '#FFFFFF', 700)
    def etapa(s, x, y, w, h, linhas, cor):
        s.o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" fill="{cor}"/>')
        y0 = y + h/2 - (len(linhas)-1)*9
        for i, l in enumerate(linhas):
            s.text(x+w/2, y0+i*18, l, 14 if i == 0 else 12, '#FFFFFF', 700 if i == 0 else 500)
    def circulo(s, cx, cy, cor, rot, lado='baixo'):
        s.o.append(f'<circle cx="{cx}" cy="{cy}" r="14" fill="{cor}"/>')
        if lado == 'baixo': s.text(cx, cy+26, rot, 12, '#202020', 600)
        else: s.text(cx-22, cy, rot, 12, '#202020', 600, 'end')
    def etiqueta(s, x, y, w, t, cor):
        s.o.append(f'<rect x="{x}" y="{y}" width="{w}" height="22" rx="11" fill="#FFFFFF" stroke="{cor}" stroke-width="1"/>')
        s.text(x+w/2, y+11, t, 12, DARK[cor], 600)
    def obs(s, x, y, w, linhas, borda='#9CA3AF', icone='i'):
        h = 14 + 16*len(linhas)
        s.o.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="6" fill="#F8F9FA" stroke="{borda}" stroke-width="1" stroke-dasharray="{"0" if borda != "#9CA3AF" else "4 3"}"/>')
        s.o.append(f'<circle cx="{x+14}" cy="{y+15}" r="8" fill="{borda}"/>')
        s.text(x+14, y+15, icone, 11, '#FFFFFF', 700)
        for i, l in enumerate(linhas):
            s.text(x+28, y+15+i*16, l, 12, '#202020', 400, 'start')
    def decisao(s, cx, cy, hw, hh, linhas):
        s.o.append(f'<polygon points="{cx},{cy-hh} {cx+hw},{cy} {cx},{cy+hh} {cx-hw},{cy}" fill="{DEC}"/>')
        y0 = cy - (len(linhas)-1)*8
        for i, l in enumerate(linhas): s.text(cx, y0+i*16, l, 12, '#FFFFFF', 700)
    def seta(s, d, cor='#4B5563'):
        s.o.append(f'<path d="{d}" fill="none" stroke="{cor}" stroke-width="1.5" marker-end="url(#seta)"/>')
    def linha(s, d, cor='#4B5563'):
        s.o.append(f'<path d="{d}" fill="none" stroke="{cor}" stroke-width="1.5"/>')
    def legenda(s, itens, y=24):
        x = 20
        s.text(x, y+12, 'Legenda', 12, '#202020', 700, 'start'); x += 70
        for tipo, cor, rot in itens:
            if tipo == 'circ':
                s.o.append(f'<circle cx="{x+9}" cy="{y+12}" r="8" fill="{cor}"/>'); x += 24
            elif tipo == 'etapa':
                s.o.append(f'<rect x="{x}" y="{y+3}" width="26" height="18" rx="4" fill="{cor}"/>'); x += 34
            elif tipo == 'dec':
                s.o.append(f'<polygon points="{x+13},{y+1} {x+26},{y+12} {x+13},{y+23} {x},{y+12}" fill="{DEC}"/>'); x += 34
            elif tipo == 'etq':
                s.o.append(f'<rect x="{x}" y="{y+3}" width="34" height="18" rx="9" fill="#FFF" stroke="#6B7280"/>'); x += 42
            elif tipo == 'obs':
                s.o.append(f'<rect x="{x}" y="{y+3}" width="30" height="18" rx="4" fill="#F8F9FA" stroke="{cor}" stroke-dasharray="{"4 3" if cor == "#9CA3AF" else "0"}"/>'); x += 38
            s.text(x, y+12, rot, 12, '#374151', 400, 'start'); x += len(rot)*6.6 + 22
    def salvar(s, nome):
        s.o.append('</svg>')
        open(f'{OUT}/{nome}', 'w', encoding='utf-8').write('\n'.join(s.o))

# ---------- 1. Quem preenche o quê ----------
d = D(1200, 640, 'Quem preenche o quê no Coleta MIR',
      'Servidor do MIR cadastra o instrumento. Gestor de TED ou convenente abre o instrumento pela UG ou CNPJ, informa execução e contrapartida e lista os projetos com o CPF do responsável, ou registra as atividades quando a própria unidade executa. O responsável pelo projeto entra com o CPF, abre o projeto e registra cada atividade e os beneficiários.')
d.legenda([('circ', '#6B7280', 'Início e fim'), ('etapa', MIR, 'Etapa (cor do ator)'), ('dec', DEC, 'Decisão'),
           ('etq', None, 'Tela ou formulário'), ('obs', '#9CA3AF', 'Observação'), ('obs', DEC, 'Dado pessoal (LGPD)')])
# Raia MIR
d.raia(80, 170, '', MIR, ['Servidor', 'do MIR'])
d.circulo(222, 150, MIR, 'Início'); d.seta('M236 150 H286')
d.etapa(290, 125, 210, 50, ['Abrir "Novo instrumento"'], MIR); d.seta('M500 150 H546')
d.etapa(550, 122, 260, 56, ['Preencher identificação,', 'responsáveis e fiscalização'], MIR)
d.etiqueta(600, 186, 160, 'Formulário do MIR', MIR); d.seta('M810 150 H852')
d.circulo(870, 150, MIR, 'Fim')
d.obs(290, 186, 240, ['Número e descrição do instrumento', 'vêm da base do MIR'])
# Raia gestor
d.raia(260, 190, '', GESTOR, ['Gestor de TED', 'ou convenente'])
d.circulo(222, 315, GESTOR, 'Início'); d.seta('M236 315 H286')
d.etapa(290, 290, 180, 50, ['Abrir o instrumento'], GESTOR)
d.etiqueta(285, 348, 190, 'UG (TED) · CNPJ (convênio)', GESTOR); d.seta('M470 315 H506')
d.etapa(510, 287, 200, 56, ['Informar execução', 'e contrapartida'], GESTOR)
d.etiqueta(525, 351, 170, 'Formulário do gestor', GESTOR); d.seta('M710 315 H726')
d.decisao(790, 315, 60, 40, ['Executa por', 'parceiro?'])
d.text(890, 300, 'Sim ou ambos', 12, '#374151', 600); d.seta('M850 315 H926')
d.etapa(930, 287, 190, 56, ['Listar os projetos', 'com CPF do responsável'], GESTOR); d.seta('M1120 315 H1138')
d.circulo(1156, 315, GESTOR, 'Fim')
d.seta('M790 355 V378'); d.text(798, 366, 'Não ou ambos', 12, '#374151', 600, 'start')
d.etapa(690, 382, 200, 56, ['Registrar as atividades', 'da própria unidade'], GESTOR)
# ligação gestor -> responsável
d.seta('M1025 343 V470 H630 V502')
d.text(830, 460, 'o projeto aparece para o responsável pelo CPF', 12, '#374151', 600)
# Raia responsável
d.raia(460, 160, '', RESP, ['Responsável', 'pelo projeto'])
d.circulo(222, 530, RESP, 'Início'); d.seta('M236 530 H286')
d.etapa(290, 505, 180, 50, ['Entrar com o CPF'], RESP); d.seta('M470 530 H536')
d.etapa(540, 505, 180, 50, ['Abrir o projeto'], RESP)
d.etiqueta(550, 563, 160, 'Meus instrumentos', RESP); d.seta('M720 530 H766')
d.etapa(770, 502, 230, 56, ['Registrar a atividade', 'e os beneficiários'], RESP)
d.etiqueta(800, 566, 170, 'Formulário de execução', RESP); d.seta('M1000 530 H1038')
d.circulo(1056, 530, RESP, 'Fim')
d.obs(1010, 566, 170, ['Um preenchimento', 'por atividade'])
d.obs(290, 566, 240, ['O CPF é dado pessoal: serve só', 'para liberar o projeto'], DEC, '!')
d.salvar('quem-preenche.svg')

# ---------- 2. Como começar ----------
d = D(1200, 560, 'Como começar no Coleta MIR',
      'A pessoa cria a conta com CPF, nome, e-mail e senha. Gestor de TED informa a UG, convenente informa o CNPJ e servidor do MIR pede o perfil MIR; o MIR confere o pedido e libera ou recusa com motivo. Responsável por projeto não pede perfil e vai direto para Meus instrumentos.')
d.legenda([('circ', '#6B7280', 'Início e fim'), ('etapa', PESSOA, 'Etapa (cor do ator)'), ('dec', DEC, 'Decisão'),
           ('etq', None, 'Tela ou formulário'), ('obs', '#9CA3AF', 'Observação')])
d.raia(80, 240, '', PESSOA, ['Quem vai', 'preencher'])
d.circulo(222, 210, PESSOA, 'Início'); d.seta('M236 210 H286')
d.etapa(290, 182, 200, 56, ['Criar a conta', 'CPF, nome, e-mail, senha'], PESSOA)
d.etiqueta(305, 246, 170, 'Solicitar acesso', PESSOA); d.seta('M490 210 H526')
d.decisao(600, 210, 70, 42, ['Qual o seu', 'papel?'])
for y, t in ((168, 'Gestor de TED: informa a UG'), (210, 'Convenente: informa o CNPJ'), (252, 'Servidor do MIR: pede perfil')):
    d.seta(f'M670 210 H695 V{y} H716' if y != 210 else 'M670 210 H716')
    d.raw(f'<rect x="720" y="{y-18}" width="240" height="36" rx="8" fill="{PESSOA}"/>')
    d.text(840, y, t, 13, '#FFFFFF', 700)
    d.linha(f'M960 {y} H985')
d.seta('M985 168 V330 H810 V356')
d.text(620, 116, 'Responsável por projeto: não pede perfil', 12, '#374151', 600, 'start')
d.seta('M600 168 V128 H1095 V178')
d.etapa(1010, 182, 170, 56, ['Preencher', 'em Meus instrumentos'], PESSOA)
d.seta('M1160 238 V262'); d.circulo(1160, 280, PESSOA, 'Fim', 'esq')
d.obs(290, 278, 400, ['Quem já tem conta pede outros perfis em "Meus acessos"'])
# Raia MIR
d.raia(330, 210, '', MIR, ['Servidor', 'do MIR'])
d.etapa(720, 360, 180, 50, ['Conferir o pedido'], MIR)
d.etiqueta(725, 418, 170, 'Solicitações de acesso', MIR); d.seta('M900 385 H996')
d.decisao(1060, 385, 60, 38, ['Libera?'])
d.seta('M1060 347 V242'); d.text(1068, 300, 'Sim', 12, '#374151', 600, 'start')
d.seta('M1060 423 V448'); d.text(1068, 436, 'Não', 12, '#374151', 600, 'start')
d.etapa(975, 452, 170, 50, ['Recusar com motivo'], MIR)
d.obs(290, 452, 380, ['Quem pediu acompanha a liberação ou o motivo da', 'recusa em "Meus acessos"'])
d.salvar('como-comecar.svg')
