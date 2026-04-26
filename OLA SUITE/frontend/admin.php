<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();
exigirNivel(NIVEL_ADMIN);
$usuario      = usuarioLogado();
$tituloPagina = 'Administração';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';

    switch ($action) {

        // ── Usuários ───────────────────────────────────────────
        case 'listar_usuarios':
            $r = apiGet('api/usuarios');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'criar_usuario':
            $nome  = trim($dados['nome']  ?? '');
            $email = trim($dados['email'] ?? '');
            $senha = trim($dados['senha'] ?? '');
            if (!$nome || !$email || !$senha) {
                jsonResponse(false, null, 'Nome, e-mail e senha são obrigatórios', 400);
            }
            $r = apiPost('api/usuarios', [
                'nome'           => $nome,
                'email'          => $email,
                'senha'          => $senha,
                'nivel'          => $dados['nivel'] ?? 'atendente',
                'departamento_id'=> intval($dados['departamento_id'] ?? 0) ?: null,
            ]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null, $r['mensagem'] ?? '');

        case 'editar_usuario':
            $id = intval($dados['id'] ?? 0);
            if (!$id) jsonResponse(false, null, 'ID inválido', 400);
            $payload = [
                'nome'           => trim($dados['nome']  ?? ''),
                'email'          => trim($dados['email'] ?? ''),
                'nivel'          => $dados['nivel'] ?? 'atendente',
                'departamento_id'=> intval($dados['departamento_id'] ?? 0) ?: null,
                'ativo'          => $dados['ativo'] ? 1 : 0,
            ];
            if (!empty($dados['senha'])) $payload['senha'] = $dados['senha'];
            $r = apiPut("api/usuarios/{$id}", $payload);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        case 'excluir_usuario':
            $id = intval($dados['id'] ?? 0);
            $r  = apiDelete("api/usuarios/{$id}");
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        // ── Configurações ──────────────────────────────────────
        case 'listar_configuracoes':
            $r = apiGet('api/configuracoes');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'salvar_configuracoes':
            $configs = $dados['configuracoes'] ?? [];
            $r = apiPost('api/configuracoes/lote', ['configuracoes' => $configs]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

// Pré-carrega departamentos para o select do formulário
$departamentos = apiGet('api/departamentos')['dados'] ?? [];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.page-wrap  { padding: 24px; max-width: 1200px; }
.page-titulo { font-size:22px; font-weight:700; margin-bottom:20px; }

.admin-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:24px; }
.admin-tab  {
  padding:10px 22px; border:none; background:none;
  color:var(--text-muted); font-size:14px; font-weight:500;
  cursor:pointer; border-bottom:2px solid transparent; transition:.2s;
}
.admin-tab:hover { color:var(--text-primary); }
.admin-tab.ativo { color:var(--accent); border-bottom-color:var(--accent); }
.tab-panel { display:none; }
.tab-panel.ativo { display:block; }

/* Tabela usuários */
.user-avatar {
  width:34px; height:34px; border-radius:50%;
  background:var(--accent-light); color:var(--accent);
  display:inline-flex; align-items:center; justify-content:center;
  font-weight:700; font-size:14px;
}
.nivel-badge { padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; }
.nivel-admin      { background:rgba(239,68,68,.12);  color:var(--danger); }
.nivel-supervisor { background:rgba(245,158,11,.12); color:var(--warning); }
.nivel-atendente  { background:rgba(79,110,247,.12); color:var(--accent); }

.status-user { display:inline-flex; align-items:center; gap:5px; font-size:12px; }
.status-user::before { content:''; width:7px; height:7px; border-radius:50%; display:inline-block; }
.status-user.online::before  { background:var(--success); }
.status-user.ausente::before { background:var(--warning); }
.status-user.offline::before { background:var(--text-muted); }

/* Configurações */
.config-grupo { margin-bottom:28px; }
.config-grupo-titulo {
  font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
  color:var(--text-muted); margin-bottom:14px; padding-bottom:8px;
  border-bottom:1px solid var(--border);
}
.config-linha {
  display:grid; grid-template-columns:240px 1fr; gap:16px;
  align-items:center; padding:10px 0; border-bottom:1px solid var(--border);
}
.config-linha:last-child { border-bottom:none; }
.config-label { font-size:13.5px; font-weight:500; }
.config-desc  { font-size:12px; color:var(--text-muted); margin-top:2px; }
.config-ctrl  { display:flex; align-items:center; gap:10px; }

/* Toggle switch */
.toggle-sw {
  position:relative; display:inline-block; width:46px; height:24px;
}
.toggle-sw input { opacity:0; width:0; height:0; }
.toggle-slider {
  position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0;
  background:var(--bg-hover); border-radius:24px; transition:.3s;
}
.toggle-slider::before {
  content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px;
  background:#fff; border-radius:50%; transition:.3s;
}
.toggle-sw input:checked + .toggle-slider { background:var(--success); }
.toggle-sw input:checked + .toggle-slider::before { transform:translateX(22px); }

/* Painel de logs */
.log-item { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); font-size:13px; }
.log-item:last-child { border-bottom:none; }
.log-hora { color:var(--text-muted); font-size:12px; white-space:nowrap; flex-shrink:0; font-family:monospace; }
.log-acao { font-weight:600; flex-shrink:0; }
.log-detalhe { color:var(--text-secondary); }
</style>

