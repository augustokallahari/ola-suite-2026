<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/db.php';

exigirLogin();
$usuario      = usuarioLogado();
$tituloPagina = 'Atendimentos';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? ($_GET['action'] ?? '');

    switch ($action) {

        case 'listar_conversas':
            $filtros = [
                'status'          => $dados['status']          ?? '',
                'departamento_id' => $dados['departamento_id'] ?? '',
                'busca'           => $dados['busca']           ?? '',
                'limite'          => $dados['limite']          ?? 60,
                'pagina'          => $dados['pagina']          ?? 1,
            ];
            // Atendente só vê próprias + aguardando
            if (!temPermissao(NIVEL_SUPERVISOR)) {
                $filtros['atendente_id'] = $usuario['id'];
            }
            $resp = apiGet('api/conversas', array_filter($filtros, fn($v) => $v !== ''));
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? [], $resp['mensagem'] ?? '');

        case 'listar_mensagens':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiGet("api/mensagens/conversa/{$conversaId}", [
                'limite'   => $dados['limite']   ?? 50,
                'antes_de' => $dados['antes_de'] ?? '',
            ]);
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? []);

        case 'enviar_mensagem':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            $conteudo   = trim($dados['conteudo'] ?? '');
            if (!$conversaId || !$conteudo) jsonResponse(false, null, 'Dados incompletos', 400);
            $resp = apiPost('api/mensagens/enviar', [
                'conversa_id'    => $conversaId,
                'conteudo'       => $conteudo,
                'enviado_por_id' => $usuario['id'],
            ]);
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null, $resp['mensagem'] ?? '');

        case 'assumir_conversa':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiPost("api/conversas/{$conversaId}/assumir", ['atendente_id' => $usuario['id']]);
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'finalizar_conversa':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiPost("api/conversas/{$conversaId}/finalizar");
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'marcar_lida':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiPost("api/conversas/{$conversaId}/marcar-lida");
            jsonResponse($resp['sucesso'] ?? false);

        case 'transferir_conversa':
            $conversaId    = intval($dados['conversa_id'] ?? 0);
            $deptId        = intval($dados['departamento_id'] ?? 0) ?: null;
            $atendenteId   = intval($dados['atendente_id']   ?? 0) ?: null;
            $motivo        = trim($dados['motivo'] ?? '');
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiPost("api/conversas/{$conversaId}/transferir", [
                'departamento_id'  => $deptId,
                'atendente_id'     => $atendenteId,
                'motivo'           => $motivo,
                'transferido_por_id' => $usuario['id'],
            ]);
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'alterar_status':
            $status = $dados['status'] ?? '';
            if (!in_array($status, ['online', 'ausente', 'offline'])) {
                jsonResponse(false, null, 'Status inválido', 400);
            }
            $resp = apiPost("api/usuarios/{$usuario['id']}/status", ['status' => $status]);
            if ($resp['sucesso'] ?? false) {
                $_SESSION['usuario']['status'] = $status;
            }
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'detalhes_conversa':
            $conversaId = intval($dados['conversa_id'] ?? 0);
            if (!$conversaId) jsonResponse(false, null, 'conversa_id inválido', 400);
            $resp = apiGet("api/conversas/{$conversaId}");
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null);

        case 'atendentes_disponiveis':
            $db = getDB();
            $deptId = intval($dados['departamento_id'] ?? 0);
            $stmt = $db->prepare(
                "SELECT id, nome, nivel FROM chat_usuarios
                 WHERE ativo = 1 AND status = 'online'
                   AND (departamento_id = ? OR ? = 0)
                 ORDER BY nome"
            );
            $stmt->execute([$deptId ?: null, $deptId]);
            jsonResponse(true, $stmt->fetchAll());

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

// Pré-carrega departamentos para filtros
$respDepts = apiGet('api/departamentos');
$departamentos = $respDepts['dados'] ?? [];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- CSS exclusivo do inbox -->
<style>
.inbox-wrap {
  display: flex;
  height: 100%;
  overflow: hidden;
}

