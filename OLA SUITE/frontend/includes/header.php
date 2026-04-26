<?php
require_once __DIR__ . '/auth.php';
$usuario = usuarioLogado();
$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($tituloPagina ?? APP_NAME) ?> — <?= APP_NAME ?></title>
  <link rel="icon" href="<?= APP_URL ?>/assets/img/favicon.png" type="image/png" />
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css" />
  <!-- Socket.IO (carregado do proxy reverso Apache → Node.js) -->
  <script src="/socket.io/socket.io.js"></script>
  <script>
    // URL do Socket.IO resolvida pelo servidor — nunca expõe porta interna ao browser
    window.OLA_SOCKET_URL = '<?= rtrim(str_replace(['http://', 'https://'], ['ws://', 'wss://'], APP_URL), '/frontend') ?>';
  </script>
</head>
<body class="dark">

<div id="app-wrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="btn-menu-toggle" onclick="toggleSidebar()" title="Menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="6"  x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <a href="<?= APP_URL ?>/inbox.php" class="topbar-brand">
        <span class="brand-icon">💬</span>
        <span class="brand-name"><?= APP_NAME ?></span>
      </a>
    </div>

    <div class="topbar-right">
      <!-- Badge de não lidas -->
      <div class="topbar-badge" id="badge-nao-lidas" style="display:none">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span class="badge-count" id="count-nao-lidas">0</span>
      </div>

      <!-- Status do usuário -->
      <div class="user-status-wrap" id="btn-status-wrap">
        <button class="btn-user-status" onclick="toggleMenuStatus()">
          <span class="status-dot" id="dot-status-usuario"></span>
          <span class="user-nome-top"><?= htmlspecialchars($usuario['nome'] ?? '') ?></span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>
        <div class="menu-status" id="menu-status" style="display:none">
          <button onclick="alterarStatus('online')">🟢 Online</button>
          <button onclick="alterarStatus('ausente')">🟡 Ausente</button>
          <button onclick="alterarStatus('offline')">⚫ Offline</button>
          <hr/>
          <a href="<?= APP_URL ?>/logout.php">🚪 Sair</a>
        </div>
      </div>
    </div>
  </header>

  <div class="main-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content" id="main-content">