<div class="page-wrap">
  <div class="page-titulo">⚙️ Administração</div>

  <div class="admin-tabs">
    <button class="admin-tab ativo" onclick="trocarTab('usuarios')">👥 Usuários</button>
    <button class="admin-tab" onclick="trocarTab('configs')">⚙️ Configurações</button>
    <button class="admin-tab" onclick="trocarTab('logs')">📋 Logs de Auditoria</button>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1 — Usuários
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel ativo" id="tab-usuarios">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:16px;font-weight:700">Usuários e Atendentes</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:3px">
          Gerencie os acessos ao sistema
        </div>
      </div>
      <button class="btn btn-primary" onclick="abrirModalUsuario()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Novo Usuário
      </button>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"></th>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Nível</th>
              <th>Departamento</th>
              <th>Status</th>
              <th>Último acesso</th>
              <th style="width:110px"></th>
            </tr>
          </thead>
          <tbody id="usuarios-tbody">
            <tr><td colspan="8" style="text-align:center;padding:40px">
              <div class="spinner" style="margin:0 auto"></div>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 2 — Configurações
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-configs">
    <div style="max-width:700px">

      <!-- Geral -->
      <div class="config-grupo">
        <div class="config-grupo-titulo">Geral</div>

        <div class="config-linha">
          <div>
            <div class="config-label">Nome da empresa</div>
            <div class="config-desc">Exibido no sistema e nos relatórios</div>
          </div>
          <div class="config-ctrl">
            <input type="text" class="form-control" id="cfg-nome_empresa" placeholder="OlaSuite" />
          </div>
        </div>

        <div class="config-linha">
          <div>
            <div class="config-label">Prefixo do protocolo</div>
            <div class="config-desc">Ex: OLA → protocolo OLA20240512123456</div>
          </div>
          <div class="config-ctrl">
            <input type="text" class="form-control" id="cfg-protocolo_prefixo" style="width:100px" maxlength="10" placeholder="OLA" />
          </div>
        </div>

        <div class="config-linha">
          <div>
            <div class="config-label">Fuso horário</div>
          </div>
          <div class="config-ctrl">
            <select class="form-control" id="cfg-fuso_horario">
              <option value="America/Sao_Paulo">America/Sao_Paulo (GMT-3)</option>
              <option value="America/Manaus">America/Manaus (GMT-4)</option>
              <option value="America/Belem">America/Belem (GMT-3)</option>
              <option value="America/Fortaleza">America/Fortaleza (GMT-3)</option>
              <option value="America/Recife">America/Recife (GMT-3)</option>
              <option value="America/Noronha">America/Noronha (GMT-2)</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Atendimento -->
      <div class="config-grupo">
        <div class="config-grupo-titulo">Atendimento</div>

        <div class="config-linha">
          <div>
            <div class="config-label">Distribuição automática</div>
            <div class="config-desc">Atribui conversas automaticamente ao atendente com menor carga</div>
          </div>
          <div class="config-ctrl">
            <label class="toggle-sw">
              <input type="checkbox" id="cfg-distribuicao_automatica" />
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>

        <div class="config-linha">
          <div>
            <div class="config-label">Máx. atendimentos por agente</div>
            <div class="config-desc">Limite de atendimentos simultâneos</div>
          </div>
          <div class="config-ctrl">
            <input type="number" class="form-control" id="cfg-max_atendimentos_agente"
                   style="width:90px" min="1" max="100" />
          </div>
        </div>

        <div class="config-linha">
          <div>
            <div class="config-label">Tempo de ociosidade (min)</div>
            <div class="config-desc">Muda status para "ausente" após inatividade</div>
          </div>
          <div class="config-ctrl">
            <input type="number" class="form-control" id="cfg-tempo_ociosidade_minutos"
                   style="width:90px" min="5" max="480" />
          </div>
        </div>
      </div>

      <!-- Notificações -->
      <div class="config-grupo">
        <div class="config-grupo-titulo">Notificações</div>

        <div class="config-linha">
          <div>
            <div class="config-label">Som de notificação</div>
            <div class="config-desc">Toca som ao receber nova mensagem</div>
          </div>
          <div class="config-ctrl">
            <label class="toggle-sw">
              <input type="checkbox" id="cfg-som_notificacao_ativo" />
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Integração -->
      <div class="config-grupo">
        <div class="config-grupo-titulo">Integração Node.js</div>

        <div class="config-linha">
          <div>
            <div class="config-label">URL da API Node</div>
            <div class="config-desc">Endereço do serviço backend</div>
          </div>
          <div class="config-ctrl">
            <input type="text" class="form-control" id="cfg-node_api_url"
                   placeholder="http://localhost:3000" />
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:8px">
        <button class="btn btn-primary" onclick="salvarConfiguracoes()">
          Salvar Configurações
        </button>
        <button class="btn btn-secondary" onclick="carregarConfiguracoes()">Resetar</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 3 — Logs
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-logs">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;max-width:900px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="font-size:16px;font-weight:700">Logs de Auditoria</div>
        <button class="btn btn-secondary btn-sm" onclick="carregarLogs()">Atualizar</button>
      </div>
      <div id="logs-lista">
        <div style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- Modal: Criar / Editar Usuário -->
