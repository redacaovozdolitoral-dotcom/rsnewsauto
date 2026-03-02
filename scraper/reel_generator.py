#!/usr/bin/env python3
# ARQUIVO: /rsnewsauto/scraper/reel_generator.py
# Gera Reel 9:16 com logo, cores e texto do cliente
# Publica no Instagram via Graph API

import os
import sys
import json
import time
import requests
import textwrap
import numpy as np
from pathlib import Path
from datetime import datetime
from io import BytesIO

# Pillow
from PIL import Image, ImageDraw, ImageFont, ImageFilter, ImageEnhance

# MoviePy
from moviepy.editor import (
    ImageClip, CompositeVideoClip, TextClip,
    AudioFileClip, concatenate_videoclips
)
from moviepy.video.fx.all import resize, crop

# ─── Configuração ────────────────────────────────────────────
BASE_DIR      = Path(__file__).parent.parent
UPLOADS_DIR   = BASE_DIR / 'uploads'
REELS_DIR     = UPLOADS_DIR / 'reels'
LOGOS_DIR     = UPLOADS_DIR / 'logos'
FONTS_DIR     = BASE_DIR / 'scraper' / 'fonts'
LOG_FILE      = BASE_DIR / 'db' / 'scraper.log'

# Dimensões Reel 9:16
REEL_W  = 1080
REEL_H  = 1920
DURACAO = 12  # segundos

# ─── Logger ──────────────────────────────────────────────────
def log(msg):
    agora = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    linha = f"[{agora}] [REEL] {msg}"
    print(linha)
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(linha + '\n')
    except:
        pass

# ─── Garante pastas ───────────────────────────────────────────
def garantir_pastas():
    for pasta in [REELS_DIR, LOGOS_DIR, FONTS_DIR]:
        pasta.mkdir(parents=True, exist_ok=True)

# ─── Baixa imagem da notícia ──────────────────────────────────
def baixar_imagem(url):
    if not url:
        return None
    try:
        resp = requests.get(url, timeout=15, headers={'User-Agent':'Mozilla/5.0'})
        if resp.status_code == 200:
            img = Image.open(BytesIO(resp.content)).convert('RGB')
            return img
    except Exception as e:
        log(f"Erro ao baixar imagem: {e}")
    return None

# ─── Imagem de fallback (fundo gradiente) ────────────────────
def imagem_fallback(cor_primaria='#00ff41', cor_secundaria='#111111'):
    img  = Image.new('RGB', (REEL_W, REEL_H), cor_secundaria)
    draw = ImageDraw.Draw(img)
    # Gradiente simples
    r1,g1,b1 = hex_para_rgb(cor_secundaria)
    r2,g2,b2 = hex_para_rgb(cor_primaria)
    for y in range(REEL_H):
        t   = y / REEL_H
        r   = int(r1 + (r2 - r1) * t * 0.3)
        g   = int(g1 + (g2 - g1) * t * 0.3)
        b   = int(b1 + (b2 - b1) * t * 0.3)
        draw.line([(0, y), (REEL_W, y)], fill=(r, g, b))
    return img

# ─── Hex para RGB ─────────────────────────────────────────────
def hex_para_rgb(hex_cor):
    hex_cor = hex_cor.lstrip('#')
    if len(hex_cor) != 6:
        return (0, 255, 65)
    return tuple(int(hex_cor[i:i+2], 16) for i in (0, 2, 4))

# ─── Prepara fundo 9:16 com a imagem ─────────────────────────
def preparar_fundo(img_noticia, cor_primaria, cor_secundaria):
    if img_noticia is None:
        return imagem_fallback(cor_primaria, cor_secundaria)

    # Redimensiona para cobrir 9:16
    ratio_w = REEL_W  / img_noticia.width
    ratio_h = REEL_H  / img_noticia.height
    ratio   = max(ratio_w, ratio_h)
    novo_w  = int(img_noticia.width  * ratio)
    novo_h  = int(img_noticia.height * ratio)
    img     = img_noticia.resize((novo_w, novo_h), Image.LANCZOS)

    # Corta centro
    x = (novo_w - REEL_W) // 2
    y = (novo_h - REEL_H) // 2
    img = img.crop((x, y, x + REEL_W, y + REEL_H))

    # Escurece para o texto ficar legível
    escurecedor = Image.new('RGB', (REEL_W, REEL_H), (0, 0, 0))
    img = Image.blend(img, escurecedor, 0.45)
    return img

