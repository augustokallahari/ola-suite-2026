<?php
$tituloPagina = 'Acesso Negado';
require_once __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:60vh;gap:16px;text-align:center;color:#8b90a7;">
  <span style="font-size:64px">🔒</span>
  <h2 style="font-size:22px;color:#e8eaf0">Acesso Negado</h2>
  <p>Você não tem permissão para acessar esta página.</p>
  <a href="<?= APP_URL ?>/inbox.php" class="btn btn-primary">Voltar ao início</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