<div class="modal-overlay" id="modal-usuario" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo" id="modal-usuario-titulo">Novo Usuário</div>
      <button class="modal-close" onclick="fecharModal('modal-usuario')">✕</button>
    </div>
    <input type="hidden" id="usuario-id" />

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div class="form-group" style="margin:0">
        <label class="form-label">Nome completo *</label>
        <input type="text" class="form-control" id="usuario-nome" placeholder="Maria Silva" />
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">E-mail *</label>
        <input type="email" class="form-control" id="usuario-email" placeholder="maria@empresa.com" />
      </div>
    </div>

    <div class="form-group" style="margin-top:14px">
      <label class="form-label" id="lbl-senha">Senha *</label>
      <input type="password" class="form-control" id="usuario-senha"
             placeholder="Mínimo 6 caracteres" autocomplete="new-password" />
      <small style="color:var(--text-muted);font-size:12px;display:block;margin-top:4px" id="hint-senha">
        Deixe em branco para manter a senha atual (ao editar)
      </small>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:2px">
      <div class="form-group" style="margin:0">
        <label class="form-label">Nível de acesso *</label>
        <select class="form-control" id="usuario-nivel">
          <option value="atendente">Atendente</option>
          <option value="supervisor">Supervisor</option>
          <option value="admin">Administrador</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Departamento</label>
        <select class="form-control" id="usuario-departamento">
          <option value="">— Sem departamento —</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group" style="margin-top:14px;display:flex;align-items:center;gap:10px" id="campo-ativo" style="display:none">
      <label class="toggle-sw">
        <input type="checkbox" id="usuario-ativo" checked />
        <span class="toggle-slider"></span>
      </label>
      <div>
        <div style="font-size:13.5px;font-weight:500">Usuário ativo</div>
        <div style="font-size:12px;color:var(--text-muted)">Desativar impede o login</div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-usuario')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarUsuario()">Salvar</button>
    </div>
  </div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
