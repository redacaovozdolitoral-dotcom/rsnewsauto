<?php
// ARQUIVO: /rsnewsauto/painel/cliente.php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Proteção — só cliente logado
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'cliente'){
    header('Location: login.php?status=expirado');
    exit;
}

define('DB_CLIENTES', __DIR__ . '/../db/clientes.json');

function ler_db($arquivo){
    if (!file_exists($arquivo)) return [];
    $dados = json_decode(file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : [];
}

$clientes  = ler_db(DB_CLIENTES);
$cliente   = null;
foreach ($clientes as $cl){
    if ($cl['id'] === $_SESSION['cliente_id']){ $cliente = $cl; break; }
}
if (!$cliente){ session_destroy(); header('Location: login.php?status=expirado'); exit; }

// Verifica trial expirado
$trial_expirado = false;
if ($cliente['status'] === 'trial' && !empty($cliente['trial_ate'])){
    $trial_expirado = strtotime($cliente['trial_ate']) < time();
}

$palavras = implode("\n", $cliente['automacao']['palavras_chave'] ?? []);
$sites    = implode("\n", $cliente['automacao']['sites_fonte']    ?? []);
$dias_trial = 0;
if ($cliente['status'] === 'trial' && !empty($cliente['trial_ate'])){
    $dias_trial = max(0, ceil((strtotime($cliente['trial_ate']) - time()) / 86400));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($cliente['portal']) ?> — RSNEWS IMPORTER</title>
<style>
:root{--verde:#00ff41;--verde2:#39d353;--preto:#0a0a0a;--preto2:#111;--preto3:#1a1a1a;--cinza:#888;--branco:#f0f0f0;--vermelho:#ff4444;--amarelo:#ffbb00}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--preto);color:var(--branco);font-family:'Segoe UI',sans-serif;min-height:100vh}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:var(--preto2);border-right:2px solid rgba(0,255,65,0.15);padding:24px 0;display:flex;flex-direction:column}
.sidebar-logo{padding:0 24px 20px;border-bottom:1px solid rgba(0,255,65,0.1)}
.sidebar-logo img{max-width:100%;max-height:48px;object-fit:contain;margin-bottom:8px;display:block}
.sidebar-logo .sem-logo{font-size:.9rem;font-weight:900;text-transform:uppercase;letter-spacing:2px;color:var(--verde);margin-bottom:4px}
.sidebar-logo p{font-size:.6rem;color:var(--cinza);text-transform:uppercase;letter-spacing:1px}
.sidebar-nav{padding:20px 0;flex:1}
.nav-item{display:flex;align-items:center;gap:10px;padding:12px 24px;color:var(--cinza);text-decoration:none;font-size:.78rem;text-transform:uppercase;letter-spacing:1px;transition:.2s;cursor:pointer;border:none;background:none;width:100%;text-align:left}
.nav-item:hover,.nav-item.ativo{color:var(--verde);background:rgba(0,255,65,0.05);border-left:3px solid var(--verde)}
.nav-item .icon{font-size:1rem;width:20px;text-align:center}
.sidebar-footer{padding:16px 24px;border-top:1px solid rgba(0,255,65,0.1)}
.sidebar-footer a{color:var(--cinza);text-decoration:none;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;transition:.2s}
.sidebar-footer a:hover{color:var(--vermelho)}
.main{margin-left:220px;padding:32px;min-height:100vh}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:12px}
.topbar h2{font-size:1.2rem;font-weight:900;text-transform:uppercase;letter-spacing:2px}
.topbar-info{display:flex;align-items:center;gap:12px}
.badge{display:inline-block;padding:4px 12px;font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.badge-ativo{background:rgba(0,255,65,0.15);color:var(--verde);border:1px solid rgba(0,255,65,0.3)}
.badge-trial{background:rgba(255,187,0,0.15);color:var(--amarelo);border:1px solid rgba(255,187,0,0.3)}
.badge-inativo{background:rgba(255,68,68,0.15);color:var(--vermelho);border:1px solid rgba(255,68,68,0.3)}
.alerta{padding:14px 18px;font-size:.8rem;margin-bottom:24px;border-left:4px solid var(--amarelo);background:rgba(255,187,0,0.07);line-height:1.6}
.alerta.erro{border-color:var(--vermelho);background:rgba(255,68,68,0.07)}
.section{display:none}
.section.ativa{display:block}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.metric{background:var(--preto2);border:1px solid rgba(0,255,65,0.1);padding:20px;position:relative;overflow:hidden}
.metric::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--verde)}
.metric.amarelo::before{background:var(--amarelo)}
.metric-valor{font-size:1.8rem;font-weight:900;color:var(--verde);display:block}
.metric.amarelo .metric-valor{color:var(--amarelo)}
.metric-label{font-size:.62rem;text-transform:uppercase;letter-spacing:2px;color:var(--cinza);margin-top:4px;display:block}
.card{background:var(--preto2);border:1px solid rgba(0,255,65,0.1);margin-bottom:24px}
.card-header{padding:16px 22px;border-bottom:1px solid rgba(0,255,65,0.1);display:flex;justify-content:space-between;align-items:center}
.card-header h3{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.card-body{padding:24px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:var(--cinza);margin-bottom:6px}
.form-group input,.form-group select,.form-group textarea{width:100%;background:var(--preto3);border:1px solid rgba(0,255,65,0.2);color:var(--branco);padding:11px 13px;font-size:.85rem;outline:none;transition:.2s;font-family:'Segoe UI',sans-serif}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--verde);box-shadow:0 0 8px rgba(0,255,65,0.1)}
.form-group textarea{resize:vertical;min-height:90px}
.form-group small{display:block;font-size:.65rem;color:var(--cinza);margin-top:5px;line-height:1.5}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn{padding:12px 22px;font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;border:none;cursor:pointer;transition:.2s}
.btn-verde{background:var(--verde);color:var(--preto)}
.btn-verde:hover{background:var(--verde2)}
.btn-outline{background:transparent;color:var(--verde);border:2px solid var(--verde)}
.btn-outline:hover{background:var(--verde);color:var(--preto)}
.btn-full{width:100%;background:var(--verde);color:var(--preto);border:none;padding:13px;font-size:.82rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:.2s;margin-top:6px}
.btn-full:hover{background:var(--verde2)}
.btn-full:disabled{opacity:.5;cursor:not-allowed}
.msg{padding:11px 14px;font-size:.78rem;margin-bottom:16px;border-left:3px solid var(--verde);background:rgba(0,255,65,0.05);line-height:1.5}
.msg.erro{border-color:var(--vermelho);background:rgba(255,68,68,0.05)}
.toggle-wrap{display:flex;align-items:center;gap:14px}
.toggle{position:relative;width:52px;height:28px;cursor:pointer}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--preto3);border:1px solid rgba(0,255,65,0.2);transition:.3s;border-radius:0}
.toggle-slider::before{content:'';position:absolute;height:20px;width:20px;left:3px;bottom:3px;background:var(--cinza);transition:.3s}
.toggle input:checked + .toggle-slider{background:rgba(0,255,65,0.15);border-color:var(--verde)}
.toggle input:checked + .toggle-slider::before{transform:translateX(24px);background:var(--verde)}
.toggle-label{font-size:.78rem;color:var(--cinza);text-transform:uppercase;letter-spacing:1px}
.color-wrap{display:flex;align-items:center;gap:10px}
.color-wrap input[type=color]{width:48px;height:38px;background:none;border:1px solid rgba(0,255,65,0.2);cursor:pointer;padding:2px}
.color-wrap input[type=text]{flex:1}
.upload-area{border:2px dashed rgba(0,255,65,0.2);padding:32px;text-align:center;cursor:pointer;transition:.2s;position:relative}
.upload-area:hover{border-color:var(--verde);background:rgba(0,255,65,0.03)}
.upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.upload-area p{font-size:.78rem;color:var(--cinza)}
.upload-area .icon{font-size:2rem;margin-bottom:10px;display:block}
.logo-preview{max-width:180px;max-height:80px;object-fit:contain;display:block;margin:12px auto 0;border:1px solid rgba(0,255,65,0.1);padding:8px;background:var(--preto3)}
.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
.dot-verde{background:var(--verde);box-shadow:0 0 6px var(--verde)}
.dot-amarelo{background:var(--amarelo)}
.dot-vermelho{background:var(--vermelho)}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(0,255,65,0.05);font-size:.8rem}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--cinza);text-transform:uppercase;letter-spacing:1px;font-size:.68rem}
.info-val{color:var(--branco);font-weight:600}
@media(max-width:900px){
  .sidebar{display:none}
  .main{margin-left:0}
  .grid3{grid-template-columns:1fr 1fr}
  .grid2{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <?php if (!empty($cliente['aparencia']['logo'])): ?>
      <img src="<?= htmlspecialchars($cliente['aparencia']['logo']) ?>" alt="Logo">
    <?php else: ?>
      <div class="sem-logo"><?= htmlspecialchars($cliente['portal']) ?></div>
    <?php endif; ?>
    <p>Painel do cliente</p>
  </div>
  <nav class="sidebar-nav">
    <button class="nav-item ativo" onclick="trocarSecao('inicio')"><span class="icon">🏠</span> Início</button>
    <button class="nav-item" onclick="trocarSecao('automacao')"><span class="icon">⚙️</span> Automação</button>
    <button class="nav-item" onclick="trocarSecao('aparencia')"><span class="icon">🎨</span> Aparência</button>
    <button class="nav-item" onclick="trocarSecao('wordpress')"><span class="icon">🌐</span> WordPress</button>
    <button class="nav-item" onclick="trocarSecao('redes')"><span class="icon">📲</span> Redes Sociais</button>
    <button class="nav-item" onclick="trocarSecao('plugin')"><span class="icon">🔌</span> Plugin</button>
    <button class="nav-item" onclick="trocarSecao('conta')"><span class="icon">👤</span> Minha Conta</button>
  </nav>
  <div class="sidebar-footer">
    <a href="#" onclick="fazerLogout()">🚪 Sair</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <div class="topbar">
    <h2 id="titulo-secao">Início</h2>
    <div class="topbar-info">
      <span class="badge badge-<?= $cliente['status'] ?>">
        <?= $cliente['status'] === 'trial' ? "⏳ Trial — {$dias_trial} dias" : ($cliente['status'] === 'ativo' ? '✅ Ativo' : '❌ Inativo') ?>
      </span>
      <span style="font-size:.7rem;color:var(--cinza)"><?= htmlspecialchars($cliente['plano']) ?></span>
    </div>
  </div>

  <?php if ($trial_expirado): ?>
  <div class="alerta erro">
    ⚠️ Seu período de trial expirou. <strong>Assine um plano</strong> para continuar usando o RSNews Importer.
    <br><a href="#" onclick="trocarSecao('conta')" style="color:var(--vermelho)">Assinar agora →</a>
  </div>
  <?php elseif ($cliente['status'] === 'trial'): ?>
  <div class="alerta">
    ⏳ Você está no período de trial gratuito. Restam <strong><?= $dias_trial ?> dias</strong>.
    <a href="#" onclick="trocarSecao('conta')" style="color:var(--amarelo)">Assinar plano →</a>
  </div>
  <?php endif; ?>

  <!-- ── INÍCIO ── -->
  <section class="section ativa" id="sec-inicio">
    <div class="grid3">
      <div class="metric">
        <span class="metric-valor"><?= count($cliente['automacao']['palavras_chave'] ?? []) ?></span>
        <span class="metric-label">Palavras-chave</span>
      </div>
      <div class="metric">
        <span class="metric-valor"><?= count($cliente['automacao']['sites_fonte'] ?? []) ?></span>
        <span class="metric-label">Sites fonte</span>
      </div>
      <div class="metric <?= $cliente['automacao']['ativo'] ? '' : 'amarelo' ?>">
        <span class="metric-valor"><?= $cliente['automacao']['ativo'] ? '✅' : '⏸' ?></span>
        <span class="metric-label">Automação <?= $cliente['automacao']['ativo'] ? 'ativa' : 'pausada' ?></span>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>📋 Resumo da configuração</h3></div>
      <div class="card-body">
        <div class="info-row">
          <span class="info-label">Portal</span>
          <span class="info-val"><?= htmlspecialchars($cliente['portal']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">WordPress</span>
          <span class="info-val"><?= !empty($cliente['wordpress']['url']) ? htmlspecialchars($cliente['wordpress']['url']) : '⚠️ Não configurado' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Intervalo de busca</span>
          <span class="info-val">A cada <?= $cliente['automacao']['intervalo_min'] ?? 30 ?> minutos</span>
        </div>
        <div class="info-row">
          <span class="info-label">Instagram</span>
          <span class="info-val">
            <span class="status-dot <?= $cliente['instagram']['sessao_salva'] ? 'dot-verde' : 'dot-amarelo' ?>"></span>
            <?= $cliente['instagram']['sessao_salva'] ? 'Conectado' : 'Não conectado' ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Facebook</span>
          <span class="info-val">
            <span class="status-dot <?= $cliente['facebook']['sessao_salva'] ? 'dot-verde' : 'dot-amarelo' ?>"></span>
            <?= $cliente['facebook']['sessao_salva'] ? 'Conectado' : 'Não conectado' ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label">Plano</span>
          <span class="info-val"><?= htmlspecialchars($cliente['plano']) ?> — R$<?= number_format($cliente['plano_config']['preco'] ?? 0, 0, ',', '.') ?>/mês</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ── AUTOMAÇÃO ── -->
  <section class="section" id="sec-automacao">
    <div class="card">
      <div class="card-header"><h3>⚙️ Configurações de automação</h3></div>
      <div class="card-body">
        <div id="msg-automacao"></div>
        <form id="formAutomacao">

          <div class="form-group">
            <label>Palavras-chave (uma por linha)</label>
            <textarea name="palavras_chave" rows="5" placeholder="Ex:&#10;saúde&#10;dengue&#10;vacina&#10;hospital"><?= htmlspecialchars($palavras) ?></textarea>
            <small>O sistema buscará notícias que contenham essas palavras nos sites fonte.</small>
          </div>

          <div class="form-group">
            <label>Sites fonte — URLs dos feeds RSS (um por linha)</label>
            <textarea name="sites_fonte" rows="6" placeholder="Ex:&#10;https://g1.globo.com/saude/feed/rss2.0.xml&#10;https://www.tuasaude.com/feed/&#10;https://tamoiosnews.com.br/feed/"><?= htmlspecialchars($sites) ?></textarea>
            <small>Cole a URL do feed RSS de cada site. A maioria dos sites tem feed em <strong>/feed/</strong> ou <strong>/rss/</strong>.</small>
          </div>

          <div class="grid2">
            <div class="form-group">
              <label>Intervalo de busca (minutos)</label>
              <input type="number" name="intervalo_min" value="<?= $cliente['automacao']['intervalo_min'] ?? 30 ?>" min="5" max="120">
              <small>Mínimo: 5 min · Máximo: 120 min</small>
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
              <div>
                <label>Status da automação</label>
                <div class="toggle-wrap" style="margin-top:12px">
                  <label class="toggle">
                    <input type="checkbox" name="ativo" id="toggleAtivo" <?= $cliente['automacao']['ativo'] ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                  </label>
                  <span class="toggle-label" id="toggleLabel"><?= $cliente['automacao']['ativo'] ? 'Ativa' : 'Pausada' ?></span>
                </div>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-full" id="btnSalvarAutomacao">💾 Salvar configurações</button>
        </form>
      </div>
    </div>
  </section>

  <!-- ── APARÊNCIA ── -->
  <section class="section" id="sec-aparencia">
    <div class="card">
      <div class="card-header"><h3>🎨 Identidade visual</h3></div>
      <div class="card-body">
        <div id="msg-aparencia"></div>

        <!-- Upload logo -->
        <div class="form-group">
          <label>Logo do portal</label>
          <div class="upload-area" id="uploadArea">
            <input type="file" id="inputLogo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" onchange="previewLogo(this)">
            <span class="icon">🖼️</span>
            <p>Clique ou arraste seu logo aqui<br><small>JPG, PNG, SVG, WEBP · Recomendado: 400×120px</small></p>
          </div>
          <?php if (!empty($cliente['aparencia']['logo'])): ?>
            <img src="<?= htmlspecialchars($cliente['aparencia']['logo']) ?>" class="logo-preview" id="logoPreview" alt="Logo atual">
          <?php else: ?>
            <img src="" class="logo-preview" id="logoPreview" alt="" style="display:none">
          <?php endif; ?>
          <button class="btn btn-outline" style="margin-top:12px;width:100%" onclick="enviarLogo()">📤 Enviar logo</button>
        </div>

        <!-- Cores -->
        <div id="msg-cores"></div>
        <form id="formCores">
          <div class="grid2">
            <div class="form-group">
              <label>Cor primária</label>
              <div class="color-wrap">
                <input type="color" id="cor1-picker" value="<?= htmlspecialchars($cliente['aparencia']['cor_primaria'] ?? '#00ff41') ?>"
                  oninput="document.getElementById('cor1-txt').value=this.value">
                <input type="text" id="cor1-txt" name="cor_primaria" value="<?= htmlspecialchars($cliente['aparencia']['cor_primaria'] ?? '#00ff41') ?>"
                  oninput="document.getElementById('cor1-picker').value=this.value" placeholder="#00ff41">
              </div>
            </div>
            <div class="form-group">
              <label>Cor secundária</label>
              <div class="color-wrap">
                <input type="color" id="cor2-picker" value="<?= htmlspecialchars($cliente['aparencia']['cor_secundaria'] ?? '#111111') ?>"
                  oninput="document.getElementById('cor2-txt').value=this.value">
                <input type="text" id="cor2-txt" name="cor_secundaria" value="<?= htmlspecialchars($cliente['aparencia']['cor_secundaria'] ?? '#111111') ?>"
                  oninput="document.getElementById('cor2-picker').value=this.value" placeholder="#111111">
              </div>
            </div>
          </div>
          <button type="submit" class="btn-full">💾 Salvar cores</button>
        </form>
      </div>
    </div>
  </section>

  <!-- ── WORDPRESS ── -->
  <section class="section" id="sec-wordpress">
    <div class="card">
      <div class="card-header"><h3>🌐 Integração WordPress</h3></div>
      <div class="card-body">
        <div id="msg-wordpress"></div>
        <div class="alerta" style="margin-bottom:20px">
          📌 Para conectar, vá no seu WordPress → <strong>Usuários → Seu perfil → Senhas de aplicativo</strong> → gere uma senha nova e cole aqui.
        </div>
        <form id="formWordpress">
          <div class="form-group">
            <label>URL do WordPress</label>
            <input type="url" name="wp_url" placeholder="https://seuportal.com.br" value="<?= htmlspecialchars($cliente['wordpress']['url'] ?? '') ?>" required>
          </div>
          <div class="grid2">
            <div class="form-group">
              <label>Usuário WordPress</label>
              <input type="text" name="wp_usuario" placeholder="admin" value="<?= htmlspecialchars($cliente['wordpress']['usuario'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Senha de aplicativo</label>
              <input type="password" name="wp_senha" placeholder="xxxx xxxx xxxx xxxx" value="<?= htmlspecialchars($cliente['wordpress']['senha_app'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" class="btn-full">💾 Salvar e testar conexão</button>
        </form>
      </div>
    </div>
  </section>

  <!-- ── REDES SOCIAIS ── -->
  <section class="section" id="sec-redes">
    <div class="card">
      <div class="card-header"><h3>📲 Redes sociais</h3></div>
      <div class="card-body">
        <div id="msg-redes"></div>
        <div class="alerta" style="margin-bottom:20px">
          📌 A conexão com Instagram e Facebook é feita pelo administrador do sistema.<br>
          Informe seu usuário abaixo e <strong>fale com o suporte</strong> para ativar.
        </div>

        <div class="grid2">
          <div class="form-group">
            <label>Usuário Instagram</label>
            <input type="text" id="ig-usuario" placeholder="@seuperfil" value="<?= htmlspecialchars($cliente['instagram']['usuario'] ?? '') ?>">
            <small>
              Status:
              <span class="status-dot <?= $cliente['instagram']['sessao_salva'] ? 'dot-verde' : 'dot-amarelo' ?>"></span>
              <?= $cliente['instagram']['sessao_salva'] ? '✅ Conectado' : '⏳ Aguardando conexão' ?>
            </small>
          </div>
          <div class="form-group">
            <label>Usuário Facebook</label>
            <input type="text" id="fb-usuario" placeholder="@suapagina" value="<?= htmlspecialchars($cliente['facebook']['usuario'] ?? '') ?>">
            <small>
              Status:
              <span class="status-dot <?= $cliente['facebook']['sessao_salva'] ? 'dot-verde' : 'dot-amarelo' ?>"></span>
              <?= $cliente['facebook']['sessao_salva'] ? '✅ Conectado' : '⏳ Aguardando conexão' ?>
            </small>
          </div>
        </div>
        <button class="btn-full" onclick="salvarRedes()">💾 Salvar usuários</button>
      </div>
    </div>
  </section>

  <!-- ── PLUGIN ── -->
  <section class="section" id="sec-plugin">
    <div class="card">
      <div class="card-header"><h3>🔌 Plugin WordPress</h3></div>
      <div class="card-body">
        <div id="msg-plugin"></div>
        <p style="font-size:.82rem;color:var(--cinza);margin-bottom:24px;line-height:1.7">
          O plugin <strong>RS News Importer</strong> precisa estar instalado no seu WordPress para que as notícias sejam publicadas automaticamente.
          Clique em <strong>Instalar automaticamente</strong> ou baixe e instale manualmente.
        </p>

        <?php if (!empty($cliente['wordpress']['url']) && !empty($cliente['wordpress']['usuario']) && !empty($cliente['wordpress']['senha_app'])): ?>
          <button class="btn-full" onclick="instalarPlugin()" style="margin-bottom:12px">🚀 Instalar automaticamente no WordPress</button>
        <?php else: ?>
          <div class="alerta erro" style="margin-bottom:16px">⚠️ Configure o WordPress primeiro antes de instalar o plugin.</div>
        <?php endif; ?>

        <button class="btn btn-outline" style="width:100%" onclick="baixarPlugin()">📥 Baixar plugin (.zip) para instalar manualmente</button>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,255,65,0.1)">
          <p style="font-size:.72rem;color:var(--cinza);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px">Instalação manual:</p>
          <ol style="font-size:.78rem;color:rgba(240,240,240,.7);line-height:2;padding-left:18px">
            <li>Baixe o arquivo .zip acima</li>
            <li>Acesse seu WordPress → Plugins → Adicionar novo</li>
            <li>Clique em "Enviar plugin" e selecione o .zip</li>
            <li>Ative o plugin</li>
            <li>Vá em "RS Importer" no menu lateral e configure</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- ── CONTA ── -->
  <section class="section" id="sec-conta">
    <div class="card">
      <div class="card-header"><h3>👤 Minha conta</h3></div>
      <div class="card-body">
        <div id="msg-conta"></div>
        <div class="info-row"><span class="info-label">Portal</span><span class="info-val"><?= htmlspecialchars($cliente['portal']) ?></span></div>
        <div class="info-row"><span class="info-label">Nome</span><span class="info-val"><?= htmlspecialchars($cliente['nome']) ?></span></div>
        <div class="info-row"><span class="info-label">E-mail</span><span class="info-val"><?= htmlspecialchars($cliente['email']) ?></span></div>
        <div class="info-row"><span class="info-label">WhatsApp</span><span class="info-val"><?= htmlspecialchars($cliente['whatsapp']) ?></span></div>
        <div class="info-row"><span class="info-label">Plano</span><span class="info-val"><?= htmlspecialchars($cliente['plano']) ?></span></div>
        <div class="info-row"><span class="info-label">Status</span><span class="info-val"><?= $cliente['status'] ?></span></div>
        <div class="info-row"><span class="info-label">Trial até</span><span class="info-val"><?= !empty($cliente['trial_ate']) ? date('d/m/Y', strtotime($cliente['trial_ate'])) : '—' ?></span></div>
        <div class="info-row"><span class="info-label">Cliente desde</span><span class="info-val"><?= date('d/m/Y', strtotime($cliente['criado_em'])) ?></span></div>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,255,65,0.1)">
          <p style="font-size:.72rem;color:var(--cinza);margin-bottom:16px;text-transform:uppercase;letter-spacing:1px">Alterar senha</p>
          <form id="formSenha">
            <div class="grid2">
              <div class="form-group">
                <label>Nova senha</label>
                <input type="password" name="nova_senha" placeholder="Mínimo 8 caracteres" minlength="8">
              </div>
              <div class="form-group">
                <label>Confirmar senha</label>
                <input type="password" name="conf_senha" placeholder="Repita a senha">
              </div>
            </div>
            <button type="submit" class="btn-full">🔐 Alterar senha</button>
          </form>
        </div>

        <?php if ($cliente['status'] !== 'ativo'): ?>
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(0,255,65,0.1)">
          <p style="font-size:.72rem;color:var(--amarelo);margin-bottom:16px;text-transform:uppercase;letter-spacing:1px">⚡ Assinar plano</p>
          <a href="/rsnewsauto/#planos" class="btn-full" style="display:block;text-align:center;text-decoration:none;line-height:2">Ver planos e assinar →</a>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </section>

</main>

<script>
const TOKEN = '<?= $cliente['token'] ?>';
const ID    = '<?= $cliente['id'] ?>';

const TITULOS = {
  inicio:    'Início',
  automacao: 'Automação',
  aparencia: 'Aparência',
  wordpress: 'WordPress',
  redes:     'Redes Sociais',
  plugin:    'Plugin',
  conta:     'Minha Conta'
};

function trocarSecao(id){
  document.querySelectorAll('.section').forEach(s => s.classList.remove('ativa'));
  document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('ativo'));
  document.getElementById('sec-' + id).classList.add('ativa');
  event.currentTarget && event.currentTarget.classList.add('ativo');
  document.getElementById('titulo-secao').textContent = TITULOS[id] || id;
}

