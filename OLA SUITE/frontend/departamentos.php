<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/db.php';

exigirLogin();
exigirNivel(NIVEL_SUPERVISOR);
$usuario      = usuarioLogado();
$tituloPagina = 'Departamentos';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';

    switch ($action) {

        case 'listar':
            $resp = apiGet('api/departamentos');
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? []);

        case 'criar':
            $nome     = trim($dados['nome'] ?? '');
            $descricao = trim($dados['descricao'] ?? '');
            $cor      = $dados['cor'] ?? '#3b82f6';
            if (!$nome) jsonResponse(false, null, 'Nome obrigatório', 400);
            $resp = apiPost('api/departamentos', compact('nome', 'descricao', 'cor'));
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null, $resp['mensagem'] ?? '');

        case 'editar':
            $id = intval($dados['id'] ?? 0);
            if (!$id) jsonResponse(false, null, 'ID inválido', 400);
            $resp = apiPut("api/departamentos/{$id}", [
                'nome'      => trim($dados['nome'] ?? ''),
                'descricao' => trim($dados['descricao'] ?? ''),
                'cor'       => $dados['cor'] ?? '#3b82f6',
                'ativo'     => $dados['ativo'] ?? 1,
            ]);
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'excluir':
            $id = intval($dados['id'] ?? 0);
            if (!$id) jsonResponse(false, null, 'ID inválido', 400);
            $resp = apiDelete("api/departamentos/{$id}");
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'fila':
            $id   = intval($dados['id'] ?? 0);
            $resp = apiGet("api/departamentos/{$id}/fila");
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? []);

        case 'atribuir_da_fila':
            // Atribui conversa da fila a um atendente
            $conversaId  = intval($dados['conversa_id']  ?? 0);
            $atendenteId = intval($dados['atendente_id'] ?? 0);
            if (!$conversaId || !$atendenteId) jsonResponse(false, null, 'Dados incompletos', 400);
            $resp = apiPost("api/conversas/{$conversaId}/assumir", ['atendente_id' => $atendenteId]);
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'atendentes_dept':
            $id = intval($dados['departamento_id'] ?? 0);
            $db = getDB();
            $stmt = $db->prepare(
                "SELECT id, nome, nivel, status FROM chat_usuarios
                 WHERE ativo = 1 AND departamento_id = ? ORDER BY nome"
            );
            $stmt->execute([$id]);
            jsonResponse(true, $stmt->fetchAll());

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

// Pré-carrega departamentos
$respDepts = apiGet('api/departamentos');
$departamentos = $respDepts['dados'] ?? [];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.page-wrap { padding: 24px; max-width: 1200px; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
.page-titulo { font-size:22px; font-weight:700; }

/* Grid de departamentos */
.depts-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; margin-bottom:32px; }

.dept-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  position: relative;
  overflow: hidden;
  transition: border-color .2s;
}
.dept-card:hover { border-color: var(--border-light); }
.dept-card-barra {
  position: absolute;
  top:0; left:0; right:0;
  height: 3px;
}
.dept-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.dept-nome { font-size:16px; font-weight:700; }
.dept-desc { font-size:12.5px; color:var(--text-muted); margin-top:3px; }
.dept-acoes { display:flex; gap:6px; }

.dept-stats { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:14px; }
.dept-stat { text-align:center; }
.dept-stat-val { font-size:22px; font-weight:800; line-height:1; }
.dept-stat-label { font-size:11px; color:var(--text-muted); margin-top:3px; }

.btn-ver-fila { width:100%; }

/* Seção de fila */
.fila-secao { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; }
.fila-secao-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.fila-titulo { font-size:16px; font-weight:700; }
.fila-tabs { display:flex; gap:8px; overflow-x:auto; margin-bottom:16px; }
.fila-tab { padding:6px 16px; border-radius:20px; border:1px solid var(--border); background:none; color:var(--text-muted); font-size:13px; cursor:pointer; white-space:nowrap; transition:.15s; }
.fila-tab.ativo { background:var(--accent-light); color:var(--accent); border-color:var(--accent); }

.fila-tabela th { width:auto; }
.fila-vazia { padding:30px; text-align:center; color:var(--text-muted); font-size:13px; }

