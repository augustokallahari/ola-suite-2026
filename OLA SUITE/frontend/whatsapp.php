<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();
exigirNivel(NIVEL_ADMIN);
$usuario      = usuarioLogado();
$tituloPagina = 'Conexões WhatsApp';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';

    switch ($action) {

        case 'listar':
            $resp = apiGet('api/sessoes');
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? []);

        case 'criar':
            $nome          = trim($dados['nome'] ?? '');
            $departamentoId = intval($dados['departamento_id'] ?? 0) ?: null;
            if (!$nome) jsonResponse(false, null, 'Nome obrigatório', 400);
            $resp = apiPost('api/sessoes', ['nome' => $nome, 'departamento_id' => $departamentoId]);
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null, $resp['mensagem'] ?? '');

        case 'conectar':
            $sessionId = trim($dados['session_id'] ?? '');
            if (!$sessionId) jsonResponse(false, null, 'session_id obrigatório', 400);
            $resp = apiPost("api/sessoes/{$sessionId}/conectar");
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'desconectar':
            $sessionId = trim($dados['session_id'] ?? '');
            if (!$sessionId) jsonResponse(false, null, 'session_id obrigatório', 400);
            $resp = apiPost("api/sessoes/{$sessionId}/desconectar");
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'excluir':
            $id = intval($dados['id'] ?? 0);
            $resp = apiDelete("api/sessoes/{$id}");
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'status':
            $sessionId = trim($dados['session_id'] ?? '');
            $resp = apiGet("api/sessoes/{$sessionId}/status");
            jsonResponse($resp['sucesso'] ?? false, $resp ?? []);

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

// Carrega departamentos para o select do formulário
$respDepts = apiGet('api/departamentos');
$departamentos = $respDepts['dados'] ?? [];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.page-wrap { padding: 24px; max-width: 1100px; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.page-titulo { font-size:22px; font-weight:700; }

/* Grid de sessões */
.sessoes-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }

.sessao-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 22px;
  transition: border-color .2s;
  position: relative;
}
.sessao-card:hover { border-color: var(--border-light); }

.sessao-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.sessao-icone {
  width: 48px; height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, #25D366, #128C7E);
  display: flex; align-items:center; justify-content:center;
  font-size: 24px; flex-shrink:0;
}
.sessao-nome    { font-size:15px; font-weight:700; }
.sessao-numero  { font-size:12.5px; color:var(--text-muted); margin-top:2px; }