/* ── Painel esquerdo: lista de conversas ──────────────────── */
.inbox-lista {
  width: 340px;
  min-width: 260px;
  flex-shrink: 0;
  background: var(--bg-secondary);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.inbox-lista-header {
  padding: 14px 14px 10px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.inbox-lista-titulo {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.busca-wrap {
  position: relative;
}
.busca-wrap svg {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  pointer-events: none;
}
.busca-input {
  width: 100%;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 10px 8px 34px;
  color: var(--text-primary);
  font-size: 13px;
  outline: none;
  transition: border-color .2s;
}
.busca-input:focus { border-color: var(--accent); }
.busca-input::placeholder { color: var(--text-muted); }

.filtros-tabs {
  display: flex;
  gap: 4px;
  padding: 8px 14px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  overflow-x: auto;
  scrollbar-width: none;
}
.filtros-tabs::-webkit-scrollbar { display: none; }
.tab-btn {
  padding: 5px 12px;
  border-radius: 20px;
  border: none;
  background: none;
  color: var(--text-muted);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s, color .15s;
}
.tab-btn:hover  { background: var(--bg-hover); color: var(--text-primary); }
.tab-btn.ativo  { background: var(--accent-light); color: var(--accent); }

.conversas-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 6px 0;
}
.conversas-scroll::-webkit-scrollbar { width: 4px; }
.conversas-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* Item de conversa */
.conversa-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  cursor: pointer;
  border-radius: 8px;
  margin: 0 6px 2px;
  transition: background .15s;
  position: relative;
}
.conversa-item:hover  { background: var(--bg-hover); }
.conversa-item.ativo  { background: var(--bg-active); }
.conversa-item.nao-lida .conv-nome { font-weight: 700; color: var(--text-primary); }

.conv-avatar {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: var(--bg-hover);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  flex-shrink: 0;
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 16px;
}

.conv-body { flex: 1; min-width: 0; }
.conv-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}
.conv-nome {
  font-size: 13.5px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text-primary);
  max-width: 160px;
}
.conv-hora {
  font-size: 11px;
  color: var(--text-muted);
  flex-shrink: 0;
}
.conv-preview {
  font-size: 12.5px;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-top: 2px;
}
.conv-meta {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 4px;
  flex-wrap: wrap;
}
.conv-dept {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 10px;
  background: var(--bg-hover);
  color: var(--text-muted);
}
.conv-badge-nao-lida {
  margin-left: auto;
  background: var(--accent);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
}
.conv-status-dot {
  width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}
.conv-status-dot.aguardando    { background: var(--warning); }
.conv-status-dot.em_atendimento { background: var(--success); }
.conv-status-dot.resolvido     { background: var(--text-muted); }

/* ── Painel direito: conversa ─────────────────────────────── */
.inbox-conversa {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--bg-primary);
}

/* Estado vazio */
.conversa-vazia {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  color: var(--text-muted);
}
.conversa-vazia-icon { font-size: 56px; }
.conversa-vazia-titulo { font-size: 18px; font-weight: 600; color: var(--text-secondary); }

/* Header da conversa ativa */
.conv-header {
  padding: 12px 16px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}
.conv-header-info { flex: 1; min-width: 0; }
.conv-header-nome { font-size: 15px; font-weight: 700; }
.conv-header-sub  { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
.conv-header-acoes { display: flex; gap: 6px; align-items: center; }

/* Área de mensagens */
.mensagens-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  scroll-behavior: smooth;
}
.mensagens-area::-webkit-scrollbar { width: 4px; }
.mensagens-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

/* Bolhas de mensagem */
.msg-wrap {
  display: flex;
  flex-direction: column;
  max-width: 72%;
}
.msg-wrap.entrada { align-items: flex-start; align-self: flex-start; }
.msg-wrap.saida   { align-items: flex-end;   align-self: flex-end; }

.msg-bolha {
  padding: 9px 13px;
  border-radius: 14px;
  font-size: 13.5px;
  line-height: 1.55;
  word-break: break-word;
  position: relative;
}
.msg-wrap.entrada .msg-bolha {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-bottom-left-radius: 4px;
  color: var(--text-primary);
}
.msg-wrap.saida .msg-bolha {
  background: var(--accent);
  border-bottom-right-radius: 4px;
  color: #fff;
}
.msg-wrap.saida.bot .msg-bolha {
  background: #1e3a2f;
  border: 1px solid #2d5a3d;
  color: #a7f3d0;
}

.msg-hora {
  font-size: 10.5px;
  color: var(--text-muted);
  margin-top: 3px;
  padding: 0 3px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.msg-wrap.saida .msg-hora { justify-content: flex-end; }

.msg-status-icon { font-size: 12px; }

/* Mensagem de sistema */
.msg-sistema {
  align-self: center;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 11.5px;
  color: var(--text-muted);
  text-align: center;
  margin: 6px 0;
}

/* Separador de data */
.msg-data-sep {
  align-self: center;
  font-size: 11px;
  color: var(--text-muted);
  background: var(--bg-secondary);
  padding: 3px 12px;
  border-radius: 20px;
  margin: 8px 0;
}

/* Mídias */
.msg-imagem {
  max-width: 260px;
  max-height: 200px;
  border-radius: 10px;
  cursor: pointer;
  display: block;
  object-fit: cover;
}
.msg-audio { width: 220px; accent-color: var(--accent); }
.msg-video { max-width: 260px; border-radius: 10px; }
.msg-doc {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: rgba(255,255,255,.05);
  border-radius: 8px;
  font-size: 12.5px;
}
.msg-doc a { color: inherit; text-decoration: underline; }

/* Área de digitação */
.digitar-wrap {
  padding: 12px 14px;
  background: var(--bg-secondary);
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex-shrink: 0;
}

.digitar-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

#input-mensagem {
  flex: 1;
  background: var(--bg-tertiary);
  border: 1.5px solid var(--border);
  border-radius: 22px;
  padding: 9px 16px;
  color: var(--text-primary);
  font-size: 13.5px;
  resize: none;
  max-height: 140px;
  outline: none;
  transition: border-color .2s;
  line-height: 1.5;
}
#input-mensagem:focus { border-color: var(--accent); }
#input-mensagem::placeholder { color: var(--text-muted); }

