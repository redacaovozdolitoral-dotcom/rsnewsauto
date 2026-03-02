#!/usr/bin/env python3
# ARQUIVO: /rsnewsauto/scraper/ia_rewriter.py
# Reescreve título e texto das notícias usando IA (Groq — gratuito)
# Integrado ao scraper.py automaticamente

import os
import json
import requests
from pathlib import Path

# ─── Configuração ────────────────────────────────────────────
# Groq é GRATUITO — cria conta em https://console.groq.com
# Gera a API Key e cola aqui:
GROQ_API_KEY = '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'
GROQ_URL     = 'https://api.groq.com/openai/v1/chat/completions'
GROQ_MODEL   = 'llama3-8b-8192'  # Rápido e gratuito

# Fallback: se não tiver Groq, usa Ollama local
OLLAMA_URL   = 'http://localhost:11434/api/generate'
OLLAMA_MODEL = 'llama3'

# ─── Reescreve uma notícia ────────────────────────────────────
def reescrever(noticia, nome_portal='Portal'):
    titulo_original = noticia.get('h1', '')
    texto_original  = noticia.get('p', '')

    if not titulo_original:
        return noticia

    prompt = f"""Você é redator do portal de notícias "{nome_portal}".

Reescreva a notícia abaixo com linguagem jornalística clara e direta.

REGRAS:
- Título: máximo 12 palavras, impactante, sem clickbait
- Texto: 3 parágrafos curtos, máximo 300 palavras no total
- Mantenha os fatos originais, não invente informações
- Escreva em português brasileiro
- NÃO use markdown, asteriscos ou formatação especial
- Responda APENAS com JSON válido no formato abaixo

NOTÍCIA ORIGINAL:
Título: {titulo_original}
Texto: {texto_original}

RESPONDA EXATAMENTE NESTE FORMATO JSON:
{{"titulo": "novo título aqui", "texto": "novo texto aqui"}}"""

    # Tenta Groq primeiro
    if GROQ_API_KEY and GROQ_API_KEY != 'SUA_GROQ_API_KEY_AQUI':
        resultado = _groq(prompt)
        if resultado:
            noticia['h1'] = resultado.get('titulo', titulo_original)
            noticia['p']  = resultado.get('texto',  texto_original)
            noticia['ia_reescrita'] = True
            return noticia

    # Fallback: Ollama local
    resultado = _ollama(prompt)
    if resultado:
        noticia['h1'] = resultado.get('titulo', titulo_original)
        noticia['p']  = resultado.get('texto',  texto_original)
        noticia['ia_reescrita'] = True

    return noticia

# ─── Groq API ────────────────────────────────────────────────
def _groq(prompt):
    try:
        resp = requests.post(
            GROQ_URL,
            headers={
                'Authorization': f'Bearer {GROQ_API_KEY}',
                'Content-Type' : 'application/json',
            },
            json={
                'model'      : GROQ_MODEL,
                'messages'   : [{'role': 'user', 'content': prompt}],
                'temperature': 0.7,
                'max_tokens' : 600,
            },
            timeout=20,
        )
        if resp.status_code == 200:
            conteudo = resp.json()['choices'][0]['message']['content'].strip()
            return _extrair_json(conteudo)
    except Exception as e:
        print(f"[IA] Erro Groq: {e}")
    return None

# ─── Ollama local ─────────────────────────────────────────────
def _ollama(prompt):
    try:
        resp = requests.post(
            OLLAMA_URL,
            json={
                'model' : OLLAMA_MODEL,
                'prompt': prompt,
                'stream': False,
            },
            timeout=60,
        )
        if resp.status_code == 200:
            conteudo = resp.json().get('response', '').strip()
            return _extrair_json(conteudo)
    except Exception as e:
        print(f"[IA] Erro Ollama: {e}")
    return None

# ─── Extrai JSON da resposta da IA ───────────────────────────
def _extrair_json(texto):
    import re
    # Tenta direto
    try:
        return json.loads(texto)
    except:
        pass
    # Tenta extrair bloco JSON
    m = re.search(r'\{[^{}]+\}', texto, re.DOTALL)
    if m:
        try:
            return json.loads(m.group())
        except:
            pass
    # Tenta extrair título e texto manualmente
    titulo = re.search(r'"titulo"\s*:\s*"([^"]+)"', texto)
    texto2 = re.search(r'"texto"\s*:\s*"([^"]+)"',  texto)
    if titulo and texto2:
        return {'titulo': titulo.group(1), 'texto': texto2.group(1)}
    return None

# ─── Reescreve lista de notícias ─────────────────────────────
def reescrever_lista(noticias, nome_portal='Portal', max_reescritas=10):
    """
    Reescreve até max_reescritas notícias da lista.
    Retorna a lista com as notícias reescritas.
    """
    reescritas = 0
    for i, n in enumerate(noticias):
        if reescritas >= max_reescritas:
            break
        try:
            noticias[i] = reescrever(n, nome_portal)
            if noticias[i].get('ia_reescrita'):
                reescritas += 1
                print(f"[IA] ✅ Reescrita {reescritas}: {noticias[i]['h1'][:60]}...")
        except Exception as e:
            print(f"[IA] Erro ao reescrever notícia {i}: {e}")
    print(f"[IA] Total reescritas: {reescritas}/{len(noticias)}")
    return noticias

# ─── Teste direto ─────────────────────────────────────────────
if __name__ == '__main__':
    teste = {
        'h1': 'Prefeitura anuncia obras na avenida principal da cidade',
        'p' : 'A prefeitura municipal anunciou hoje o início das obras de revitalização da avenida principal. Os trabalhos incluem recapeamento asfáltico, troca de calçadas e nova iluminação pública. A previsão é que as obras sejam concluídas em 90 dias.',
        'url': 'https://exemplo.com/noticia',
        'img': '',
        'category': 'Cidade',
        'source': 'Exemplo News',
    }
    print("Notícia original:")
    print(f"Título: {teste['h1']}")
    print(f"Texto:  {teste['p'][:100]}...")
    print()
    resultado = reescrever(teste, 'Voz do Litoral')
    print("Notícia reescrita:")
    print(f"Título: {resultado['h1']}")
    print(f"Texto:  {resultado['p'][:100]}...")
