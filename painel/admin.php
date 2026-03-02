<?php
// ARQUIVO: /rsnewsauto/painel/admin.php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Proteção — só admin
if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin'){
    header('Location: login.php?status=expirado');
    exit;
}

define('DB_CLIENTES', __DIR__ . '/../db/clientes.json');
define('DB_LOGS',     __DIR__ . '/../db/logs.json');

function ler_db($arquivo){
    if (!file_exists($arquivo)) return [];
    $dados = json_decode(file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : [];
}

$clientes = ler_db(DB_CLIENTES);
$logs     = array_slice(ler_db(DB_LOGS), 0, 20);

// Métricas
$total    = count($clientes);
$ativos   = count(array_filter($clientes, fn($c) => $c['status'] === 'ativo'));
$trial    = count(array_filter($clientes, fn($c) => $c['status'] === 'trial'));
$inativos = count(array_filter($clientes, fn($c) => $c['status'] === 'inativo'));

$receita = array_sum(array_map(function($c){
    if ($c['status'] !== 'ativo') return 0;
    return $c['plano_config']['preco'] ?? 0;
}, $clientes));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — RSNEWS IMPORTER</title>
<style>
:root{--verde:#00ff41;--verde2:#39d353;--preto:#0a0a0a;--preto2:#111;--preto3:#1a1a1a;--cinza:#888;--branco:#f0f0f0;--vermelho:#ff4444;--amarelo:#ffbb00}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--preto);color:var(--branco);font-family:'Segoe UI',sans-serif;min-height:100vh}
/* SIDEBAR */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:var(--preto2);border-right:2px solid rgba(0,255,65,0.15);padding:24px 0;display:flex;flex-direction:column}
.sidebar-logo{padding:0 24px 24px;border-bottom:1px solid rgba(0,255,65,0.1)}
.sidebar-logo h1{font-size:1rem;font-weight:900;text-transform:uppercase;letter-spacing:2px;color:var(--verde)}
.sidebar-logo h1 span{color:var(--branco)}
.sidebar-logo p{font-size:.6rem;color:var(--cinza);text-transform:uppercase;letter-spacing:1px;margin-top:2px}
.sidebar-nav{padding:20px 0;flex:1}
.nav-item{display:flex;align-items:center;gap:10px;padding:12px 24px;color:var(--cinza);text-decoration:none;font-size:.78rem;text-transform:uppercase;letter-spacing:1px;transition:.2s;cursor:pointer;border:none;background:none;width:100%;text-align:left}
.nav-item:hover,.nav-item.ativo{color:var(--verde);background:rgba(0,255,65,0.05);border-left:3px solid var(--verde)}
.nav-item .icon{font-size:1rem;width:20px;text-align:center}
.sidebar-footer{padding:16px 24px;border-top:1px solid rgba(0,255,65,0.1)}
.sidebar-footer a{color:var(--cinza);text-decoration:none;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;transition:.2s}
.sidebar-footer a:hover{color:var(--vermelho)}
/* MAIN */
.main{margin-left:220px;padding:32px;min-height:100vh}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px}
.topbar h2{font-size:1.2rem;font-weight:900;text-transform:uppercase;letter-spacing:2px}
.topbar span{font-size:.7rem;color:var(--cinza);text-transform:uppercase;letter-spacing:1px}
/* MÉTRICAS */
.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px}
.metric{background:var(--preto2);border:1px solid rgba(0,255,65,0.1);padding:24px;position:relative;overflow:hidden}
.metric::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--verde)}
.metric.vermelho::before{background:var(--vermelho)}
.metric.amarelo::before{background:var(--amarelo)}
.metric-valor{font-size:2.2rem;font-weight:900;color:var(--verde);display:block}
.metric.vermelho .metric-valor{color:var(--vermelho)}
.metric.amarelo .metric-valor{color:var(--amarelo)}
.metric-label{font-size:.65rem;text-transform:uppercase;letter-spacing:2px;color:var(--cinza);margin-top:4px;display:block}
/* SEÇÕES */
.section{display:none}
.section.ativa{display:block}
/* TABELA CLIENTES */
.card{background:var(--preto2);border:1px solid rgba(0,255,65,0.1);margin-bottom:24px}
.card-header{padding:18px 24px;border-bottom:1px solid rgba(0,255,65,0.1);display:flex;justify-content:space-between;align-items:center}
.card-header h3{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.card-body{padding:0}
table{width:100%;border-collapse:collapse}
th{padding:12px 16px;font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--cinza);text-align:left;border-bottom:1px solid rgba(0,255,65,0.08);background:var(--preto3)}
td{padding:14px 16px;font-size:.78rem;border-bottom:1px solid rgba(0,255,65,0.05);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(0,255,65,0.02)}
.badge{display:inline-block;padding:3px 10px;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:1px}
.badge-ativo{background:rgba(0,255,65,0.15);color:var(--verde);border:1px solid rgba(0,255,65,0.3)}
.badge-trial{background:rgba(255,187,0,0.15);color:var(--amarelo);border:1px solid rgba(255,187,0,0.3)}
.badge-inativo{background:rgba(255,68,68,0.15);color:var(--vermelho);border:1px solid rgba(255,68,68,0.3)}
.btn-sm{padding:5px 12px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;border:none;cursor:pointer;transition:.2s}
.btn-verde{background:var(--verde);color:var(--preto)}
.btn-verde:hover{background:var(--verde2)}
.btn-vermelho{background:transparent;color:var(--vermelho);border:1px solid var(--vermelho)}
.btn-vermelho:hover{background:var(--vermelho);color:var(--branco)}
.btn-cinza{background:transparent;color:var(--cinza);border:1px solid rgba(255,255,255,0.1)}
.btn-cinza:hover{border-color:var(--verde);color:var(--verde)}
/* MODAL CLIENTE */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:200;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal-overlay.ativo{display:flex}
.modal{background:var(--preto2);border:2px solid var(--verde);padding:36px;max-width:640px;width:95%;max-height:90vh;overflow-y:auto;position:relative}
.modal-close{position:absolute;top:14px;right:18px;background:none;border:none;color:var(--cinza);font-size:1.4rem;cursor:pointer}
.modal-close:hover{color:var(--verde)}
.modal h3{font-size:1rem;font-weight:900;text-transform:uppercase;color:var(--verde);margin-bottom:20px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--cinza);margin-bottom:5px}
.form-group input,.form-group select,.form-group textarea{width:100%;background:var(--preto3);border:1px solid rgba(0,255,65,0.2);color:var(--branco);padding:10px 12px;font-size:.82rem;outline:none;transition:.2s;font-family:'Segoe UI',sans-serif}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--verde)}
.form-group select option{background:var(--preto3)}
.form-group textarea{resize:vertical;min-height:80px}
.btn-full{width:100%;background:var(--verde);color:var(--preto);border:none;padding:12px;font-size:.82rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:.2s;margin-top:8px}
.btn-full:hover{background:var(--verde2)}
/* LOGS */
.log-item{padding:12px 16px;border-bottom:1px solid rgba(0,255,65,0.05);display:flex;gap:16px;align-items:flex-start}
.log-item:last-child{border-bottom:none}
.log-data{font-size:.65rem;color:var(--cinza);white-space:nowrap;padding-top:2px}
.log-acao{font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--verde);white-space:nowrap}
.log-info{font-size:.75rem;color:rgba(240,240,240,.6)}
/* NOVO CLIENTE */
.btn-novo{background:var(--verde);color:var(--preto);border:none;padding:9px 18px;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:.2s}
.btn-novo:hover{background:var(--verde2)}
.msg{padding:10px 14px;font-size:.78rem;margin-bottom:16px;border-left:3px solid var(--verde);background:rgba(0,255,65,0.05)}
.msg.erro{border-color:var(--vermelho);background:rgba(255,68,68,0.05)}
@media(max-width:900px){
  .sidebar{display:none}
  .main{margin-left:0}
  .metrics{grid-template-columns:1fr 1fr}
  .grid2{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <h1>RSNews<span>Importer</span></h1>
    <p>Painel Admin</p>
  </div>
  <nav class="sidebar-nav">
    <button class="nav-item ativo" onclick="trocarSecao('dashboard')"><span class="icon">📊</span> Dashboard</button>
    <button class="nav-item" onclick="trocarSecao('clientes')"><span class="icon">👥</span> Clientes</button>
    <button class="nav-item" onclick="trocarSecao('novo')"><span class="icon">➕</span> Novo Cliente</button>
    <button class="nav-item" onclick="trocarSecao('logs')"><span class="icon">📋</span> Logs</button>
  </nav>
  <div class="sidebar-footer">
    <a href="#" onclick="fazerLogout()">🚪 Sair</a>
  </div>
</aside>

<!-- MAIN -->
<main class="main">

  <div class="topbar">
    <h2 id="titulo-secao">Dashboard</h2>
    <span><?= date('d/m/Y H:i') ?> · Admin</span>
  </div>

  <!-- ── DASHBOARD ── -->
  <section class="section ativa" id="sec-dashboard">
    <div class="metrics">
      <div class="metric">
        <span class="metric-valor"><?= $total ?></span>
        <span class="metric-label">Total de clientes</span>
      </div>
      <div class="metric">
        <span class="metric-valor"><?= $ativos ?></span>
        <span class="metric-label">Clientes ativos</span>
      </div>
      <div class="metric amarelo">
        <span class="metric-valor"><?= $trial ?></span>
        <span class="metric-label">Em trial</span>
      </div>
      <div class="metric">
        <span class="metric-valor">R$<?= number_format($receita, 0, ',', '.') ?></span>
        <span class="metric-label">Receita mensal</span>
      </div>
    </div>

    <!-- Últimos clientes -->
    <div class="card">
      <div class="card-header">
        <h3>🆕 Últimos cadastros</h3>
      </div>
      <div class="card-body">
        <table>
          <thead><tr>
            <th>Portal</th><th>Nome</th><th>Plano</th><th>Status</th><th>Cadastro</th>
          </tr></thead>
          <tbody>
            <?php foreach(array_slice($clientes,0,8) as $cl): ?>
            <tr>
              <td><?= htmlspecialchars($cl['portal']) ?></td>
              <td><?= htmlspecialchars($cl['nome']) ?></td>
              <td><?= htmlspecialchars($cl['plano']) ?></td>
              <td>
                <span class="badge badge-<?= $cl['status'] ?>">
                  <?= $cl['status'] === 'trial' ? '⏳ Trial' : ($cl['status'] === 'ativo' ? '✅ Ativo' : '❌ Inativo') ?>
                </span>
              </td>
              <td><?= date('d/m/Y', strtotime($cl['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($clientes)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--cinza);padding:32px">Nenhum cliente cadastrado ainda.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Logs recentes -->
    <div class="card">
      <div class="card-header"><h3>📋 Atividade recente</h3></div>
      <div class="card-body">
        <?php foreach(array_slice($logs,0,8) as $log): ?>
        <div class="log-item">
          <span class="log-data"><?= $log['data'] ?></span>
          <span class="log-acao"><?= htmlspecialchars($log['acao']) ?></span>
          <span class="log-info"><?= htmlspecialchars(json_encode($log['dados'], JSON_UNESCAPED_UNICODE)) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($logs)): ?>
        <div style="padding:24px;text-align:center;color:var(--cinza);font-size:.78rem">Nenhuma atividade registrada.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ── CLIENTES ── -->
  <section class="section" id="sec-clientes">
    <div class="card">
      <div class="card-header">
        <h3>👥 Todos os clientes (<?= $total ?>)</h3>
        <button class="btn-novo" onclick="trocarSecao('novo')">+ Novo cliente</button>
      </div>
      <div class="card-body">
        <div id="msg-clientes"></div>
        <table>
          <thead><tr>
            <th>Portal</th><th>E-mail</th><th>Plano</th><th>Status</th><th>Trial até</th><th>Ações</th>
          </tr></thead>
          <tbody id="tabela-clientes">
            <?php foreach($clientes as $cl): ?>
            <tr id="row-<?= $cl['id'] ?>">
              <td><strong><?= htmlspecialchars($cl['portal']) ?></strong><br><small style="color:var(--cinza)"><?= htmlspecialchars($cl['nome']) ?></small></td>
              <td><?= htmlspecialchars($cl['email']) ?><br><small style="color:var(--cinza)"><?= htmlspecialchars($cl['whatsapp']) ?></small></td>
              <td><?= htmlspecialchars($cl['plano']) ?></td>
              <td>
                <span class="badge badge-<?= $cl['status'] ?>" id="badge-<?= $cl['id'] ?>">
                  <?= $cl['status'] === 'trial' ? '⏳ Trial' : ($cl['status'] === 'ativo' ? '✅ Ativo' : '❌ Inativo') ?>
                </span>
              </td>
              <td><?= !empty($cl['trial_ate']) ? date('d/m/Y', strtotime($cl['trial_ate'])) : '—' ?></td>
              <td style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn-sm btn-cinza" onclick="verCliente('<?= $cl['id'] ?>')">👁 Ver</button>
                <button class="btn-sm btn-verde" onclick="toggleCliente('<?= $cl['id'] ?>')">⚡ Toggle</button>
                <button class="btn-sm btn-cinza" onclick="instalarPlugin('<?= $cl['id'] ?>')">🔌 Plugin</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($clientes)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--cinza);padding:32px">Nenhum cliente ainda.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ── NOVO CLIENTE ── -->
  <section class="section" id="sec-novo">
    <div class="card" style="max-width:600px">
      <div class="card-header"><h3>➕ Cadastrar novo cliente</h3></div>
      <div class="card-body" style="padding:28px">
        <div id="msg-novo"></div>
        <form id="formNovoCliente">
          <div class="grid2">
            <div class="form-group">
              <label>Nome do portal</label>
              <input type="text" name="portal" placeholder="Ex: Voz do Litoral" required>
            </div>
            <div class="form-group">
              <label>Nome do responsável</label>
              <input type="text" name="nome" placeholder="Nome completo" required>
            </div>
          </div>
          <div class="grid2">
            <div class="form-group">
              <label>E-mail</label>
              <input type="email" name="email" placeholder="email@portal.com.br" required>
            </div>
            <div class="form-group">
              <label>WhatsApp</label>
              <input type="tel" name="whatsapp" placeholder="(12) 99999-9999" required>
            </div>
          </div>
          <div class="grid2">
            <div class="form-group">
              <label>Plano</label>
              <select name="plano">
                <option value="Starter">Starter — R$197/mês</option>
                <option value="Pro" selected>Pro — R$397/mês</option>
                <option value="Agência">Agência — R$797/mês</option>
              </select>
            </div>
            <div class="form-group">
              <label>URL WordPress</label>
              <input type="url" name="wp_url" placeholder="https://portal.com.br">
            </div>
          </div>
          <button type="submit" class="btn-full">✅ Cadastrar cliente</button>
        </form>
      </div>
    </div>
  </section>

  <!-- ── LOGS ── -->
  <section class="section" id="sec-logs">
    <div class="card">
      <div class="card-header"><h3>📋 Log de atividades</h3></div>
      <div class="card-body">
        <?php foreach($logs as $log): ?>
        <div class="log-item">
          <span class="log-data"><?= $log['data'] ?></span>
          <span class="log-acao"><?= htmlspecialchars($log['acao']) ?></span>
          <span class="log-info"><?= htmlspecialchars(json_encode($log['dados'], JSON_UNESCAPED_UNICODE)) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($logs)): ?>
        <div style="padding:24px;text-align:center;color:var(--cinza);font-size:.78rem">Nenhuma atividade registrada.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

</main>

<!-- MODAL VER CLIENTE -->
<div class="modal-overlay" id="modalCliente">
  <div class="modal">
    <button class="modal-close" onclick="fecharModal()">✕</button>
    <h3>📋 Detalhes do cliente</h3>
    <div id="modal-conteudo" style="font-size:.8rem;line-height:1.8;color:rgba(240,240,240,.8)"></div>
  </div>
</div>

<script>
const TITULOS = {
  dashboard: 'Dashboard',
  clientes:  'Clientes',
  novo:      'Novo Cliente',
  logs:      'Logs'
};

function trocarSecao(id){
  document.querySelectorAll('.section').forEach(s => s.classList.remove('ativa'));
  document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('ativo'));
  document.getElementById('sec-' + id).classList.add('ativa');
  document.querySelectorAll('.nav-item').forEach(b => {
    if(b.textContent.toLowerCase().includes(TITULOS[id].toLowerCase().substring(0,4)))
      b.classList.add('ativo');
  });
  document.getElementById('titulo-secao').textContent = TITULOS[id];
}

function fecharModal(){
  document.getElementById('modalCliente').classList.remove('ativo');
}
document.getElementById('modalCliente').addEventListener('click', function(e){
  if(e.target === this) fecharModal();
});

async function verCliente(id){
  const res  = await fetch('/rsnewsauto/api.php?action=listar_clientes&admin_token=RSNews%402026');
  const json = await res.json();
  const cl   = json.clientes.find(c => c.id === id);
  if(!cl) return;

  document.getElementById('modal-conteudo').innerHTML = `
    <p><strong style="color:var(--verde)">Portal:</strong> ${cl.portal}</p>
    <p><strong style="color:var(--verde)">Responsável:</strong> ${cl.nome}</p>
    <p><strong style="color:var(--verde)">E-mail:</strong> ${cl.email}</p>
    <p><strong style="color:var(--verde)">WhatsApp:</strong> ${cl.whatsapp}</p>
    <p><strong style="color:var(--verde)">Plano:</strong> ${cl.plano}</p>
    <p><strong style="color:var(--verde)">Status:</strong> ${cl.status}</p>
    <p><strong style="color:var(--verde)">Trial até:</strong> ${cl.trial_ate || '—'}</p>
    <hr style="border-color:rgba(0,255,65,0.1);margin:14px 0">
    <p><strong style="color:var(--verde)">WordPress:</strong> ${cl.wordpress.url || 'Não configurado'}</p>
    <p><strong style="color:var(--verde)">Palavras-chave:</strong> ${cl.automacao.palavras_chave.join(', ') || 'Nenhuma'}</p>
    <p><strong style="color:var(--verde)">Sites fonte:</strong> ${cl.automacao.sites_fonte.join(', ') || 'Nenhum'}</p>
    <p><strong style="color:var(--verde)">Automação ativa:</strong> ${cl.automacao.ativo ? '✅ Sim' : '❌ Não'}</p>
    <p><strong style="color:var(--verde)">Token:</strong> <small>${cl.token}</small></p>
    <p><strong style="color:var(--verde)">ID:</strong> <small>${cl.id}</small></p>
  `;
  document.getElementById('modalCliente').classList.add('ativo');
}

async function toggleCliente(id){
  if(!confirm('Alternar status do cliente?')) return;
  const res  = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_cliente', admin_token:'RSNews@2026', id})
  });
  const json = await res.json();
  if(json.status === 'ok'){
    const badge = document.getElementById('badge-' + id);
    const s = json.novo_status;
    badge.className = 'badge badge-' + s;
    badge.textContent = s === 'ativo' ? '✅ Ativo' : s === 'trial' ? '⏳ Trial' : '❌ Inativo';
    mostrarMsg('msg-clientes', `Status atualizado: ${s}`);
  }
}

