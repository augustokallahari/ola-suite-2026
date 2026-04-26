<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redireciona se já estiver logado
if (estaLogado()) {
    header('Location: ' . APP_URL . '/inbox.php');
    exit;
}

// ── Handler AJAX ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!isset($dados['action'])) {
        jsonResponse(false, null, 'Ação inválida', 400);
    }

    switch ($dados['action']) {
        case 'login':
            $email = trim($dados['email'] ?? '');
            $senha = $dados['senha'] ?? '';

            if (!$email || !$senha) {
                jsonResponse(false, null, 'E-mail e senha são obrigatórios', 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, null, 'E-mail inválido', 400);
            }

            $resultado = autenticar($email, $senha);
            if ($resultado['sucesso']) {
                jsonResponse(true, [
                    'redirect' => APP_URL . '/inbox.php',
                    'usuario'  => $resultado['usuario'],
                ], 'Login realizado com sucesso');
            } else {
                jsonResponse(false, null, $resultado['mensagem'], 401);
            }
            break;

        default:
            jsonResponse(false, null, 'Ação não reconhecida', 400);
    }
}

$csrf = gerarCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Entrar — <?= APP_NAME ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #0f1117;
      --card:    #1a1d27;
      --border:  #2a2f45;
      --accent:  #4f6ef7;
      --accent2: #3a5ae8;
      --text:    #e8eaf0;
      --muted:   #8b90a7;
      --danger:  #ef4444;
      --success: #10b981;
      --input:   #21253a;
      --radius:  10px;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      -webkit-font-smoothing: antialiased;
    }

    /* Fundo animado */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 10%, rgba(79,110,247,.12) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 90%, rgba(16,185,129,.08) 0%, transparent 60%);
      pointer-events: none;
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      position: relative;
      z-index: 1;
    }

    /* Logo/cabeçalho */
    .login-brand {
      text-align: center;
      margin-bottom: 32px;
    }
    .login-brand-icon {
      font-size: 52px;
      display: block;
      margin-bottom: 10px;
      filter: drop-shadow(0 0 20px rgba(79,110,247,.4));
    }
    .login-brand-nome {
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -.5px;
      background: linear-gradient(135deg, #fff 0%, #8b90a7 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .login-brand-sub {
      font-size: 14px;
      color: var(--muted);
      margin-top: 4px;
    }

    /* Card */
    .login-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 36px;
      box-shadow:
        0 0 0 1px rgba(255,255,255,.04),
        0 24px 64px rgba(0,0,0,.5);
    }

    .login-titulo {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
    }
    .login-subtitulo {
      font-size: 13.5px;
      color: var(--muted);
      margin-bottom: 28px;
    }

    /* Grupo de campo */
    .campo {
      margin-bottom: 18px;
    }
    .campo label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
      margin-bottom: 6px;
    }
    .campo-input {
      position: relative;
    }
    .campo-input input {
      width: 100%;
      background: var(--input);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 11px 14px 11px 42px;
      color: var(--text);
      font-size: 14px;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .campo-input input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(79,110,247,.15);
    }
    .campo-input input::placeholder { color: var(--muted); }
    .campo-icone {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      pointer-events: none;
    }
    .campo-senha-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 2px;
    }
    .campo-senha-toggle:hover { color: var(--text); }

    /* Erro */
    .alerta-erro {
      display: none;
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.3);
      border-radius: var(--radius);
      padding: 11px 14px;
      color: #fca5a5;
      font-size: 13.5px;
      margin-bottom: 18px;
    }
    .alerta-erro.visivel { display: flex; align-items: center; gap: 8px; }

    /* Botão */
    .btn-entrar {
      width: 100%;
      padding: 12px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: background .2s, transform .1s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 6px;
    }
    .btn-entrar:hover:not(:disabled) { background: var(--accent2); }
    .btn-entrar:active { transform: scale(.99); }
    .btn-entrar:disabled { opacity: .6; cursor: not-allowed; }

    .spinner-btn {
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Rodapé */
    .login-footer {
      text-align: center;
      margin-top: 24px;
      font-size: 12px;
      color: var(--muted);
    }
  </style>
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <span class="login-brand-icon">💬</span>
    <div class="login-brand-nome"><?= APP_NAME ?></div>
    <div class="login-brand-sub">Plataforma de Atendimento Omnichannel</div>
  </div>

  <div class="login-card">
    <h1 class="login-titulo">Bem-vindo de volta</h1>
    <p class="login-subtitulo">Entre com suas credenciais para acessar o sistema</p>

    <!-- Alerta de erro -->
    <div class="alerta-erro" id="alerta-erro">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span id="msg-erro"></span>
    </div>

    <form id="form-login" novalidate>
      <input type="hidden" name="csrf" value="<?= $csrf ?>" />

      <div class="campo">
        <label for="email">E-mail</label>
        <div class="campo-input">
          <svg class="campo-icone" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
          <input type="email" id="email" name="email" placeholder="seu@email.com"
                 autocomplete="email" required />
        </div>
      </div>

      <div class="campo">
        <label for="senha">Senha</label>
        <div class="campo-input">
          <svg class="campo-icone" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input type="password" id="senha" name="senha" placeholder="••••••••"
                 autocomplete="current-password" required />
          <button type="button" class="campo-senha-toggle" onclick="toggleSenha()"
                  title="Mostrar/ocultar senha">
            <svg id="ico-olho" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-entrar" id="btn-entrar">
        <span class="spinner-btn" id="spinner-login"></span>
        <span id="txt-btn">Entrar</span>
      </button>
    </form>
  </div>

  <div class="login-footer">
    <?= APP_NAME ?> v<?= APP_VERSION ?> &mdash; Atendimento Inteligente
  </div>
</div>

<script>
const form    = document.getElementById('form-login');
const btnEntrar = document.getElementById('btn-entrar');
const spinner   = document.getElementById('spinner-login');
const txtBtn    = document.getElementById('txt-btn');
const alertaErro = document.getElementById('alerta-erro');
const msgErro    = document.getElementById('msg-erro');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('email').value.trim();
  const senha  = document.getElementById('senha').value;

  if (!email || !senha) {
    mostrarErro('Preencha e-mail e senha.');
    return;
  }

  setCarregando(true);
  ocultarErro();

  try {
    const resp = await fetch('login.php', {
      method:  'POST',
      headers: {
        'Content-Type':     'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ action: 'login', email, senha }),
    });

    const data = await resp.json();

    if (data.sucesso) {
      txtBtn.textContent = '✓ Entrando...';
      setTimeout(() => {
        window.location.href = data.dados.redirect;
      }, 400);
    } else {
      mostrarErro(data.mensagem || 'Credenciais inválidas.');
      setCarregando(false);
    }
  } catch (err) {
    mostrarErro('Falha na conexão. Tente novamente.');
    setCarregando(false);
  }
});

function setCarregando(on) {
  btnEntrar.disabled     = on;
  spinner.style.display  = on ? 'block' : 'none';
  txtBtn.textContent     = on ? 'Entrando...' : 'Entrar';
}

function mostrarErro(msg) {
  msgErro.textContent = msg;
  alertaErro.classList.add('visivel');
}
function ocultarErro() {
  alertaErro.classList.remove('visivel');
}

function toggleSenha() {
  const campo = document.getElementById('senha');
  campo.type  = campo.type === 'password' ? 'text' : 'password';
}

// Enter no campo email foca a senha
document.getElementById('email').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') document.getElementById('senha').focus();
});
</script>
</body>
</html>