.btn-digitar {
  width: 40px; height: 40px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background .2s, transform .1s;
}
.btn-digitar:active { transform: scale(.92); }

.btn-anexo   { background: var(--bg-hover); color: var(--text-secondary); }
.btn-anexo:hover { background: var(--bg-active); color: var(--text-primary); }

.btn-enviar  { background: var(--accent); color: #fff; }
.btn-enviar:hover { background: var(--accent-hover); }
.btn-enviar:disabled { opacity: .4; cursor: not-allowed; }

.preview-arquivo {
  display: none;
  align-items: center;
  gap: 8px;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 12.5px;
  color: var(--text-secondary);
}
.preview-arquivo.visivel { display: flex; }
.preview-arquivo button {
  margin-left: auto;
  background: none;
  border: none;
  color: var(--danger);
  cursor: pointer;
  font-size: 16px;
  padding: 0 4px;
}

/* Barra de assumir / chatbot ativo */
.banner-assumir {
  display: none;
  padding: 10px 16px;
  background: rgba(245,158,11,.12);
  border-bottom: 1px solid rgba(245,158,11,.25);
  font-size: 13px;
  color: #fcd34d;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.banner-assumir.visivel { display: flex; }

/* Info painel lateral (contato) */
.info-painel {
  width: 280px;
  background: var(--bg-secondary);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  transform: translateX(100%);
  transition: transform .25s ease;
  position: absolute;
  right: 0; top: var(--topbar-h); bottom: 0;
  z-index: 50;
}
.info-painel.aberto { transform: translateX(0); }
.info-painel-header { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.info-painel-titulo { font-weight: 700; }
.info-secao { padding: 14px 16px; border-bottom: 1px solid var(--border); }
.info-secao-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: 10px; }
.info-campo { margin-bottom: 8px; }
.info-campo span { font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 2px; }
.info-campo p { font-size: 13px; color: var(--text-primary); }

/* Modal transferência */
</style>

<!-- Injeção de dados PHP no JS -->
<script>
OLA.usuarioId   = <?= $usuario['id'] ?>;
OLA.usuarioNome = '<?= addslashes($usuario['nome']) ?>';
OLA.nivel       = '<?= $usuario['nivel'] ?>';
document.body.dataset.uid    = OLA.usuarioId;
document.body.dataset.nome   = OLA.usuarioNome;
document.body.dataset.nivel  = OLA.nivel;
document.body.dataset.status = '<?= $usuario['status'] ?? 'online' ?>';
</script>

<div class="inbox-wrap">

  <!-- ── Lista de conversas ── -->
  <div class="inbox-lista">
    <div class="inbox-lista-header">
      <div class="inbox-lista-titulo">
        <span>Atendimentos</span>
        <button class="btn btn-sm btn-primary" onclick="atualizarLista()" title="Atualizar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
          </svg>
        </button>
      </div>
      <div class="busca-wrap">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" class="busca-input" id="busca-conversas"
               placeholder="Buscar por nome ou número..." />
      </div>
    </div>

    <!-- Filtros por status -->
    <div class="filtros-tabs">
      <button class="tab-btn ativo" data-status="">Todos</button>
      <button class="tab-btn" data-status="aguardando">Aguardando</button>
      <button class="tab-btn" data-status="em_atendimento">Em atendimento</button>
      <button class="tab-btn" data-status="resolvido">Resolvidos</button>
    </div>

    <!-- Lista -->
    <div class="conversas-scroll" id="lista-conversas">
      <div class="empty-state" style="padding:40px 20px">
        <div class="spinner"></div>
        <p style="color:var(--text-muted);font-size:13px">Carregando...</p>
      </div>
    </div>
  </div>

  <!-- ── Painel de conversa ── -->
  <div class="inbox-conversa" id="painel-conversa">

    <!-- Estado vazio (nenhuma conversa selecionada) -->
    <div class="conversa-vazia" id="estado-vazio">
      <div class="conversa-vazia-icon">💬</div>
      <div class="conversa-vazia-titulo">Selecione um atendimento</div>
      <p style="font-size:13.5px">Escolha uma conversa na lista ao lado</p>
    </div>

    <!-- Conteúdo da conversa (hidden até selecionar) -->
    <div id="conteudo-conversa" style="display:none; flex:1; flex-direction:column; overflow:hidden;">

      <!-- Header -->
      <div class="conv-header" id="conv-header">
        <div class="conv-avatar" id="conv-avatar-header" style="width:38px;height:38px;font-size:14px">?</div>
        <div class="conv-header-info">
          <div class="conv-header-nome" id="conv-nome-header">—</div>
          <div class="conv-header-sub" id="conv-sub-header">—</div>
        </div>
        <div class="conv-header-acoes">
          <!-- Info do contato -->
          <button class="btn btn-secondary btn-sm btn-icon" onclick="toggleInfoPainel()" title="Informações do contato">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
              <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
          </button>
          <!-- Transferir -->
          <button class="btn btn-secondary btn-sm" onclick="abrirModal('modal-transferir')" title="Transferir" id="btn-transferir" style="display:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
              <polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
            </svg>
            Transferir
          </button>
          <!-- Finalizar -->
          <button class="btn btn-danger btn-sm" onclick="confirmarFinalizar()" id="btn-finalizar" style="display:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Finalizar
          </button>
        </div>
      </div>

      <!-- Banner: chatbot ativo / aguardando -->
      <div class="banner-assumir" id="banner-assumir">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/>
          <path d="M12 8v4l3 3"/>
        </svg>
        <span id="txt-banner">Conversa aguardando atendimento</span>
        <button class="btn btn-sm btn-primary" style="margin-left:auto" onclick="assumirConversa()">
          Assumir atendimento
        </button>
      </div>

      <!-- Mensagens -->
      <div class="mensagens-area" id="mensagens-area"></div>

      <!-- Preview de arquivo para envio -->
      <div class="preview-arquivo" id="preview-arquivo">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        <span id="nome-arquivo-preview">arquivo.pdf</span>
        <button onclick="limparArquivo()">✕</button>
      </div>

      <!-- Campo de digitação -->
      <div class="digitar-wrap" id="digitar-wrap">
        <div class="digitar-row">
          <label class="btn-digitar btn-anexo" for="input-arquivo" title="Enviar arquivo">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
            </svg>
          </label>
          <input type="file" id="input-arquivo" style="display:none" onchange="selecionarArquivo(this)" />

          <textarea id="input-mensagem" placeholder="Digite uma mensagem..." rows="1"
                    onkeydown="teclaMsg(event)" oninput="autoResizeMsg(this)"></textarea>

          <button class="btn-digitar btn-enviar" id="btn-enviar" onclick="enviarMensagem()" title="Enviar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </button>
        </div>
      </div>

    </div><!-- /#conteudo-conversa -->
  </div><!-- /.inbox-conversa -->

  <!-- Painel de informações do contato -->
  <div class="info-painel" id="info-painel">
    <div class="info-painel-header">
      <div class="info-painel-titulo">Informações</div>
      <button class="btn btn-secondary btn-sm btn-icon" onclick="toggleInfoPainel()">✕</button>
    </div>
    <div class="info-secao">
      <div class="info-secao-label">Contato</div>
      <div class="info-campo"><span>Nome</span><p id="ip-nome">—</p></div>
      <div class="info-campo"><span>Número</span><p id="ip-numero">—</p></div>
      <div class="info-campo"><span>Tags</span><p id="ip-tags">—</p></div>
    </div>
    <div class="info-secao">
      <div class="info-secao-label">Atendimento</div>
      <div class="info-campo"><span>Protocolo</span><p id="ip-protocolo">—</p></div>
      <div class="info-campo"><span>Departamento</span><p id="ip-departamento">—</p></div>
      <div class="info-campo"><span>Atendente</span><p id="ip-atendente">—</p></div>
      <div class="info-campo"><span>Abertura</span><p id="ip-abertura">—</p></div>
    </div>
    <div class="info-secao" id="ip-obs-wrap">
      <div class="info-secao-label">Observações</div>
      <p id="ip-obs" style="font-size:13px;color:var(--text-secondary);line-height:1.55">—</p>
    </div>
  </div>

</div><!-- /.inbox-wrap -->

<!-- ── Modal: Transferir conversa ── -->
<div class="modal-overlay" id="modal-transferir" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo">Transferir Atendimento</div>
      <button class="modal-close" onclick="fecharModal('modal-transferir')">✕</button>
    </div>

    <div class="form-group">
      <label class="form-label">Departamento destino</label>
      <select class="form-control" id="transf-departamento" onchange="carregarAtendentesTransf()">
        <option value="">— Selecionar departamento —</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Atendente específico (opcional)</label>
      <select class="form-control" id="transf-atendente">
        <option value="">— Distribuir automaticamente —</option>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Motivo (opcional)</label>
      <textarea class="form-control" id="transf-motivo" rows="2" placeholder="Descreva o motivo da transferência..."></textarea>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-transferir')">Cancelar</button>
      <button class="btn btn-primary" onclick="confirmarTransferencia()">Transferir</button>
    </div>
  </div>
</div>

<!-- ── JS do inbox ── -->
<script>
// Estado global
let conversaAtiva     = null; // objeto completo da conversa
let filtroStatus      = '';
let filtroBusca       = '';
let arquivoSelecionado = null;
let paginaMensagens   = false; // para paginação futura

// ── Lista de conversas ─────────────────────────────────────────
async function atualizarLista() {
  const lista = document.getElementById('lista-conversas');

  const dados = await postAjax('inbox.php', {
    action:  'listar_conversas',
    status:  filtroStatus,
    busca:   filtroBusca,
  });

  if (!dados.sucesso) { lista.innerHTML = '<p style="padding:20px;color:var(--text-muted);font-size:13px">Erro ao carregar conversas.</p>'; return; }

  const convs = dados.dados || [];
  if (!convs.length) {
    lista.innerHTML = `<div class="empty-state" style="padding:40px 20px">
      <div class="empty-state-icon">💬</div>
      <div class="empty-state-titulo">Nenhum atendimento</div>
      <p style="font-size:13px">Aguardando novas mensagens...</p>
    </div>`;
    return;
  }

  lista.innerHTML = convs.map(c => renderItemConversa(c)).join('');

  // Reaplica ativo
  if (conversaAtiva) {
    const el = document.querySelector(`.conversa-item[data-id="${conversaAtiva.id}"]`);
    if (el) el.classList.add('ativo');
  }
}

function renderItemConversa(c) {
  const nome       = esc(c.contato_nome || c.contato_nome_push || c.contato_numero || 'Desconhecido');
  const inicial    = (nome[0] || '?').toUpperCase();
  const hora       = c.ultima_mensagem_em ? formatarData(c.ultima_mensagem_em) : '';
  const preview    = esc(c.ultima_mensagem || '');
  const naoLida    = parseInt(c.nao_lidas || 0);
  const dept       = esc(c.departamento_nome || '');
  const statusCls  = c.status || 'aguardando';

  return `
  <div class="conversa-item ${naoLida > 0 ? 'nao-lida' : ''}"
       data-id="${c.id}" onclick="selecionarConversa(${c.id})">
    <div class="conv-avatar">${inicial}</div>
    <div class="conv-body">
      <div class="conv-header-row">
        <span class="conv-nome">${nome}</span>
        <span class="conv-hora">${hora}</span>
      </div>
      <div class="conv-preview">${preview || '<em style="opacity:.5">Sem mensagens</em>'}</div>
      <div class="conv-meta">
        <span class="conv-status-dot ${statusCls}"></span>
        ${dept ? `<span class="conv-dept">${dept}</span>` : ''}
        ${naoLida > 0 ? `<span class="conv-badge-nao-lida">${naoLida > 99 ? '99+' : naoLida}</span>` : ''}
      </div>
    </div>
  </div>`;
}

// ── Selecionar conversa ────────────────────────────────────────
async function selecionarConversa(id) {
  // Remove ativo anterior
  document.querySelectorAll('.conversa-item').forEach(el => el.classList.remove('ativo'));
  const elItem = document.querySelector(`.conversa-item[data-id="${id}"]`);
  if (elItem) elItem.classList.add('ativo');

  // Carrega detalhes
  const r = await postAjax('inbox.php', { action: 'detalhes_conversa', conversa_id: id });
  if (!r.sucesso) { toast('Erro ao abrir conversa.', 'error'); return; }

  conversaAtiva = r.dados;

  document.getElementById('estado-vazio').style.display          = 'none';
  document.getElementById('conteudo-conversa').style.display     = 'flex';

  atualizarHeaderConversa();
  atualizarBannerAssumir();
  carregarMensagens();
  preencherInfoPainel();
  marcarComoLida(id);
}

function atualizarHeaderConversa() {
  const c    = conversaAtiva;
  const nome = c.contato_nome || c.contato_nome_push || c.contato_numero || 'Desconhecido';
  const sub  = [
    c.protocolo ? `#${c.protocolo}` : '',
    c.departamento_nome || '',
    c.atendente_nome ? `Atendente: ${c.atendente_nome}` : '',
  ].filter(Boolean).join(' · ');

  document.getElementById('conv-avatar-header').textContent   = nome[0].toUpperCase();
  document.getElementById('conv-nome-header').textContent     = nome;
  document.getElementById('conv-sub-header').textContent      = sub || '—';

  const ehMeu   = c.atendente_id === OLA.usuarioId;
  const eSuperv = ['admin','supervisor'].includes(OLA.nivel);
  const emAtend = c.status === 'em_atendimento';

  document.getElementById('btn-transferir').style.display = (emAtend && (ehMeu || eSuperv)) ? 'flex' : 'none';
  document.getElementById('btn-finalizar').style.display  = (emAtend && (ehMeu || eSuperv)) ? 'flex' : 'none';
}

function atualizarBannerAssumir() {
  const c      = conversaAtiva;
  const banner = document.getElementById('banner-assumir');
  const txt    = document.getElementById('txt-banner');

  if (c.status === 'aguardando' || (c.chatbot_ativo && c.status !== 'em_atendimento')) {
    banner.classList.add('visivel');
    txt.textContent = c.chatbot_ativo
      ? '🤖 Chatbot em atendimento — clique para assumir'
      : '⏳ Aguardando atendimento';
  } else if (c.status === 'em_atendimento' && c.atendente_id !== OLA.usuarioId && !['admin','supervisor'].includes(OLA.nivel)) {
    banner.classList.add('visivel');
    txt.textContent = `Sendo atendido por ${c.atendente_nome || 'outro atendente'}`;
    banner.querySelector('button').style.display = 'none';
  } else {
    banner.classList.remove('visivel');
  }
}

// ── Mensagens ──────────────────────────────────────────────────
async function carregarMensagens() {
  const area = document.getElementById('mensagens-area');
  area.innerHTML = '<div style="display:flex;justify-content:center;padding:40px"><div class="spinner"></div></div>';

  const r = await postAjax('inbox.php', {
    action:      'listar_mensagens',
    conversa_id: conversaAtiva.id,
    limite:      60,
  });
  if (!r.sucesso) { area.innerHTML = '<p style="padding:20px;color:var(--text-muted)">Erro ao carregar mensagens.</p>'; return; }

  const msgs = r.dados || [];
  area.innerHTML = '';

  let dataAnterior = '';
  for (const m of msgs) {
    const dataMsg = new Date(m.criado_em).toDateString();
    if (dataMsg !== dataAnterior) {
      area.innerHTML += `<div class="msg-data-sep">${formatarDataLonga(m.criado_em)}</div>`;
      dataAnterior = dataMsg;
    }
    area.innerHTML += renderMensagem(m);
  }

  if (!msgs.length) {
    area.innerHTML = '<div class="empty-state" style="height:100%"><div class="empty-state-icon">💬</div><p>Nenhuma mensagem ainda</p></div>';
  }

  rolarParaBaixo();
}

function renderMensagem(m) {
  if (m.tipo === 'sistema') {
    return `<div class="msg-sistema">${esc(m.conteudo || '')}</div>`;
  }

  const dir    = m.direcao === 'saida' ? 'saida' : 'entrada';
  const botCls = m.is_bot ? 'bot' : '';
  const hora   = new Date(m.criado_em).toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });

  let conteudo = '';
  switch (m.tipo) {
    case 'imagem':
      conteudo = `<img class="msg-imagem" src="${esc(m.midia_url || '')}"
                   alt="${esc(m.midia_nome || 'imagem')}"
                   onclick="visualizarImagem('${esc(m.midia_url || '')}')"/>`;
      if (m.conteudo) conteudo += `<div style="margin-top:6px">${esc(m.conteudo)}</div>`;
      break;
    case 'audio':
      conteudo = `<audio class="msg-audio" controls src="${esc(m.midia_url || '')}"></audio>`;
      break;
    case 'video':
      conteudo = `<video class="msg-video" controls src="${esc(m.midia_url || '')}" style="max-width:260px;border-radius:10px"></video>`;
      break;
    case 'documento':
      conteudo = `<div class="msg-doc">📎 <a href="${esc(m.midia_url || '')}" target="_blank">${esc(m.midia_nome || 'documento')}</a></div>`;
      break;
    case 'sticker':
      conteudo = `<img class="msg-imagem" src="${esc(m.midia_url || '')}" alt="sticker" style="background:transparent;border:none;max-width:100px"/>`;
      break;
    case 'localizacao':
      conteudo = `📍 <em>Localização compartilhada</em>`;
      break;
    default:
      conteudo = esc(m.conteudo || '').replace(/\n/g, '<br>');
  }

  let statusIcon = '';
  if (dir === 'saida') {
    const icons = { enviando:'🕐', enviado:'✓', entregue:'✓✓', lido:'✓✓', erro:'⚠' };
    statusIcon = `<span class="msg-status-icon" style="${m.status === 'lido' ? 'color:#60a5fa' : ''}">${icons[m.status] || '✓'}</span>`;
    if (m.is_bot) statusIcon = '🤖';
  }

  return `
  <div class="msg-wrap ${dir} ${botCls}" data-msg-id="${m.id}">
    <div class="msg-bolha">${conteudo}</div>
    <div class="msg-hora">${hora} ${statusIcon}</div>
  </div>`;
}