function mostrarMsg(id, msg, erro=false){
  const el = document.getElementById(id);
  if(!el) return;
  el.innerHTML = `<div class="msg ${erro?'erro':''}">${msg}</div>`;
  setTimeout(() => el.innerHTML = '', 4000);
}

// TOGGLE
document.getElementById('toggleAtivo').addEventListener('change', function(){
  document.getElementById('toggleLabel').textContent = this.checked ? 'Ativa' : 'Pausada';
});

// AUTOMAÇÃO
document.getElementById('formAutomacao').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnSalvarAutomacao');
  btn.textContent = 'Salvando...'; btn.disabled = true;

  const palavras = document.querySelector('[name=palavras_chave]').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const sites    = document.querySelector('[name=sites_fonte]').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const intervalo= parseInt(document.querySelector('[name=intervalo_min]').value) || 30;
  const ativo    = document.getElementById('toggleAtivo').checked;

  const res  = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'atualizar_config', token: TOKEN,
      automacao: {palavras_chave: palavras, sites_fonte: sites, intervalo_min: intervalo, ativo}
    })
  });
  const json = await res.json();
  mostrarMsg('msg-automacao', json.status === 'ok' ? '✅ Configurações salvas!' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
  btn.textContent = '💾 Salvar configurações'; btn.disabled = false;
});

