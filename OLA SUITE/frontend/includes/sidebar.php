<?php
$usuario     = usuarioLogado();
$paginaAtual = basename($_SERVER['PHP_SELF'], '.php');

$itensMenu = [
    ['href' => 'inbox',       'icone' => '💬', 'label' => 'Atendimentos',  'nivel' => NIVEL_ATENDENTE],
    ['href' => 'contatos',    'icone' => '👤', 'label' => 'Contatos',      'nivel' => NIVEL_ATENDENTE],
    ['href' => 'departamentos','icone'=> '🏢', 'label' => 'Departamentos', 'nivel' => NIVEL_SUPERVISOR],
    ['href' => 'chatbot',     'icone' => '🤖', 'label' => 'Chatbot',       'nivel' => NIVEL_SUPERVISOR],
    ['href' => 'relatorios',  'icone' => '📊', 'label' => 'Relatórios',    'nivel' => NIVEL_SUPERVISOR],
    ['href' => 'whatsapp',    'icone' => '📱', 'label' => 'WhatsApp',      'nivel' => NIVEL_ADMIN],
    ['href' => 'admin',       'icone' => '⚙️', 'label' => 'Administração', 'nivel' => NIVEL_ADMIN],
];
?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="sidebar-titulo">Menu</span>
  </div>

  <ul class="sidebar-nav">
    <?php foreach ($itensMenu as $item): ?>
      <?php if (!temPermissao($item['nivel'])) continue; ?>
      <li>
        <a href="<?= APP_URL ?>/<?= $item['href'] ?>.php"
           class="sidebar-link <?= $paginaAtual === $item['href'] ? 'ativo' : '' ?>">
          <span class="sidebar-icone"><?= $item['icone'] ?></span>
          <span class="sidebar-label"><?= $item['label'] ?></span>
          <?php if ($item['href'] === 'inbox'): ?>
            <span class="sidebar-badge" id="sb-badge-inbox" style="display:none">0</span>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-user-info">
        <div class="sidebar-user-nome"><?= htmlspecialchars($usuario['nome'] ?? '') ?></div>
        <div class="sidebar-user-nivel"><?= ucfirst($usuario['nivel'] ?? '') ?></div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="sidebar-logout" title="Sair">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </a>
  </div>
</nav>