function rolarParaBaixo(suave = false) {
  const area = document.getElementById('mensagens-area');
  if (suave) { area.scrollTop = area.scrollHeight; return; }
  setTimeout(() => { area.scrollTop = area.scrollHeight; }, 60);
}

// ── Enviar mensagem ────────────────────────────────────────────
async function enviarMensagem() {
  if (!conversaAtiva) return;

  const input   = document.getElementById('input-mensagem');
  const conteudo = input.value.trim();
  const arquivo  = arquivoSelecionado;

  if (!conteudo && !arquivo) return;

  const btnEnviar = document.getElementById('btn-enviar');
  btnEnviar.disabled = true;

  try {
    let r;
    if (arquivo) {
      const formData = new FormData();
      formData.append('arquivo',       arquivo);
      formData.append('conversa_id',   conversaAtiva.id);
      formData.append('conteudo',      conteudo);
      formData.append('enviado_por_id', OLA.usuarioId);

      const resp = await fetch('enviar_midia.php', {
        method: 'POST',
        body:   formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      r = await resp.json();
      limparArquivo();
    } else {
      r = await postAjax('inbox.php', {
        action:       'enviar_mensagem',
        conversa_id:  conversaAtiva.id,
        conteudo,
      });
    }

    if (r.sucesso) {
      input.value = '';
      input.style.height = 'auto';
    } else {
      toast(r.mensagem || 'Erro ao enviar mensagem.', 'error');
    }
  } finally {
    btnEnviar.disabled = false;
    input.focus();
  }
}

function teclaMsg(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    enviarMensagem();
  }
}

function autoResizeMsg(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 140) + 'px';
}