// CORES
document.getElementById('formCores').addEventListener('submit', async function(e){
  e.preventDefault();
  const res  = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'atualizar_config', token: TOKEN,
      aparencia: {
        cor_primaria:   document.getElementById('cor1-txt').value,
        cor_secundaria: document.getElementById('cor2-txt').value,
      }
    })
  });
  const json = await res.json();
  mostrarMsg('msg-cores', json.status === 'ok' ? '✅ Cores salvas!' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
});

// LOGO
function previewLogo(input){
  if(input.files && input.files[0]){
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('logoPreview');
      img.src = e.target.result;
      img.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
async function enviarLogo(){
  const input = document.getElementById('inputLogo');
  if(!input.files[0]){ mostrarMsg('msg-aparencia','Selecione um arquivo primeiro.',true); return; }
  const form = new FormData();
  form.append('token', TOKEN);
  form.append('logo', input.files[0]);
  const res  = await fetch('/rsnewsauto/api.php?action=upload_logo', {method:'POST', body: form});
  const json = await res.json();
  mostrarMsg('msg-aparencia', json.status === 'ok' ? '✅ Logo enviado!' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
}

// WORDPRESS
document.getElementById('formWordpress').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = e.target.querySelector('button');
  btn.textContent = 'Salvando e testando...'; btn.disabled = true;
  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'atualizar_config', token: TOKEN,
      wordpress: {
        url:       document.querySelector('[name=wp_url]').value,
        usuario:   document.querySelector('[name=wp_usuario]').value,
        senha_app: document.querySelector('[name=wp_senha]').value,
      }
    })
  });
  const json = await res.json();
  mostrarMsg('msg-wordpress', json.status === 'ok' ? '✅ WordPress salvo!' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
  btn.textContent = '💾 Salvar e testar conexão'; btn.disabled = false;
});

