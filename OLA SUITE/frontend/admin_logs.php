<?php
// Endpoint dedicado para logs de auditoria (leitura direta do banco)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

exigirLogin();
exigirNivel(NIVEL_ADMIN);

if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(403);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->query(
        "SELECT l.*, u.nome AS usuario_nome
         FROM chat_logs l
         LEFT JOIN chat_usuarios u ON u.id = l.usuario_id
         ORDER BY l.criado_em DESC
         LIMIT 200"
    );
    jsonResponse(true, $stmt->fetchAll());
} catch (Exception $e) {
    jsonResponse(false, [], 'Erro ao buscar logs: ' . $e->getMessage());
}