/* Indicador de status de atendente */
.status-atendente { display:inline-flex; align-items:center; gap:5px; font-size:12px; }
.status-atendente::before { content:''; display:inline-block; width:7px; height:7px; border-radius:50%; }
.status-atendente.online::before  { background:var(--success); }
.status-atendente.ausente::before { background:var(--warning); }
.status-atendente.offline::before { background:var(--text-muted); }
</style>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-titulo">Departamentos</div>
      <p style="color:var(--text-muted);font-size:13px;margin-top:4px">Gerencie departamentos e acompanhe as filas de atendimento</p>
    </div>
    <button class="btn btn-primary" onclick="abrirModal('modal-dept')">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Novo Departamento
    </button>
  </div>

  <!-- Cards de departamentos -->
  <div class="depts-grid" id="depts-grid">
    <?php foreach ($departamentos as $d): ?>
    <div class="dept-card" data-id="<?= $d['id'] ?>">
      <div class="dept-card-barra" style="background:<?= htmlspecialchars($d['cor']) ?>"></div>
      <div class="dept-card-header">
        <div>
          <div class="dept-nome"><?= htmlspecialchars($d['nome']) ?></div>
          <?php if ($d['descricao']): ?>
            <div class="dept-desc"><?= htmlspecialchars($d['descricao']) ?></div>
          <?php endif; ?>
        </div>
        <div class="dept-acoes">
          <button class="btn btn-secondary btn-sm btn-icon" title="Editar"
                  onclick='abrirEditar(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)'>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
          <?php if (eAdmin()): ?>
          <button class="btn btn-danger btn-sm btn-icon" title="Excluir"
                  onclick="confirmarExcluir(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nome'], ENT_QUOTES) ?>')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
              <path d="M9 6V4h6v2"/>
            </svg>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="dept-stats">
        <div class="dept-stat">
          <div class="dept-stat-val" style="color:var(--warning)"><?= intval($d['atendimentos_abertos'] ?? 0) ?></div>
          <div class="dept-stat-label">Em fila</div>
        </div>
        <div class="dept-stat">
          <div class="dept-stat-val" style="color:var(--success)"><?= intval($d['total_atendentes'] ?? 0) ?></div>
          <div class="dept-stat-label">Agentes</div>
        </div>
        <div class="dept-stat">
          <div class="dept-stat-val" style="color:var(--accent)"><?= intval($d['atendimentos_abertos'] ?? 0) ?></div>
          <div class="dept-stat-label">Ativos</div>
        </div>
      </div>

      <button class="btn btn-secondary btn-sm btn-ver-fila"
              onclick="verFila(<?= $d['id'] ?>, '<?= htmlspecialchars($d['nome'], ENT_QUOTES) ?>')">
        Ver fila de espera
      </button>
    </div>
    <?php endforeach; ?>

    <?php if (empty($departamentos)): ?>
    <div class="empty-state" style="grid-column:1/-1;padding:60px">
      <div class="empty-state-icon">🏢</div>
      <div class="empty-state-titulo">Nenhum departamento</div>
      <p style="font-size:13px">Crie departamentos para organizar o atendimento</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Seção de fila (aparece ao clicar em "Ver fila") -->
  <div class="fila-secao" id="fila-secao" style="display:none">
    <div class="fila-secao-header">
      <div class="fila-titulo" id="fila-titulo-dept">Fila: —</div>
      <button class="btn btn-secondary btn-sm" onclick="document.getElementById('fila-secao').style.display='none'">
        Fechar
      </button>
    </div>

    <!-- Tabs por status -->
    <div class="fila-tabs" id="fila-tabs"></div>

    <!-- Tabela de fila -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Contato</th>
            <th>Protocolo</th>
            <th>Aguardando</th>
            <th>Prioridade</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody id="fila-tbody">
          <tr><td colspan="6" class="fila-vazia">Selecione um departamento</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Atendentes do departamento -->
    <div style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
      <div style="font-size:13px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">
        Atendentes do departamento
      </div>
      <div id="atendentes-dept-lista" style="display:flex;flex-wrap:wrap;gap:8px"></div>
    </div>
  </div>
</div>

<!-- Modal: Criar/Editar departamento -->
<div class="modal-overlay" id="modal-dept" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo" id="modal-dept-titulo">Novo Departamento</div>
      <button class="modal-close" onclick="fecharModal('modal-dept')">✕</button>
    </div>

    <input type="hidden" id="dept-id" value="" />

    <div class="form-group">
      <label class="form-label">Nome *</label>
      <input type="text" class="form-control" id="dept-nome" placeholder="Ex: Suporte, Vendas..." maxlength="100" />
    </div>
    <div class="form-group">
      <label class="form-label">Descrição</label>
      <input type="text" class="form-control" id="dept-descricao" placeholder="Descrição opcional" maxlength="255" />
    </div>
    <div class="form-group">
      <label class="form-label">Cor de identificação</label>
      <div style="display:flex;gap:10px;align-items:center">
        <input type="color" id="dept-cor" value="#3b82f6"
               style="width:48px;height:40px;border-radius:8px;border:1px solid var(--border);background:transparent;cursor:pointer;padding:2px" />
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php foreach (['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'] as $cor): ?>
            <div onclick="document.getElementById('dept-cor').value='<?= $cor ?>'"
                 style="width:24px;height:24px;border-radius:50%;background:<?= $cor ?>;cursor:pointer;border:2px solid transparent;transition:.15s"
                 onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-dept')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarDept()">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal: Atribuir da fila -->
