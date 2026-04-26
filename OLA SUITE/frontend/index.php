<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Redireciona para inbox se logado, senão para login
if (estaLogado()) {
    header('Location: ' . APP_URL . '/inbox.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