# ─── Carrega fonte ────────────────────────────────────────────
def carregar_fonte(tamanho, negrito=False):
    nomes = [
        'DejaVuSans-Bold.ttf' if negrito else 'DejaVuSans.ttf',
        'arial.ttf',
        'Arial.ttf',
    ]
    # Tenta na pasta fonts/
    for nome in nomes:
        caminho = FONTS_DIR / nome
        if caminho.exists():
            try:
                return ImageFont.truetype(str(caminho), tamanho)
            except:
                pass
    # Tenta no sistema
    caminhos_sistema = [
        f'/usr/share/fonts/truetype/dejavu/{"DejaVuSans-Bold" if negrito else "DejaVuSans"}.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/System/Library/Fonts/Helvetica.ttc',
    ]
    for c in caminhos_sistema:
        if os.path.exists(c):
            try:
                return ImageFont.truetype(c, tamanho)
            except:
                pass
    return ImageFont.load_default()

# ─── Desenha texto com quebra de linha ───────────────────────
def desenhar_texto_quebrado(draw, texto, x, y, fonte, cor, largura_max, espacamento=8):
    palavras    = texto.split()
    linha_atual = ''
    linhas      = []
    for palavra in palavras:
        teste = (linha_atual + ' ' + palavra).strip()
        bbox  = draw.textbbox((0,0), teste, font=fonte)
        if bbox[2] - bbox[0] <= largura_max:
            linha_atual = teste
        else:
            if linha_atual:
                linhas.append(linha_atual)
            linha_atual = palavra
    if linha_atual:
        linhas.append(linha_atual)

    y_atual = y
    for linha in linhas:
        draw.text((x, y_atual), linha, font=fonte, fill=cor)
        bbox    = draw.textbbox((0,0), linha, font=fonte)
        y_atual += (bbox[3] - bbox[1]) + espacamento
    return y_atual

# ─── Gera frame PNG do reel ───────────────────────────────────
def gerar_frame(noticia, cliente):
    garantir_pastas()

    cor_prim = cliente.get('aparencia', {}).get('cor_primaria',   '#00ff41')
    cor_sec  = cliente.get('aparencia', {}).get('cor_secundaria', '#111111')
    portal   = cliente.get('portal', 'Portal')
    logo_path= cliente.get('aparencia', {}).get('logo', '')

    titulo   = noticia.get('h1', '')
    resumo   = noticia.get('p',  '')[:180]
    categoria= noticia.get('category', 'Notícia')
    fonte_txt= noticia.get('source', portal)

    r_prim   = hex_para_rgb(cor_prim)

    # 1. Fundo
    img_noticia = baixar_imagem(noticia.get('img',''))
    fundo       = preparar_fundo(img_noticia, cor_prim, cor_sec)
    draw        = ImageDraw.Draw(fundo)

    # 2. Faixa inferior (gradiente escuro para texto)
    faixa_h = 780
    faixa   = Image.new('RGBA', (REEL_W, faixa_h), (0, 0, 0, 0))
    d_faixa = ImageDraw.Draw(faixa)
    for y in range(faixa_h):
        alpha = int(210 * (y / faixa_h))
        d_faixa.line([(0, y), (REEL_W, y)], fill=(0, 0, 0, alpha))
    fundo_rgba = fundo.convert('RGBA')
    fundo_rgba.paste(faixa, (0, REEL_H - faixa_h), faixa)
    fundo = fundo_rgba.convert('RGB')
    draw  = ImageDraw.Draw(fundo)

    # 3. Faixa de categoria (topo)
    cat_fonte = carregar_fonte(32, negrito=True)
    cat_texto = f"  {categoria.upper()}  "
    cat_bbox  = draw.textbbox((0,0), cat_texto, font=cat_fonte)
    cat_w     = cat_bbox[2] - cat_bbox[0] + 20
    cat_h     = cat_bbox[3] - cat_bbox[1] + 16
    draw.rectangle([60, 80, 60 + cat_w, 80 + cat_h], fill=r_prim + (255,) if len(r_prim)==3 else r_prim)
    draw.text((70, 88), cat_texto.strip(), font=cat_fonte, fill=(0, 0, 0))

    # 4. Logo do cliente (topo direito)
    logo_ok = False
    if logo_path:
        caminho_logo = BASE_DIR / logo_path.lstrip('/')
        if caminho_logo.exists():
            try:
                logo = Image.open(str(caminho_logo)).convert('RGBA')
                logo.thumbnail((240, 80), Image.LANCZOS)
                pos_x = REEL_W - logo.width - 60
                pos_y = 70
                fundo_rgba2 = fundo.convert('RGBA')
                fundo_rgba2.paste(logo, (pos_x, pos_y), logo)
                fundo = fundo_rgba2.convert('RGB')
                draw  = ImageDraw.Draw(fundo)
                logo_ok = True
            except Exception as e:
                log(f"Erro ao aplicar logo: {e}")

    if not logo_ok:
        fonte_portal = carregar_fonte(36, negrito=True)
        draw.text((REEL_W - 300, 80), portal.upper()[:20], font=fonte_portal, fill=r_prim)

    # 5. Linha colorida decorativa
    draw.rectangle([0, REEL_H - faixa_h - 6, REEL_W, REEL_H - faixa_h], fill=r_prim)

    # 6. Título
    fonte_titulo = carregar_fonte(72, negrito=True)
    margem       = 60
    y_titulo     = REEL_H - faixa_h + 60
    y_apos_titulo= desenhar_texto_quebrado(
        draw, titulo, margem, y_titulo,
        fonte_titulo, (255, 255, 255),
        REEL_W - margem * 2, espacamento=12
    )

    # 7. Linha separadora
    draw.rectangle([margem, y_apos_titulo + 10, margem + 80, y_apos_titulo + 14], fill=r_prim)

    # 8. Resumo
    fonte_resumo = carregar_fonte(42)
    desenhar_texto_quebrado(
        draw, resumo, margem, y_apos_titulo + 36,
        fonte_resumo, (200, 200, 200),
        REEL_W - margem * 2, espacamento=8
    )

    # 9. Rodapé
    fonte_rodape = carregar_fonte(30)
    draw.text((margem, REEL_H - 70), f"📰 {fonte_txt}", font=fonte_rodape, fill=(150, 150, 150))
    draw.text((REEL_W - 280, REEL_H - 70), datetime.now().strftime('%d/%m/%Y'), font=fonte_rodape, fill=(150, 150, 150))

    return fundo

