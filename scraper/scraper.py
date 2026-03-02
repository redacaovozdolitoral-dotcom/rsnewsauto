#!/usr/bin/env python3
# ARQUIVO: /rsnewsauto/scraper/scraper.py

import json
import os
import time
import hashlib
import requests
import feedparser
import sys
from datetime import datetime
from pathlib import Path

# ─── Importa o rewriter de IA ────────────────────────────────
sys.path.insert(0, str(Path(__file__).parent))
from ia_rewriter import reescrever_lista

# ─── Configuração ────────────────────────────────────────────
BASE_DIR     = Path(__file__).parent.parent
DB_CLIENTES  = BASE_DIR / 'db' / 'clientes.json'
DB_VISTOS    = BASE_DIR / 'db' / 'vistos.json'
LOG_FILE     = BASE_DIR / 'db' / 'scraper.log'
API_URL      = 'https://studiorsilhabela.com.br/rsnewsauto/api.php'

# ─── Logger ──────────────────────────────────────────────────
def log(msg):
    agora = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    linha = f"[{agora}] {msg}"
    print(linha)
    try:
        with open(LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(linha + '\n')
        with open(LOG_FILE, 'r', encoding='utf-8') as f:
            linhas = f.readlines()
        if len(linhas) > 500:
            with open(LOG_FILE, 'w', encoding='utf-8') as f:
                f.writelines(linhas[-500:])
    except:
        pass

# ─── Banco de dados auxiliares ────────────────────────────────
def ler_json(caminho):
    try:
        if os.path.exists(caminho):
            with open(caminho, 'r', encoding='utf-8') as f:
                return json.load(f)
    except:
        pass
    return {}

def salvar_json(caminho, dados):
    os.makedirs(os.path.dirname(caminho), exist_ok=True)
    with open(caminho, 'w', encoding='utf-8') as f:
        json.dump(dados, f, ensure_ascii=False, indent=2)

def ler_clientes():
    try:
        with open(DB_CLIENTES, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        log(f"ERRO ao ler clientes.json: {e}")
        return []

# ─── Controle de duplicidade ──────────────────────────────────
def ja_visto(cliente_id, url):
    vistos = ler_json(DB_VISTOS)
    chave  = hashlib.md5(url.encode()).hexdigest()
    return chave in vistos.get(cliente_id, [])

def marcar_visto(cliente_id, url):
    vistos = ler_json(DB_VISTOS)
    chave  = hashlib.md5(url.encode()).hexdigest()
    if cliente_id not in vistos:
        vistos[cliente_id] = []
    vistos[cliente_id].append(chave)
    vistos[cliente_id] = vistos[cliente_id][-500:]
    salvar_json(DB_VISTOS, vistos)

# ─── Extrai imagem do item RSS ────────────────────────────────
def extrair_imagem(entry):
    if hasattr(entry, 'media_content') and entry.media_content:
        for m in entry.media_content:
            if m.get('url'):
                return m['url']
    if hasattr(entry, 'media_thumbnail') and entry.media_thumbnail:
        return entry.media_thumbnail[0].get('url', '')
    if hasattr(entry, 'enclosures') and entry.enclosures:
        for enc in entry.enclosures:
            if 'image' in enc.get('type', '') or enc.get('href','').endswith(('.jpg','.jpeg','.png','.webp')):
                return enc.get('href','') or enc.get('url','')
    import re
    conteudo = ''
    if hasattr(entry, 'content') and entry.content:
        conteudo = entry.content[0].get('value', '')
    elif hasattr(entry, 'summary'):
        conteudo = entry.summary or ''
    m = re.search(r'<img[^>]+src=["\']([^"\']+)["\']', conteudo)
    if m:
        return m.group(1)
    return ''

# ─── Limpa texto HTML ─────────────────────────────────────────
def limpar_texto(texto):
    import re
    texto = re.sub(r'<[^>]+>', ' ', texto or '')
    texto = re.sub(r'\s+', ' ', texto).strip()
    return texto[:1000]

# ─── Filtra por palavra-chave ─────────────────────────────────
def passou_filtro(titulo, descricao, palavras):
    if not palavras:
        return True
    texto = (titulo + ' ' + descricao).lower()
    for palavra in palavras:
        if palavra.lower().strip() in texto:
            return True
    return False

# ─── Scraping de um cliente ───────────────────────────────────
def processar_cliente(cliente):
    cid      = cliente.get('id', '?')
    portal   = cliente.get('portal', '?')
    status   = cliente.get('status', '')
    automacao= cliente.get('automacao', {})

    if status not in ('ativo', 'trial'):
        log(f"[{portal}] Ignorado — status: {status}")
        return

    if not automacao.get('ativo', False):
        log(f"[{portal}] Automação pausada — pulando")
        return

    palavras = automacao.get('palavras_chave', [])
    sites    = automacao.get('sites_fonte', [])
    token    = cliente.get('token', '')

    if not sites:
        log(f"[{portal}] Nenhum site fonte configurado")
        return

    log(f"[{portal}] Iniciando scraping — {len(sites)} site(s) · {len(palavras)} palavra(s)-chave")

    noticias_novas = []

    for feed_url in sites:
        if not feed_url.strip():
            continue
        try:
            log(f"[{portal}] Lendo feed: {feed_url}")
            feed = feedparser.parse(feed_url, agent='RSNewsImporter/1.0')

            if feed.bozo and not feed.entries:
                log(f"[{portal}] Feed inválido ou inacessível: {feed_url}")
                continue

            fonte = feed.feed.get('title', '') or feed_url
            log(f"[{portal}] Feed ok — {len(feed.entries)} itens encontrados")

            for entry in feed.entries:
                titulo = limpar_texto(entry.get('title', ''))
                link   = entry.get('link', '') or entry.get('id', '')
                resumo = limpar_texto(
                    entry.get('summary', '') or
                    (entry.content[0].get('value','') if hasattr(entry,'content') and entry.content else '')
                )

                if not titulo or not link:
                    continue

                if not passou_filtro(titulo, resumo, palavras):
                    continue

                if ja_visto(cid, link):
                    continue

                img = extrair_imagem(entry)

                tags      = entry.get('tags', [])
                categoria = tags[0].get('term', 'Geral') if tags else 'Geral'

                noticias_novas.append({
                    'h1'      : titulo,
                    'p'       : resumo,
                    'img'     : img,
                    'url'     : link,
                    'category': categoria,
                    'source'  : fonte,
                })

                marcar_visto(cid, link)

                if len(noticias_novas) >= 15:
                    break

        except Exception as e:
            log(f"[{portal}] ERRO no feed {feed_url}: {e}")
            continue

        if len(noticias_novas) >= 15:
            break

    if not noticias_novas:
        log(f"[{portal}] Nenhuma notícia nova encontrada")
        return

    # ─── Reescreve com IA ─────────────────────────────────────
    log(f"[{portal}] Reescrevendo {len(noticias_novas)} notícia(s) com IA...")
    noticias_novas = reescrever_lista(noticias_novas, portal, max_reescritas=10)

    log(f"[{portal}] {len(noticias_novas)} notícia(s) pronta(s) — enviando para API...")

    # ─── Envia para api.php ───────────────────────────────────
    try:
        resp = requests.post(
            API_URL,
            json={
                'action'  : 'receber_noticias',
                'token'   : token,
                'noticias': noticias_novas,
            },
            timeout=30,
            headers={'Content-Type': 'application/json'}
        )
        resultado = resp.json()
        if resultado.get('status') == 'ok':
            log(f"[{portal}] ✅ {resultado.get('adicionadas', 0)} notícia(s) publicada(s) com sucesso!")
        else:
            log(f"[{portal}] ⚠️ API retornou: {resultado}")
    except Exception as e:
        log(f"[{portal}] ERRO ao enviar para API: {e}")

# ─── Main ─────────────────────────────────────────────────────
def main():
    log("=" * 55)
    log("RSNews Scraper iniciado")
    log("=" * 55)

    clientes = ler_clientes()

    if not clientes:
        log("Nenhum cliente encontrado em clientes.json")
        return

    ativos = [c for c in clientes if c.get('status') in ('ativo', 'trial') and c.get('automacao', {}).get('ativo')]
    log(f"Total de clientes: {len(clientes)} · Com automação ativa: {len(ativos)}")

    for cliente in clientes:
        try:
            processar_cliente(cliente)
        except Exception as e:
            log(f"ERRO crítico no cliente {cliente.get('portal','?')}: {e}")
        time.sleep(2)

    log("=" * 55)
    log("RSNews Scraper finalizado")
    log("=" * 55)

if __name__ == '__main__':
    main()
