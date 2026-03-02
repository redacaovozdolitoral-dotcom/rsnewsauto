<?php
// ARQUIVO: /rsnewsauto/painel/login.php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Se já está logado redireciona
if (isset($_SESSION['tipo'])){
    if ($_SESSION['tipo'] === 'admin') header('Location: admin.php');
    else header('Location: cliente.php');
    exit;
}

$msg_status = '';
if (isset($_GET['status'])){
    $msgs = [
        'sucesso'  => '✅ Pagamento aprovado! Sua conta está ativa.',
        'erro'     => '❌ Erro no pagamento. Tente novamente ou fale com o suporte.',
        'pendente' => '⏳ Pagamento pendente. Você receberá confirmação em breve.',
        'expirado' => '⚠️ Sessão expirada. Faça login novamente.',
    ];
    $msg_status = $msgs[$_GET['status']] ?? '';
}
$novo = isset($_GET['novo']) ? true : false;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RSNEWS IMPORTER — Painel</title>
<style>
:root{--verde:#00ff41;--verde2:#39d353;--preto:#0a0a0a;--preto2:#111;--preto3:#1a1a1a;--cinza:#888;--branco:#f0f0f0}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--preto);color:var(--branco);font-family:'Segoe UI',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.container{width:100%;max-width:420px}
.logo{text-align:center;margin-bottom:36px}
.logo h1{font-size:1.6rem;font-weight:900;text-transform:uppercase;letter-spacing:3px;color:var(--verde)}
.logo h1 span{color:var(--branco)}
.logo p{font-size:.7rem;color:var(--cinza);text-transform:uppercase;letter-spacing:2px;margin-top:4px}
.card{background:var(--preto2);border:2px solid rgba(0,255,65,0.2);padding:40px 36px}
.card h2{font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;margin-bottom:28px;color:var(--branco)}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--cinza);margin-bottom:6px}
.form-group input{width:100%;background:var(--preto3);border:1px solid rgba(0,255,65,0.2);color:var(--branco);padding:12px 14px;font-size:.9rem;outline:none;transition:.2s;font-family:'Segoe UI',sans-serif}
.form-group input:focus{border-color:var(--verde);box-shadow:0 0 10px rgba(0,255,65,0.1)}
.btn{width:100%;background:var(--verde);color:var(--preto);border:none;padding:14px;font-size:.85rem;font-weight:900;text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:.2s;margin-top:8px}
.btn:hover{background:var(--verde2)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.msg{padding:12px 14px;font-size:.8rem;margin-bottom:20px;border-left:3px solid var(--verde);background:rgba(0,255,65,0.05);line-height:1.5}
.msg.erro{border-color:#ff4444;background:rgba(255,68,68,0.05)}
.divider{text-align:center;margin:20px 0;position:relative}
.divider::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:rgba(0,255,65,0.1)}
.divider span{background:var(--preto2);padding:0 12px;font-size:.7rem;color:var(--cinza);text-transform:uppercase;letter-spacing:1px;position:relative}
.link{color:var(--verde);text-decoration:none;font-size:.75rem;text-transform:uppercase;letter-spacing:1px}
.link:hover{text-decoration:underline}
.footer-link{text-align:center;margin-top:20px;font-size:.7rem;color:var(--cinza)}
.spinner{display:none;width:16px;height:16px;border:2px solid var(--preto);border-top-color:var(--preto);border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}
.tab-btns{display:flex;margin-bottom:28px;border-bottom:2px solid rgba(0,255,65,0.1)}
.tab-btn{flex:1;background:none;border:none;color:var(--cinza);padding:10px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:.2s;border-bottom:2px solid transparent;margin-bottom:-2px}
.tab-btn.ativo{color:var(--verde);border-bottom-color:var(--verde)}
.tab{display:none}.tab.ativo{display:block}
</style>
</head>
<body>
<div class="container">

  <div class="logo">
    <h1>RSNews<span>Importer</span></h1>
    <p>Painel de controle</p>
  </div>

  <?php if ($msg_status): ?>
    <div class="msg <?= strpos($msg_status,'❌')!==false ? 'erro' : '' ?>">
      <?= htmlspecialchars($msg_status) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="tab-btns">
      <button class="tab-btn <?= !$novo ? 'ativo' : '' ?>" onclick="trocarTab('login')">Entrar</button>
      <button class="tab-btn <?= $novo ? 'ativo' : '' ?>" onclick="trocarTab('senha')">Primeiro acesso</button>
    </div>

    <!-- TAB LOGIN -->
    <div class="tab <?= !$novo ? 'ativo' : '' ?>" id="tab-login">
      <div id="msg-login"></div>
      <form id="formLogin">
        <div class="form-group">
          <label>E-mail</label>
          <input type="email" id="login-email" placeholder="seu@email.com"
            value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Senha</label>
          <input type="password" id="login-senha" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn" id="btnLogin">Entrar no painel</button>
      </form>
      <div class="footer-link" style="margin-top:16px">
        <a href="#" class="link" onclick="trocarTab('senha')">Primeiro acesso? Crie sua senha</a>
      </div>
    </div>

    <!-- TAB PRIMEIRO ACESSO / CRIAR SENHA -->
    <div class="tab <?= $novo ? 'ativo' : '' ?>" id="tab-senha">
      <div id="msg-senha"></div>
      <p style="font-size:.78rem;color:var(--cinza);margin-bottom:20px;line-height:1.6">
        Se você acabou de se cadastrar, crie sua senha de acesso ao painel.
      </p>
      <form id="formCriarSenha">
        <div class="form-group">
          <label>E-mail cadastrado</label>
          <input type="email" id="cs-email" placeholder="seu@email.com"
            value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Nova senha (mín. 8 caracteres)</label>
          <input type="password" id="cs-senha" placeholder="Crie uma senha forte" minlength="8" required>
        </div>
        <div class="form-group">
          <label>Confirmar senha</label>
          <input type="password" id="cs-confirma" placeholder="Repita a senha" required>
        </div>
        <button type="submit" class="btn" id="btnCriarSenha">Criar senha e entrar</button>
      </form>
      <div class="footer-link" style="margin-top:16px">
        <a href="#" class="link" onclick="trocarTab('login')">← Voltar para login</a>
      </div>
    </div>

  </div>

  <div class="footer-link">
    <a href="/rsnewsauto/" class="link">← Voltar para o site</a>
    &nbsp;·&nbsp;
    <a href="https://wa.me/5512996813069" class="link" target="_blank">Suporte</a>
  </div>

</div>

<script>
function trocarTab(tab){
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('ativo'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('ativo'));
  document.getElementById('tab-' + tab).classList.add('ativo');
  document.querySelectorAll('.tab-btn').forEach(b => {
    if(b.textContent.toLowerCase().includes(tab === 'login' ? 'entrar' : 'primeiro')) b.classList.add('ativo');
  });
}

function mostrarMsg(id, msg, erro=false){
  const el = document.getElementById(id);
  el.innerHTML = `<div class="msg ${erro?'erro':''}">${msg}</div>`;
}

// LOGIN
document.getElementById('formLogin').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnLogin');
  btn.textContent = 'Entrando...'; btn.disabled = true;

  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'login',
      email: document.getElementById('login-email').value,
      senha: document.getElementById('login-senha').value
    })
  });
  const json = await res.json();

  if(json.status === 'ok'){
    // Salva token no localStorage
    if(json.token) localStorage.setItem('rsnews_token', json.token);
    if(json.id)    localStorage.setItem('rsnews_id', json.id);
    localStorage.setItem('rsnews_tipo', json.tipo);
    window.location.href = json.redirect;
  } else {
    mostrarMsg('msg-login', json.erro || 'Erro ao fazer login.', true);
    btn.textContent = 'Entrar no painel'; btn.disabled = false;
  }
});

// CRIAR SENHA
document.getElementById('formCriarSenha').addEventListener('submit', async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnCriarSenha');
  const senha = document.getElementById('cs-senha').value;
  const confirma = document.getElementById('cs-confirma').value;

  if(senha !== confirma){
    mostrarMsg('msg-senha','As senhas não conferem.', true); return;
  }
  btn.textContent = 'Salvando...'; btn.disabled = true;

  const res = await fetch('/rsnewsauto/api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action: 'criar_senha',
      email: document.getElementById('cs-email').value,
      senha: senha
    })
  });
  const json = await res.json();

  if(json.status === 'ok'){
    if(json.token) localStorage.setItem('rsnews_token', json.token);
    if(json.id)    localStorage.setItem('rsnews_id', json.id);
    localStorage.setItem('rsnews_tipo', 'cliente');
    mostrarMsg('msg-senha','✅ Senha criada! Redirecionando...');
    setTimeout(() => window.location.href = '/rsnewsauto/painel/cliente.php', 1500);
  } else {
    mostrarMsg('msg-senha', json.erro || 'Erro ao criar senha.', true);
    btn.textContent = 'Criar senha e entrar'; btn.disabled = false;
  }
});
</script>
</body>
</html>