<div class="modal-overlay" id="modal-atribuir" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo">Atribuir Atendimento</div>
      <button class="modal-close" onclick="fecharModal('modal-atribuir')">✕</button>
    </div>
    <input type="hidden" id="atribuir-conversa-id" />
    <div class="form-group">
      <label class="form-label">Selecionar atendente</label>
      <select class="form-control" id="atribuir-atendente-sel">
        <option value="">Carregando...</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-atribuir')">Cancelar</button>
      <button class="btn btn-primary" onclick="confirmarAtribuir()">Atribuir</button>
    </div>
  </div>
</div>

<script>
let deptFilaAtual = null;
let atendentesFilaAtual = [];

// ── CRUD departamentos ─────────────────────────────────────────
function abrirEditar(dept) {
  document.getElementById('modal-dept-titulo').textContent = 'Editar Departamento';
  document.getElementById('dept-id').value        = dept.id;
  document.getElementById('dept-nome').value      = dept.nome || '';
  document.getElementById('dept-descricao').value = dept.descricao || '';
  document.getElementById('dept-cor').value       = dept.cor || '#3b82f6';
  abrirModal('modal-dept');
}

function abrirModal(id) {
  if (id === 'modal-dept' && !document.getElementById('dept-id').value) {
    document.getElementById('modal-dept-titulo').textContent = 'Novo Departamento';
    document.getElementById('dept-id').value        = '';
    document.getElementById('dept-nome').value      = '';
    document.getElementById('dept-descricao').value = '';
    document.getElementById('dept-cor').value       = '#3b82f6';
  }
  document.getElementById(id).style.display = 'flex';
}

async function salvarDept() {
  const id       = document.getElementById('dept-id').value;
  const nome     = document.getElementById('dept-nome').value.trim();
  const descricao = document.getElementById('dept-descricao').value.trim();
  const cor      = document.getElementById('dept-cor').value;

  if (!nome) { toast('Nome obrigatório.', 'error'); return; }

  const action = id ? 'editar' : 'criar';
  const payload = { action, nome, descricao, cor };
  if (id) payload.id = parseInt(id);

  const r = await postAjax('departamentos.php', payload);
  if (r.sucesso) {
    toast(id ? 'Departamento atualizado!' : 'Departamento criado!', 'success');
    fecharModal('modal-dept');
    setTimeout(() => location.reload(), 800);
  } else {
    toast(r.mensagem || 'Erro ao salvar.', 'error');
  }
}

async function confirmarExcluir(id, nome) {
  if (!confirm(`Excluir o departamento "${nome}"?\n\nAtenção: conversas vinculadas perderão o departamento.`)) return;
  const r = await postAjax('departamentos.php', { action: 'excluir', id });
  if (r.sucesso) {
    toast('Departamento excluído.', 'success');
    setTimeout(() => location.reload(), 800);
  } else {
    toast(r.mensagem || 'Erro ao excluir.', 'error');
  }
}

// ── Fila do departamento ───────────────────────────────────────
async function verFila(deptId, deptNome) {
  deptFilaAtual = deptId;
  document.getElementById('fila-titulo-dept').textContent = `Fila: ${deptNome}`;
  document.getElementById('fila-secao').style.display = 'block';
  document.getElementById('fila-secao').scrollIntoView({ behavior: 'smooth' });

  await Promise.all([
    carregarFila(deptId),
    carregarAtendentes(deptId),
  ]);
}