async function instalarPlugin(id){
  if(!confirm('Instalar plugin automaticamente no WordPress do cliente?')) return;
  mostrarMsg('msg-clientes', '⏳ Instalando plugin... aguarde.');
  const res  = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'instalar_plugin', admin_token:'RSNews@2026', id})
  });
  const json = await res.json();
  if(json.status === 'ok'){
    mostrarMsg('msg-clientes', '✅ Plugin instalado com sucesso!');
  } else {
    mostrarMsg('msg-clientes', '❌ Erro: ' + (json.erro || 'Falha na instalação.'), true);
  }
}

function mostrarMsg(id, msg, erro=false){
  const el = document.getElementById(id);
  el.innerHTML = `<div class="msg ${erro?'erro':''}" style="margin-bottom:16px">${msg}</div>`;
  setTimeout(() => el.innerHTML = '', 4000);
}

// NOVO CLIENTE
document.getElementById('formNovoCliente').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn  = e.target.querySelector('button');
  btn.textContent = 'Cadastrando...'; btn.disabled = true;
  const data = Object.fromEntries(new FormData(e.target));
  const res  = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'cadastrar', ...data})
  });
  const json = await res.json();
  if(json.status === 'ok'){
    mostrarMsg('msg-novo', `✅ Cliente cadastrado! ID: ${json.id}`);
    e.target.reset();
    setTimeout(() => trocarSecao('clientes'), 2000);
  } else {
    mostrarMsg('msg-novo', '❌ ' + (json.erro || 'Erro ao cadastrar.'), true);
  }
  btn.textContent = '✅ Cadastrar cliente'; btn.disabled = false;
});

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