// TABS
// ══════════════════════════════════════════════════════════════
function trocarTab(tab) {
  document.querySelectorAll('.admin-tab').forEach((b, i) => {
    const tabs = ['usuarios','configs','logs'];
    b.classList.toggle('ativo', tabs[i] === tab);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('ativo'));
  document.getElementById(`tab-${tab}`).classList.add('ativo');

  if (tab === 'configs' && !document.getElementById('cfg-nome_empresa').dataset.loaded) carregarConfiguracoes();
  if (tab === 'logs'    && !document.getElementById('logs-lista').dataset.loaded)       carregarLogs();
}

// ══════════════════════════════════════════════════════════════
// USUÁRIOS
// ══════════════════════════════════════════════════════════════
let _usuarios = [];

async function carregarUsuarios() {
  const tbody = document.getElementById('usuarios-tbody');
  const r = await postAjax('admin.php', { action: 'listar_usuarios' });
  _usuarios = r.dados || [];

  if (!_usuarios.length) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">Nenhum usuário encontrado</td></tr>';
    return;
  }

  tbody.innerHTML = _usuarios.map(u => {
    const ini  = (u.nome || '?')[0].toUpperCase();
    const acesso = u.ultimo_acesso ? formatarData(u.ultimo_acesso) : 'Nunca';
    return `
    <tr>
      <td><div class="user-avatar">${ini}</div></td>
      <td>
        <div style="font-weight:600">${esc(u.nome)}</div>
        ${!u.ativo ? '<span class="pill pill-danger" style="font-size:10px">Inativo</span>' : ''}
      </td>
      <td style="color:var(--text-secondary);font-size:13px">${esc(u.email)}</td>
      <td><span class="nivel-badge nivel-${u.nivel}">${esc(u.nivel)}</span></td>
      <td style="font-size:13px;color:var(--text-secondary)">${esc(u.departamento_nome || '—')}</td>
      <td><span class="status-user ${u.status}">${esc(u.status || 'offline')}</span></td>
      <td style="font-size:12px;color:var(--text-muted)">${acesso}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn-secondary btn-sm btn-icon" title="Editar"
                  onclick="editarUsuario(${u.id})">✏️</button>
          ${u.id !== OLA.usuarioId ? `
          <button class="btn btn-danger btn-sm btn-icon" title="Desativar"
                  onclick="desativarUsuario(${u.id}, '${esc(u.nome)}')">🗑️</button>` : ''}
        </div>
      </td>
    </tr>`;
  }).join('');
}

function abrirModalUsuario() {
  document.getElementById('modal-usuario-titulo').textContent = 'Novo Usuário';
  document.getElementById('usuario-id').value         = '';
  document.getElementById('usuario-nome').value       = '';
  document.getElementById('usuario-email').value      = '';
  document.getElementById('usuario-senha').value      = '';
  document.getElementById('usuario-nivel').value      = 'atendente';
  document.getElementById('usuario-departamento').value = '';
  document.getElementById('usuario-ativo').checked    = true;
  document.getElementById('lbl-senha').textContent    = 'Senha *';
  document.getElementById('hint-senha').style.display = 'none';
  document.getElementById('campo-ativo').style.display = 'none';
  abrirModal('modal-usuario');
}

function editarUsuario(id) {
  const u = _usuarios.find(x => x.id === id);
  if (!u) return;
  document.getElementById('modal-usuario-titulo').textContent = 'Editar Usuário';
  document.getElementById('usuario-id').value          = u.id;
  document.getElementById('usuario-nome').value        = u.nome;
  document.getElementById('usuario-email').value       = u.email;
  document.getElementById('usuario-senha').value       = '';
  document.getElementById('usuario-nivel').value       = u.nivel;
  document.getElementById('usuario-departamento').value = u.departamento_id || '';
  document.getElementById('usuario-ativo').checked     = !!u.ativo;
  document.getElementById('lbl-senha').textContent     = 'Nova senha';
  document.getElementById('hint-senha').style.display  = 'block';
  document.getElementById('campo-ativo').style.display = 'flex';
  abrirModal('modal-usuario');
}

async function salvarUsuario() {
  const id    = document.getElementById('usuario-id').value;
  const nome  = document.getElementById('usuario-nome').value.trim();
  const email = document.getElementById('usuario-email').value.trim();
  const senha = document.getElementById('usuario-senha').value;
  const nivel = document.getElementById('usuario-nivel').value;
  const deptId = parseInt(document.getElementById('usuario-departamento').value) || null;
  const ativo  = document.getElementById('usuario-ativo').checked;

  if (!nome || !email) { toast('Nome e e-mail são obrigatórios.', 'error'); return; }
  if (!id && !senha)   { toast('Senha obrigatória para novo usuário.', 'error'); return; }
  if (senha && senha.length < 6) { toast('Senha deve ter no mínimo 6 caracteres.', 'error'); return; }

  const payload = { action: id ? 'editar_usuario' : 'criar_usuario',
                    nome, email, nivel, departamento_id: deptId, ativo: ativo ? 1 : 0 };
  if (id) payload.id = parseInt(id);
  if (senha) payload.senha = senha;

  const r = await postAjax('admin.php', payload);
  if (r.sucesso) {
    toast(id ? 'Usuário atualizado!' : 'Usuário criado!', 'success');
    fecharModal('modal-usuario');
    carregarUsuarios();
  } else {
    toast(r.mensagem || 'Erro ao salvar usuário.', 'error');
  }
}