# ─── Gera vídeo MP4 com efeito Ken Burns ─────────────────────
def gerar_video(noticia, cliente):
    garantir_pastas()

    log(f"Gerando vídeo: {noticia.get('h1','')[:50]}...")

    frame      = gerar_frame(noticia, cliente)
    frame_path = str(REELS_DIR / f"frame_{int(time.time())}.png")
    frame.save(frame_path, 'PNG')

    # Ken Burns — zoom suave de 1.0 para 1.08
    def make_frame(t):
        progresso = t / DURACAO
        zoom      = 1.0 + 0.08 * progresso
        img       = Image.open(frame_path)
        novo_w    = int(REEL_W * zoom)
        novo_h    = int(REEL_H * zoom)
        img       = img.resize((novo_w, novo_h), Image.LANCZOS)
        x         = (novo_w - REEL_W) // 2
        y         = (novo_h - REEL_H) // 2
        img       = img.crop((x, y, x + REEL_W, y + REEL_H))
        return np.array(img)

    clip       = ImageClip(frame_path, duration=DURACAO)
    video      = clip.fl(lambda gf, t: make_frame(t))
    video      = video.set_fps(24)

    # Fade in / fade out
    video = video.fadein(0.5).fadeout(0.5)

    nome_arquivo = f"reel_{cliente.get('id','x')}_{int(time.time())}.mp4"
    output_path  = str(REELS_DIR / nome_arquivo)

    video.write_videofile(
        output_path,
        fps=24,
        codec='libx264',
        audio=False,
        preset='ultrafast',
        logger=None,
    )

    # Remove frame temporário
    try:
        os.remove(frame_path)
    except:
        pass

    log(f"✅ Vídeo gerado: {nome_arquivo}")
    return output_path

