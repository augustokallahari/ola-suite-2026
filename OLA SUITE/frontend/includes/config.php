<?php
// ============================================================
//  OlaSuite — Configurações globais
// ============================================================

// Banco de dados
define('DB_HOST',    'localhost');
define('DB_NAME',    'chat-kallahari');
define('DB_USER',    'olasuite');          // usuário MySQL criado para a aplicação
define('DB_PASS',    'OlaSuite@2026!');   // senha definida ao criar o usuário MySQL
define('DB_CHARSET', 'utf8mb4');

// Node.js API (acesso interno — nunca exposto ao browser)
define('NODE_API_URL', 'http://127.0.0.1:3000');
define('NODE_API_KEY', 'Kl4rH4r1_OlA_S3cr3t_2026_!#xZ');  // mesma que API_SECRET no .env

// App
define('APP_NAME',    'OlaSuite');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'https://www.kallahari.com.br/olasuite/frontend');

// Sessão
define('SESSION_NAME',    'olasuite_session');
define('SESSION_TIMEOUT', 3600 * 8); // 8 horas

// Níveis de acesso (hierarquia crescente)
define('NIVEL_ATENDENTE',  'atendente');
define('NIVEL_SUPERVISOR', 'supervisor');
define('NIVEL_ADMIN',      'admin');

date_default_timezone_set('America/Sao_Paulo');

// Inicia sessão segura (uma única vez)
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => true,   // HTTPS em produção
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