async function desativarUsuario(id, nome) {
  if (!confirm(`Desativar o usuário "${nome}"?\nEle não conseguirá mais fazer login.`)) return;
  const r = await postAjax('admin.php', { action: 'excluir_usuario', id });
  if (r.sucesso) { toast('Usuário desativado.', 'success'); carregarUsuarios(); }
  else toast(r.mensagem || 'Erro.', 'error');
}

// ══════════════════════════════════════════════════════════════
// CONFIGURAÇÕES
// ══════════════════════════════════════════════════════════════
const CAMPOS_BOOL = ['distribuicao_automatica','som_notificacao_ativo'];

async function carregarConfiguracoes() {
  document.getElementById('cfg-nome_empresa').dataset.loaded = '1';
  const r = await postAjax('admin.php', { action: 'listar_configuracoes' });
  const cfg = r.dados?.geral ?? {};
  const atend = r.dados?.atendimento ?? {};
  const notif = r.dados?.notificacao ?? {};
  const integ = r.dados?.integracao ?? {};

  const todos = { ...cfg, ...atend, ...notif, ...integ };

  for (const [chave, valor] of Object.entries(todos)) {
    const el = document.getElementById(`cfg-${chave}`);
    if (!el) continue;
    if (el.type === 'checkbox') {
      el.checked = valor === '1';
    } else {
      el.value = valor ?? '';
    }
  }
}

async function salvarConfiguracoes() {
  const chaves = [
    'nome_empresa','protocolo_prefixo','fuso_horario',
    'distribuicao_automatica','max_atendimentos_agente','tempo_ociosidade_minutos',
    'som_notificacao_ativo','node_api_url',
  ];
  const configuracoes = {};
  for (const ch of chaves) {
    const el = document.getElementById(`cfg-${ch}`);
    if (!el) continue;
    configuracoes[ch] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value.trim();
  }
  const r = await postAjax('admin.php', { action: 'salvar_configuracoes', configuracoes });
  if (r.sucesso) toast('Configurações salvas!', 'success');
  else toast(r.mensagem || 'Erro ao salvar.', 'error');
}

// ══════════════════════════════════════════════════════════════
// LOGS
// ══════════════════════════════════════════════════════════════
async function carregarLogs() {
  const lista = document.getElementById('logs-lista');
  lista.dataset.loaded = '1';
  lista.innerHTML = '<div style="text-align:center;padding:20px"><div class="spinner" style="margin:0 auto"></div></div>';

  // Busca direto do banco via PHP (logs não têm rota na API Node)
  try {
    const r = await fetch('admin_logs.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await r.json();
    const logs = data.dados || [];

    if (!logs.length) {
      lista.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:30px">Nenhum log registrado</p>';
      return;
    }

    lista.innerHTML = logs.map(l => `
    <div class="log-item">
      <span class="log-hora">${formatarData(l.criado_em)}</span>
      <span class="log-acao">${esc(l.acao)}</span>
      <span style="color:var(--text-muted)">·</span>
      <span class="log-detalhe">${esc(l.usuario_nome || 'Sistema')} ${l.detalhe ? '— ' + esc(l.detalhe) : ''}</span>
      ${l.ip ? `<span style="margin-left:auto;font-size:11px;color:var(--text-muted);font-family:monospace">${esc(l.ip)}</span>` : ''}
    </div>`).join('');
  } catch {
    lista.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:30px">Erro ao carregar logs</p>';
  }
}

// Logs são carregados via admin_logs.php

// ── Inicialização ──────────────────────────────────────────────
carregarUsuarios();

// Socket.IO — atualiza status de usuário em tempo real
socket.on('usuario_status', () => carregarUsuarios());
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