// ── Arquivo ────────────────────────────────────────────────────
function selecionarArquivo(input) {
  const file = input.files[0];
  if (!file) return;
  arquivoSelecionado = file;
  document.getElementById('nome-arquivo-preview').textContent = file.name;
  document.getElementById('preview-arquivo').classList.add('visivel');
}

function limparArquivo() {
  arquivoSelecionado = null;
  document.getElementById('input-arquivo').value = '';
  document.getElementById('preview-arquivo').classList.remove('visivel');
}

// ── Assumir / finalizar / transferir ──────────────────────────
async function assumirConversa() {
  if (!conversaAtiva) return;
  const r = await postAjax('inbox.php', {
    action: 'assumir_conversa', conversa_id: conversaAtiva.id,
  });
  if (r.sucesso) {
    toast('Atendimento assumido!', 'success');
    conversaAtiva.status       = 'em_atendimento';
    conversaAtiva.atendente_id = OLA.usuarioId;
    conversaAtiva.chatbot_ativo = 0;
    atualizarHeaderConversa();
    atualizarBannerAssumir();
    atualizarLista();
  } else {
    toast(r.mensagem || 'Erro ao assumir.', 'error');
  }
}

async function confirmarFinalizar() {
  if (!conversaAtiva) return;
  if (!confirm('Finalizar este atendimento?')) return;
  const r = await postAjax('inbox.php', {
    action: 'finalizar_conversa', conversa_id: conversaAtiva.id,
  });
  if (r.sucesso) {
    toast('Atendimento finalizado.', 'success');
    conversaAtiva = null;
    document.getElementById('estado-vazio').style.display      = 'flex';
    document.getElementById('conteudo-conversa').style.display = 'none';
    atualizarLista();
  } else {
    toast(r.mensagem || 'Erro ao finalizar.', 'error');
  }
}