.sessao-status-badge {
  display: inline-flex; align-items:center; gap:5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px; font-weight:600;
  margin-bottom: 14px;
}
.sessao-status-badge.conectado    { background:rgba(16,185,129,.12); color:var(--success); }
.sessao-status-badge.aguardando_qr { background:rgba(245,158,11,.12); color:var(--warning); }
.sessao-status-badge.desconectado { background:rgba(239,68,68,.12);  color:var(--danger); }
.sessao-status-badge.erro         { background:rgba(239,68,68,.12);  color:var(--danger); }
.sessao-status-badge::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
  animation: none;
}
.sessao-status-badge.conectado::before    { animation: pulso 2s infinite; }
.sessao-status-badge.aguardando_qr::before { animation: pulso 1s infinite; }
@keyframes pulso { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Área do QR code */
.qr-wrap {
  display: none;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px;
  background: #fff;
  border-radius: var(--radius);
  margin-bottom: 14px;
}
.qr-wrap.visivel { display: flex; }
.qr-wrap img { width: 200px; height: 200px; }
.qr-instrucao { font-size:12px; color:#333; text-align:center; font-weight:500; }

/* Ações do card */
.sessao-acoes { display:flex; gap:8px; flex-wrap:wrap; }

/* Spinner de conexão */
.sessao-conectando {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: rgba(79,110,247,.08);
  border: 1px solid rgba(79,110,247,.2);
  border-radius: var(--radius);
  font-size:13px;
  color: var(--accent);
  margin-bottom: 14px;
}
.sessao-conectando.visivel { display:flex; }

/* Sessão info grid */
.sessao-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
.sessao-info-item { background:var(--bg-tertiary); border-radius:var(--radius-sm); padding:8px 10px; }
.sessao-info-label { font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:.05em; }
.sessao-info-val   { font-size:13px; font-weight:600; margin-top:2px; }
</style>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-titulo">Conexões WhatsApp</div>
      <p style="color:var(--text-muted);font-size:13px;margin-top:4px">
        Gerencie os números conectados ao OlaSuite
      </p>
    </div>
    <button class="btn btn-primary" onclick="abrirModal('modal-nova-sessao')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Nova Conexão
    </button>
  </div>

  <!-- Alerta de instrução -->
  <div class="alert alert-info" style="margin-bottom:20px">
    <strong>Como conectar:</strong> Clique em "Conectar", aguarde o QR Code aparecer, abra o WhatsApp no celular
    → <strong>Dispositivos conectados → Conectar dispositivo</strong> e aponte a câmera para o QR Code.
  </div>

  <!-- Grid de sessões -->
  <div class="sessoes-grid" id="sessoes-grid">
    <div style="grid-column:1/-1;display:flex;justify-content:center;padding:40px">
      <div class="spinner"></div>
    </div>
  </div>
</div>

<!-- Modal: Nova sessão -->
<div class="modal-overlay" id="modal-nova-sessao" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo">Nova Conexão WhatsApp</div>
      <button class="modal-close" onclick="fecharModal('modal-nova-sessao')">✕</button>
    </div>

    <div class="form-group">
      <label class="form-label">Nome da conexão *</label>
      <input type="text" class="form-control" id="nova-sessao-nome"
             placeholder="Ex: Suporte Principal, Vendas 1..." maxlength="100" />
      <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">
        Um apelido para identificar este número
      </small>
    </div>

    <div class="form-group">
      <label class="form-label">Departamento padrão (opcional)</label>
      <select class="form-control" id="nova-sessao-dept">
        <option value="">— Sem departamento padrão —</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">
        Conversas desta sessão serão enviadas a este departamento
      </small>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-nova-sessao')">Cancelar</button>
      <button class="btn btn-primary" onclick="criarSessao()">Criar e Conectar</button>
    </div>
  </div>
</div>

<script>
// ── Carregar sessões ───────────────────────────────────────────
async function carregarSessoes() {
  const grid = document.getElementById('sessoes-grid');
  const r    = await postAjax('whatsapp.php', { action: 'listar' });
  const sessoes = r.dados || [];

  if (!sessoes.length) {
    grid.innerHTML = `
    <div class="empty-state" style="grid-column:1/-1;padding:60px">
      <div class="empty-state-icon">📱</div>
      <div class="empty-state-titulo">Nenhuma conexão cadastrada</div>
      <p style="font-size:13px">Adicione um número de WhatsApp para começar a receber mensagens</p>
      <button class="btn btn-primary" style="margin-top:12px" onclick="abrirModal('modal-nova-sessao')">
        Adicionar primeira conexão
      </button>
    </div>`;
    return;
  }

  grid.innerHTML = sessoes.map(s => renderCardSessao(s)).join('');
}

function renderCardSessao(s) {
  const statusLabels = {
    conectado:     'Conectado',
    aguardando_qr: 'Aguardando QR',
    desconectado:  'Desconectado',
    erro:          'Erro',
  };
  const statusLabel = statusLabels[s.status] || s.status;
  const numero = s.numero ? `+${s.numero}` : 'Número não identificado';

  const isConectado   = s.status === 'conectado';
  const isAguardando  = s.status === 'aguardando_qr';
  const isDesconect   = ['desconectado','erro'].includes(s.status);

  return `
  <div class="sessao-card" id="card-${s.session_id}">
    <div class="sessao-header">
      <div class="sessao-icone">📱</div>
      <div>
        <div class="sessao-nome">${esc(s.nome)}</div>
        <div class="sessao-numero">${isConectado ? numero : 'Não conectado'}</div>
      </div>
    </div>

    <div class="sessao-status-badge ${s.status}" id="badge-${s.session_id}">
      ${esc(statusLabel)}
    </div>

    <!-- Indicador de conectando -->
    <div class="sessao-conectando" id="conectando-${s.session_id}">
      <div class="spinner"></div>
      Inicializando, aguarde o QR Code...
    </div>

    <!-- QR Code -->
    <div class="qr-wrap" id="qr-${s.session_id}">
      <img id="qr-img-${s.session_id}" src="" alt="QR Code" />
      <div class="qr-instrucao">
        📱 Abra o WhatsApp → Dispositivos conectados<br>→ Conectar dispositivo → aponte para o QR
      </div>
    </div>

    <!-- Info quando conectado -->
    ${isConectado ? `
    <div class="sessao-info-grid">
      <div class="sessao-info-item">
        <div class="sessao-info-label">Número</div>
        <div class="sessao-info-val" style="color:var(--success)">${numero}</div>
      </div>
      <div class="sessao-info-item">
        <div class="sessao-info-label">Departamento</div>
        <div class="sessao-info-val">${esc(s.departamento_nome || 'Geral')}</div>
      </div>
    </div>` : ''}

    <div class="sessao-acoes">
      ${isDesconect || s.status === 'erro' ? `
        <button class="btn btn-primary btn-sm" onclick="conectar('${esc(s.session_id)}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18.364 5.636a9 9 0 0 1 0 12.728"/><path d="M15.536 8.464a5 5 0 0 1 0 7.072"/>
            <path d="M12.707 11.293a1 1 0 0 1 0 1.414"/><path d="M5 12H2"/><path d="M12 5V2"/>
          </svg>
          Conectar
        </button>` : ''}

      ${isConectado || isAguardando ? `
        <button class="btn btn-secondary btn-sm" onclick="desconectar('${esc(s.session_id)}')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="1" y1="1" x2="23" y2="23"/>
            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
            <path d="M10.71 5.05A16 16 0 0 1 22.56 9"/>
            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <line x1="12" y1="20" x2="12.01" y2="20"/>
          </svg>
          Desconectar
        </button>` : ''}

      <button class="btn btn-danger btn-sm btn-icon" title="Remover conexão"
              onclick="excluirSessao(${s.id}, '${esc(s.nome)}')">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
          <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
        </svg>
      </button>
    </div>
  </div>`;
}

// ── Criar sessão ───────────────────────────────────────────────
async function criarSessao() {
  const nome  = document.getElementById('nova-sessao-nome').value.trim();
  const deptId = document.getElementById('nova-sessao-dept').value;
  if (!nome) { toast('Nome obrigatório.', 'error'); return; }

  const r = await postAjax('whatsapp.php', {
    action: 'criar', nome, departamento_id: deptId || null,
  });
  if (!r.sucesso) { toast(r.mensagem || 'Erro ao criar sessão.', 'error'); return; }

  toast('Sessão criada! Iniciando conexão...', 'success');
  fecharModal('modal-nova-sessao');
  document.getElementById('nova-sessao-nome').value = '';

  await carregarSessoes();

  // Inicia conexão automaticamente
  const sessionId = r.dados?.session_id;
  if (sessionId) setTimeout(() => conectar(sessionId), 500);
}

// ── Conectar ───────────────────────────────────────────────────
async function conectar(sessionId) {
  // Mostra indicador de conectando
  const conect = document.getElementById(`conectando-${sessionId}`);
  const badge  = document.getElementById(`badge-${sessionId}`);
  if (conect) conect.classList.add('visivel');
  if (badge)  { badge.className = 'sessao-status-badge aguardando_qr'; badge.textContent = 'Aguardando QR'; }

  const r = await postAjax('whatsapp.php', { action: 'conectar', session_id: sessionId });
  if (!r.sucesso) {
    toast(r.mensagem || 'Erro ao iniciar conexão.', 'error');
    if (conect) conect.classList.remove('visivel');
  }
  // O QR chega via Socket.IO (evento qr_code)
}

// ── Desconectar ────────────────────────────────────────────────
async function desconectar(sessionId) {
  if (!confirm('Desconectar esta sessão do WhatsApp?')) return;
  const r = await postAjax('whatsapp.php', { action: 'desconectar', session_id: sessionId });
  if (r.sucesso) {
    toast('Sessão desconectada.', 'success');
    carregarSessoes();
  } else {
    toast(r.mensagem || 'Erro ao desconectar.', 'error');
  }
}

// ── Excluir sessão ────────────────────────────────────────────
async function excluirSessao(id, nome) {
  if (!confirm(`Remover a conexão "${nome}"?\n\nAs conversas existentes serão mantidas.`)) return;
  const r = await postAjax('whatsapp.php', { action: 'excluir', id });
  if (r.sucesso) {
    toast('Conexão removida.', 'success');
    carregarSessoes();
  } else {
    toast(r.mensagem || 'Erro ao remover.', 'error');
  }
}

// ── Socket.IO — eventos WhatsApp ───────────────────────────────
socket.on('qr_code', ({ session_id, qr }) => {
  const qrWrap  = document.getElementById(`qr-${session_id}`);
  const qrImg   = document.getElementById(`qr-img-${session_id}`);
  const conect  = document.getElementById(`conectando-${session_id}`);
  const badge   = document.getElementById(`badge-${session_id}`);

  if (qrImg)  { qrImg.src = qr; }
  if (qrWrap) qrWrap.classList.add('visivel');
  if (conect) conect.classList.remove('visivel');
  if (badge)  { badge.className = 'sessao-status-badge aguardando_qr'; badge.textContent = 'Aguardando QR'; }

  tocarSomNotificacao();
  toast('QR Code gerado! Escaneie com seu WhatsApp.', 'info', 5000);
});

socket.on('sessao_status', ({ session_id, status, numero }) => {
  const badge  = document.getElementById(`badge-${session_id}`);
  const qrWrap = document.getElementById(`qr-${session_id}`);
  const conect = document.getElementById(`conectando-${session_id}`);

  const labels = { conectado:'Conectado', aguardando_qr:'Aguardando QR', desconectado:'Desconectado', erro:'Erro' };

  if (badge)  { badge.className = `sessao-status-badge ${status}`; badge.textContent = labels[status] || status; }
  if (qrWrap && status === 'conectado') qrWrap.classList.remove('visivel');
  if (conect) conect.classList.remove('visivel');

  if (status === 'conectado') {
    toast(`✅ WhatsApp conectado! ${numero ? '+'+numero : ''}`, 'success', 5000);
    setTimeout(() => carregarSessoes(), 1500);
  }
  if (status === 'desconectado') {
    toast('⚠️ Sessão desconectada. Reconectando automaticamente...', 'info', 4000);
    setTimeout(() => carregarSessoes(), 2000);
  }
});

// ── Inicialização ──────────────────────────────────────────────
carregarSessoes();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
