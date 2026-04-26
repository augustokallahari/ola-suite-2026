<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

destruirSessao();
header('Location: ' . APP_URL . '/login.php?saiu=1');
exit;