async function carregarAtendentesTransf() {
  const deptId = document.getElementById('transf-departamento').value;
  const sel    = document.getElementById('transf-atendente');
  sel.innerHTML = '<option value="">— Distribuir automaticamente —</option>';
  if (!deptId) return;
  const r = await postAjax('inbox.php', { action: 'atendentes_disponiveis', departamento_id: deptId });
  if (r.sucesso && r.dados) {
    r.dados.forEach(u => {
      sel.innerHTML += `<option value="${u.id}">${esc(u.nome)}</option>`;
    });
  }
}

async function confirmarTransferencia() {
  if (!conversaAtiva) return;
  const deptId     = document.getElementById('transf-departamento').value;
  const atendenteId = document.getElementById('transf-atendente').value;
  const motivo     = document.getElementById('transf-motivo').value.trim();

  if (!deptId) { toast('Selecione o departamento de destino.', 'error'); return; }

  const r = await postAjax('inbox.php', {
    action:          'transferir_conversa',
    conversa_id:     conversaAtiva.id,
    departamento_id: deptId,
    atendente_id:    atendenteId || null,
    motivo,
  });
  if (r.sucesso) {
    toast('Atendimento transferido!', 'success');
    fecharModal('modal-transferir');
    conversaAtiva = null;
    document.getElementById('estado-vazio').style.display      = 'flex';
    document.getElementById('conteudo-conversa').style.display = 'none';
    atualizarLista();
  } else {
    toast(r.mensagem || 'Erro ao transferir.', 'error');
  }
}

