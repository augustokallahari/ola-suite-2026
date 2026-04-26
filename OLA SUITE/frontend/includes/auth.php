<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Verifica se há sessão válida ──────────────────────────────
function estaLogado(): bool {
    if (empty($_SESSION['usuario'])) return false;
    // Timeout de inatividade
    if (!empty($_SESSION['ultimo_acesso'])) {
        if (time() - $_SESSION['ultimo_acesso'] > SESSION_TIMEOUT) {
            destruirSessao();
            return false;
        }
    }
    $_SESSION['ultimo_acesso'] = time();
    return true;
}

// ── Retorna dados do usuário logado ──────────────────────────
function usuarioLogado(): ?array {
    return $_SESSION['usuario'] ?? null;
}

function usuarioId(): ?int {
    return $_SESSION['usuario']['id'] ?? null;
}

function usuarioNivel(): ?string {
    return $_SESSION['usuario']['nivel'] ?? null;
}

// ── Verifica se o nível atual tem permissão ──────────────────
function temPermissao(string $nivelMinimo): bool {
    $hierarquia = [
        NIVEL_ATENDENTE  => 1,
        NIVEL_SUPERVISOR => 2,
        NIVEL_ADMIN      => 3,
    ];
    $nivelAtual = usuarioNivel();
    return ($hierarquia[$nivelAtual] ?? 0) >= ($hierarquia[$nivelMinimo] ?? 99);
}

function eAdmin(): bool      { return temPermissao(NIVEL_ADMIN); }
function eSupervisor(): bool { return temPermissao(NIVEL_SUPERVISOR); }

// ── Exige login (redireciona se não estiver logado) ──────────
function exigirLogin(string $redirect = ''): void {
    if (!estaLogado()) {
        $url = APP_URL . '/login.php';
        if ($redirect) $url .= '?redirect=' . urlencode($redirect);
        header('Location: ' . $url);
        exit;
    }
}

// ── Exige nível mínimo ────────────────────────────────────────
function exigirNivel(string $nivelMinimo): void {
    exigirLogin();
    if (!temPermissao($nivelMinimo)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

// ── Autentica usuário (login) ────────────────────────────────
function autenticar(string $email, string $senha): array {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM chat_usuarios WHERE email = ? AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([trim($email)]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['sucesso' => false, 'mensagem' => 'E-mail ou senha inválidos'];
        }

        if (!password_verify($senha, $usuario['senha'])) {
            registrarLog($usuario['id'], 'login_falha', null, null, 'Senha incorreta');
            return ['sucesso' => false, 'mensagem' => 'E-mail ou senha inválidos'];
        }

        // Atualiza último acesso e status
        $db->prepare(
            "UPDATE chat_usuarios SET ultimo_acesso = NOW(), status = 'online' WHERE id = ?"
        )->execute([$usuario['id']]);

        unset($usuario['senha']);
        iniciarSessaoUsuario($usuario);
        registrarLog($usuario['id'], 'login_sucesso');

        return ['sucesso' => true, 'usuario' => $usuario];

    } catch (PDOException $e) {
        error_log('[Auth] Erro DB: ' . $e->getMessage());
        return ['sucesso' => false, 'mensagem' => 'Erro interno. Tente novamente.'];
    }
}

// ── Inicia sessão PHP do usuário ─────────────────────────────
function iniciarSessaoUsuario(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION['usuario']      = $usuario;
    $_SESSION['ultimo_acesso'] = time();
}

// ── Destrói sessão ────────────────────────────────────────────
function destruirSessao(): void {
    try {
        if (!empty($_SESSION['usuario']['id'])) {
            $db = getDB();
            $db->prepare(
                "UPDATE chat_usuarios SET status = 'offline' WHERE id = ?"
            )->execute([$_SESSION['usuario']['id']]);
        }
    } catch (PDOException) {}

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── Log de auditoria ─────────────────────────────────────────
function registrarLog(
    ?int $usuarioId,
    string $acao,
    ?string $tabela = null,
    ?int $registroId = null,
    ?string $detalhe = null
): void {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO chat_logs (usuario_id, acao, tabela, registro_id, detalhe, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $usuarioId, $acao, $tabela, $registroId, $detalhe,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException) {}
}

// ── Resposta JSON padronizada (para handlers AJAX) ───────────
function jsonResponse(bool $sucesso, mixed $dados = null, string $mensagem = '', int $httpCode = 200): never {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso'  => $sucesso,
        'mensagem' => $mensagem,
        'dados'    => $dados,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CSRF Token ───────────────────────────────────────────────
function gerarCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCSRF(string $token): bool {
    return !empty($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