// REDES
async function salvarRedes(){
  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'atualizar_config', token: TOKEN,
      instagram: {usuario: document.getElementById('ig-usuario').value},
      facebook:  {usuario: document.getElementById('fb-usuario').value},
    })
  });
  const json = await res.json();
  mostrarMsg('msg-redes', json.status === 'ok' ? '✅ Usuários salvos! Fale com o suporte para ativar.' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
}

// PLUGIN
async function instalarPlugin(){
  mostrarMsg('msg-plugin','⏳ Instalando plugin no WordPress...');
  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'instalar_plugin', token: TOKEN, id: ID})
  });
  const json = await res.json();
  mostrarMsg('msg-plugin', json.status === 'ok' ? '✅ Plugin instalado com sucesso!' : '❌ ' + (json.erro||'Erro na instalação'), json.status !== 'ok');
}
function baixarPlugin(){
  window.location.href = '/rsnewsauto/api.php?action=baixar_plugin&token=' + TOKEN;
}

// SENHA
document.getElementById('formSenha').addEventListener('submit', async function(e){
  e.preventDefault();
  const nova  = document.querySelector('[name=nova_senha]').value;
  const conf  = document.querySelector('[name=conf_senha]').value;
  if(nova !== conf){ mostrarMsg('msg-conta','As senhas não conferem.',true); return; }
  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'alterar_senha', token: TOKEN, nova_senha: nova})
  });
  const json = await res.json();
  mostrarMsg('msg-conta', json.status === 'ok' ? '✅ Senha alterada!' : '❌ ' + (json.erro||'Erro'), json.status !== 'ok');
  if(json.status === 'ok') e.target.reset();
});

// LOGOUT
async function fazerLogout(){
  await fetch('/rsnewsauto/api.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'logout'})
  });
  window.location.href = '/rsnewsauto/painel/login.php';
}
</script>
</body>
</html>