// ── Marcar como lida ──────────────────────────────────────────
async function marcarComoLida(conversaId) {
  await postAjax('inbox.php', { action: 'marcar_lida', conversa_id: conversaId });
  // Atualiza item na lista
  const el = document.querySelector(`.conversa-item[data-id="${conversaId}"]`);
  if (el) {
    el.classList.remove('nao-lida');
    const badge = el.querySelector('.conv-badge-nao-lida');
    if (badge) badge.remove();
  }
}

// ── Painel de informações ──────────────────────────────────────
function preencherInfoPainel() {
  const c = conversaAtiva;
  document.getElementById('ip-nome').textContent        = c.contato_nome || c.contato_nome_push || '—';
  document.getElementById('ip-numero').textContent      = c.contato_numero || '—';
  document.getElementById('ip-tags').textContent        = c.tags || '—';
  document.getElementById('ip-protocolo').textContent   = c.protocolo ? `#${c.protocolo}` : '—';
  document.getElementById('ip-departamento').textContent = c.departamento_nome || '—';
  document.getElementById('ip-atendente').textContent   = c.atendente_nome || 'Não atribuído';
  document.getElementById('ip-abertura').textContent    = c.aberto_em ? formatarData(c.aberto_em) : '—';
  document.getElementById('ip-obs').textContent         = c.observacoes || '—';
}

