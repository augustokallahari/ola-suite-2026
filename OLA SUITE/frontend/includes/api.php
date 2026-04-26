<?php
require_once __DIR__ . '/config.php';

// ── Helper para chamar o backend Node.js ─────────────────────

function apiRequest(
    string $metodo,
    string $endpoint,
    array  $dados = [],
    bool   $retornarArray = true
): array|false {
    $url  = rtrim(NODE_API_URL, '/') . '/' . ltrim($endpoint, '/');
    $curl = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Api-Key: ' . NODE_API_KEY,
    ];

    $opcoes = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => strtoupper($metodo),
    ];

    if (!empty($dados) && in_array(strtoupper($metodo), ['POST', 'PUT', 'PATCH'])) {
        $opcoes[CURLOPT_POSTFIELDS] = json_encode($dados);
    } elseif (!empty($dados) && strtoupper($metodo) === 'GET') {
        $opcoes[CURLOPT_URL] = $url . '?' . http_build_query($dados);
    }

    curl_setopt_array($curl, $opcoes);

    $resposta   = curl_exec($curl);
    $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlErro   = curl_error($curl);
    curl_close($curl);

    if ($curlErro) {
        error_log('[API] cURL erro: ' . $curlErro . ' | URL: ' . $url);
        return ['sucesso' => false, 'mensagem' => 'Serviço indisponível', 'dados' => null];
    }

    $resultado = json_decode($resposta, true);
    if ($resultado === null) {
        error_log('[API] JSON inválido (HTTP ' . $httpCode . '): ' . $resposta);
        return ['sucesso' => false, 'mensagem' => 'Resposta inválida da API', 'dados' => null];
    }

    return $resultado;
}

function apiGet(string $endpoint, array $params = []): array {
    return apiRequest('GET', $endpoint, $params) ?: [];
}

function apiPost(string $endpoint, array $dados = []): array {
    return apiRequest('POST', $endpoint, $dados) ?: [];
}

function apiPut(string $endpoint, array $dados = []): array {
    return apiRequest('PUT', $endpoint, $dados) ?: [];
}

function apiDelete(string $endpoint): array {
    return apiRequest('DELETE', $endpoint) ?: [];
}

// Upload de arquivo para o Node.js via multipart
function apiUpload(string $endpoint, string $caminhoArquivo, array $campos = []): array {
    $url  = rtrim(NODE_API_URL, '/') . '/' . ltrim($endpoint, '/');
    $curl = curl_init();

    $postData = $campos;
    $postData['arquivo'] = new CURLFile($caminhoArquivo);

    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-Api-Key: ' . NODE_API_KEY,
        ],
    ]);

    $resposta = curl_exec($curl);
    curl_close($curl);

    return json_decode($resposta, true) ?: ['sucesso' => false, 'mensagem' => 'Erro no upload'];
}