async function carregarFila(deptId) {
  const tbody = document.getElementById('fila-tbody');
  tbody.innerHTML = '<tr><td colspan="6" class="fila-vazia"><div class="spinner" style="margin:0 auto"></div></td></tr>';

  const r = await postAjax('departamentos.php', { action: 'fila', id: deptId });
  const fila = r.dados || [];

  if (!fila.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="fila-vazia">✅ Fila vazia — nenhum atendimento aguardando</td></tr>';
    return;
  }

  tbody.innerHTML = fila.map((item, idx) => {
    const tempo = calcularTempo(item.entrada_em);
    const prio  = item.prioridade <= 3 ? '🔴 Alta' : item.prioridade <= 6 ? '🟡 Normal' : '🟢 Baixa';
    return `
    <tr>
      <td style="font-weight:700;color:var(--accent)">${idx + 1}º</td>
      <td>
        <div style="font-weight:600">${esc(item.contato_nome || item.contato_numero || '—')}</div>
        <div style="font-size:11px;color:var(--text-muted)">${esc(item.contato_numero || '')}</div>
      </td>
      <td><span style="font-family:monospace;font-size:12px">${esc(item.protocolo || '—')}</span></td>
      <td>
        <span style="color:${tempo.minutos > 10 ? 'var(--danger)' : 'var(--text-primary)'};font-weight:500">
          ${tempo.texto}
        </span>
      </td>
      <td><span class="pill ${item.prioridade <= 3 ? 'pill-danger' : item.prioridade <= 6 ? 'pill-warning' : 'pill-success'}">${prio}</span></td>
      <td>
        <button class="btn btn-primary btn-sm" onclick="abrirAtribuir(${item.conversa_id})">
          Atribuir
        </button>
      </td>
    </tr>`;
  }).join('');
}

async function carregarAtendentes(deptId) {
  const r = await postAjax('departamentos.php', { action: 'atendentes_dept', departamento_id: deptId });
  atendentesFilaAtual = r.dados || [];
  const lista = document.getElementById('atendentes-dept-lista');
  if (!atendentesFilaAtual.length) {
    lista.innerHTML = '<span style="font-size:13px;color:var(--text-muted)">Nenhum atendente neste departamento</span>';
    return;
  }
  lista.innerHTML = atendentesFilaAtual.map(a => `
    <div style="background:var(--bg-hover);border:1px solid var(--border);border-radius:var(--radius);padding:8px 12px;display:flex;align-items:center;gap:8px">
      <span class="status-atendente ${a.status}">${esc(a.nome)}</span>
      <span class="pill ${a.nivel === 'admin' ? 'pill-info' : a.nivel === 'supervisor' ? 'pill-warning' : 'pill-muted'}" style="font-size:10px">${a.nivel}</span>
    </div>
  `).join('');
}

// ── Atribuir da fila ───────────────────────────────────────────
function abrirAtribuir(conversaId) {
  document.getElementById('atribuir-conversa-id').value = conversaId;
  const sel = document.getElementById('atribuir-atendente-sel');
  sel.innerHTML = atendentesFilaAtual.length
    ? atendentesFilaAtual.map(a => `<option value="${a.id}">${esc(a.nome)} (${a.status})</option>`).join('')
    : '<option value="">Nenhum atendente disponível</option>';
  abrirModal('modal-atribuir');
}

async function confirmarAtribuir() {
  const conversaId  = document.getElementById('atribuir-conversa-id').value;
  const atendenteId = document.getElementById('atribuir-atendente-sel').value;
  if (!atendenteId) { toast('Selecione um atendente.', 'error'); return; }

  const r = await postAjax('departamentos.php', {
    action: 'atribuir_da_fila', conversa_id: parseInt(conversaId), atendente_id: parseInt(atendenteId),
  });
  if (r.sucesso) {
    toast('Atendimento atribuído com sucesso!', 'success');
    fecharModal('modal-atribuir');
    carregarFila(deptFilaAtual);
  } else {
    toast(r.mensagem || 'Erro ao atribuir.', 'error');
  }
}

// ── Helpers ────────────────────────────────────────────────────
function calcularTempo(iso) {
  if (!iso) return { texto: '—', minutos: 0 };
  const minutos = Math.floor((Date.now() - new Date(iso)) / 60000);
  if (minutos < 1)  return { texto: 'Agora',           minutos };
  if (minutos < 60) return { texto: `${minutos}min`,   minutos };
  const h = Math.floor(minutos / 60), m = minutos % 60;
  return { texto: `${h}h${m > 0 ? m + 'min' : ''}`, minutos };
}

// Socket.IO — atualiza fila em tempo real
socket.on('fila_atualizada', ({ departamento_id }) => {
  if (deptFilaAtual && departamento_id === deptFilaAtual) {
    carregarFila(deptFilaAtual);
  }
});
socket.on('usuario_status', () => {
  if (deptFilaAtual) carregarAtendentes(deptFilaAtual);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
