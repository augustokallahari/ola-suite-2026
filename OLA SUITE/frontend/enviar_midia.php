<?php
// Handler dedicado para upload de mídia (multipart/form-data)
// Chamado via fetch com FormData pelo inbox.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$conversaId   = intval($_POST['conversa_id']    ?? 0);
$conteudo     = trim($_POST['conteudo']         ?? '');
$enviadoPorId = intval($_POST['enviado_por_id'] ?? 0);

if (!$conversaId || empty($_FILES['arquivo'])) {
    jsonResponse(false, null, 'Dados incompletos', 400);
}

$arquivo    = $_FILES['arquivo'];
$tmpPath    = $arquivo['tmp_name'];
$nomeOrig   = basename($arquivo['name']);
$mime       = mime_content_type($tmpPath);
$tamanho    = $arquivo['size'];
$maxBytes   = 64 * 1024 * 1024; // 64MB

if ($tamanho > $maxBytes) {
    jsonResponse(false, null, 'Arquivo muito grande (máx. 64MB)', 400);
}

$ext      = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
$nomeGrav = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destDir  = __DIR__ . '/assets/uploads/';
$destPath = $destDir . $nomeGrav;

if (!is_dir($destDir)) mkdir($destDir, 0755, true);

if (!move_uploaded_file($tmpPath, $destPath)) {
    jsonResponse(false, null, 'Falha ao salvar arquivo', 500);
}

// Envia para o Node.js via cURL multipart
$resp = apiUpload('api/mensagens/enviar-midia', $destPath, [
    'conversa_id'    => $conversaId,
    'conteudo'       => $conteudo,
    'enviado_por_id' => $enviadoPorId,
]);

jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null, $resp['mensagem'] ?? '');