# ─── Publica no Instagram via Graph API ──────────────────────
def publicar_instagram(video_path, noticia, cliente):
    ig = cliente.get('instagram', {})
    access_token = ig.get('access_token', '')
    ig_user_id   = ig.get('ig_user_id', '')

    if not access_token or not ig_user_id:
        log("Instagram: access_token ou ig_user_id não configurado")
        return False

    legenda = (
        f"{noticia.get('h1','')}\n\n"
        f"{noticia.get('p','')[:200]}...\n\n"
        f"📰 {noticia.get('source','')}\n"
        f"🔗 {noticia.get('url','')}\n\n"
        f"#noticias #{cliente.get('portal','').replace(' ','').lower()}"
    )

    # URL pública do vídeo
    base_url   = 'https://studiorsilhabela.com.br/rsnewsauto/uploads/reels/'
    video_url  = base_url + os.path.basename(video_path)

    try:
        # 1. Cria container
        resp1 = requests.post(
            f"https://graph.facebook.com/v19.0/{ig_user_id}/media",
            data={
                'media_type'  : 'REELS',
                'video_url'   : video_url,
                'caption'     : legenda,
                'access_token': access_token,
            },
            timeout=30,
        )
        dados1 = resp1.json()
        if 'id' not in dados1:
            log(f"Instagram erro container: {dados1}")
            return False

        container_id = dados1['id']
        log(f"Instagram container criado: {container_id}")

        # 2. Aguarda processamento (máx 3 min)
        for tentativa in range(18):
            time.sleep(10)
            status = requests.get(
                f"https://graph.facebook.com/v19.0/{container_id}",
                params={'fields':'status_code','access_token':access_token},
                timeout=15,
            ).json()
            codigo = status.get('status_code', '')
            log(f"Instagram status: {codigo} (tentativa {tentativa+1})")
            if codigo == 'FINISHED':
                break
            if codigo == 'ERROR':
                log("Instagram: erro no processamento do vídeo")
                return False

        # 3. Publica
        resp2 = requests.post(
            f"https://graph.facebook.com/v19.0/{ig_user_id}/media_publish",
            data={
                'creation_id' : container_id,
                'access_token': access_token,
            },
            timeout=30,
        )
        dados2 = resp2.json()
        if 'id' in dados2:
            log(f"✅ Instagram publicado! Post ID: {dados2['id']}")
            return True
        else:
            log(f"Instagram erro publicação: {dados2}")
            return False

    except Exception as e:
        log(f"Instagram ERRO: {e}")
        return False

# ─── Publica no Facebook ──────────────────────────────────────
def publicar_facebook(video_path, noticia, cliente):
    fb = cliente.get('facebook', {})
    access_token = fb.get('access_token', '')
    page_id      = fb.get('page_id', '')

    if not access_token or not page_id:
        log("Facebook: access_token ou page_id não configurado")
        return False

    legenda   = f"{noticia.get('h1','')}\n\n{noticia.get('p','')[:300]}\n\n🔗 {noticia.get('url','')}"
    video_url = 'https://studiorsilhabela.com.br/rsnewsauto/uploads/reels/' + os.path.basename(video_path)

    try:
        resp = requests.post(
            f"https://graph.facebook.com/v19.0/{page_id}/videos",
            data={
                'file_url'    : video_url,
                'description' : legenda,
                'access_token': access_token,
            },
            timeout=60,
        )
        dados = resp.json()
        if 'id' in dados:
            log(f"✅ Facebook publicado! Video ID: {dados['id']}")
            return True
        else:
            log(f"Facebook erro: {dados}")
            return False
    except Exception as e:
        log(f"Facebook ERRO: {e}")
        return False

# ─── Processa uma notícia completa ───────────────────────────
def processar_reel(noticia, cliente):
    portal = cliente.get('portal', '?')
    log(f"[{portal}] Iniciando reel: {noticia.get('h1','')[:50]}...")

    try:
        # 1. Gera vídeo
        video_path = gerar_video(noticia, cliente)

        # 2. Publica Instagram
        ig_ok = publicar_instagram(video_path, noticia, cliente)

        # 3. Publica Facebook
        fb_ok = publicar_facebook(video_path, noticia, cliente)

        log(f"[{portal}] Reel concluído — Instagram: {'✅' if ig_ok else '⏭'} · Facebook: {'✅' if fb_ok else '⏭'}")
        return video_path

    except Exception as e:
        log(f"[{portal}] ERRO ao gerar reel: {e}")
        return None

# ─── Teste direto ─────────────────────────────────────────────
if __name__ == '__main__':
    cliente_teste = {
        'id'       : 'teste',
        'portal'   : 'Voz do Litoral',
        'aparencia': {
            'cor_primaria'  : '#00ff41',
            'cor_secundaria': '#0a0a0a',
            'logo'          : '',
        },
        'instagram': {'access_token':'','ig_user_id':''},
        'facebook' : {'access_token':'','page_id':''},
    }
    noticia_teste = {
        'h1'      : 'Ilhabela registra recorde de turistas no verão 2026',
        'p'       : 'A ilha registrou mais de 150 mil visitantes neste verão, batendo todos os recordes históricos. As praias do sul foram as mais procuradas pelos turistas vindos de São Paulo e Minas Gerais.',
        'img'     : 'https://tamoiosnews.com.br/wp-content/uploads/2026/01/WhatsApp-Image-2026-01-30-at-11.57.42.jpeg',
        'url'     : 'https://tamoiosnews.com.br/',
        'category': 'Regional',
        'source'  : 'Tamoios News',
    }
    frame = gerar_frame(noticia_teste, cliente_teste)
    frame.save('/tmp/teste_reel.png')
    print("✅ Frame salvo em /tmp/teste_reel.png")
    print("Para gerar o vídeo completo execute: processar_reel(noticia, cliente)")