function toggleInfoPainel() {
  document.getElementById('info-painel').classList.toggle('aberto');
}

// ── Visualizar imagem ──────────────────────────────────────────
function visualizarImagem(url) {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out';
  overlay.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain"/>`;
  overlay.onclick = () => overlay.remove();
  document.body.appendChild(overlay);
}

// ── Formatação de data ─────────────────────────────────────────
function formatarDataLonga(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const hoje = new Date();
  const ontem = new Date(hoje); ontem.setDate(hoje.getDate() - 1);
  if (d.toDateString() === hoje.toDateString())  return 'Hoje';
  if (d.toDateString() === ontem.toDateString()) return 'Ontem';
  return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' });
}

// ── Socket.IO: eventos em tempo real ──────────────────────────
socket.on('nova_mensagem', (msg) => {
  // Atualiza lista sempre
  atualizarLista();

  // Se for da conversa ativa, adiciona a mensagem
  if (conversaAtiva && msg.conversa_id === conversaAtiva.id) {
    const area = document.getElementById('mensagens-area');
    // Remove estado vazio se houver
    const empty = area.querySelector('.empty-state');
    if (empty) empty.remove();
    area.innerHTML += renderMensagem(msg);
    rolarParaBaixo(true);
    marcarComoLida(conversaAtiva.id);
  } else if (msg.direcao === 'entrada') {
    tocarSomNotificacao();
    incrementarBadge(1);
  }
});

socket.on('nova_conversa', () => atualizarLista());

socket.on('conversa_assumida', ({ conversa_id, atendente_id }) => {
  if (conversaAtiva && conversaAtiva.id === conversa_id) {
    conversaAtiva.atendente_id  = atendente_id;
    conversaAtiva.status        = 'em_atendimento';
    conversaAtiva.chatbot_ativo = 0;
    atualizarHeaderConversa();
    atualizarBannerAssumir();
  }
  atualizarLista();
});

socket.on('conversa_encerrada', ({ conversa_id }) => {
  if (conversaAtiva && conversaAtiva.id === conversa_id) {
    toast('Esta conversa foi encerrada.', 'info');
    conversaAtiva = null;
    document.getElementById('estado-vazio').style.display      = 'flex';
    document.getElementById('conteudo-conversa').style.display = 'none';
  }
  atualizarLista();
});

socket.on('conversa_transferida', () => atualizarLista());
socket.on('fila_atualizada',      () => atualizarLista());

socket.on('mensagem_ack', ({ msg_id, ack }) => {
  const icons = { 0:'⚠', 1:'✓', 2:'✓✓', 3:'✓✓' };
  document.querySelectorAll('[data-msg-id]').forEach(el => {
    const icon = el.querySelector('.msg-status-icon');
    if (icon) {
      // match via data não disponível — futuro: data-wa-id
    }
  });
});

// ── Filtros ────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');
    filtroStatus = btn.dataset.status;
    atualizarLista();
  });
});

// Busca com debounce
let debTimer;
document.getElementById('busca-conversas').addEventListener('input', (e) => {
  clearTimeout(debTimer);
  debTimer = setTimeout(() => {
    filtroBusca = e.target.value.trim();
    atualizarLista();
  }, 350);
});

// ── Inicialização ──────────────────────────────────────────────
atualizarLista();
// Atualiza a lista a cada 30s como fallback
setInterval(atualizarLista, 30000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
