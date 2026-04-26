<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();
$usuario      = usuarioLogado();
$tituloPagina = 'Contatos';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';

    switch ($action) {

        case 'listar':
            $resp = apiGet('api/contatos', [
                'busca'  => $dados['busca']  ?? '',
                'limite' => $dados['limite'] ?? 50,
                'pagina' => $dados['pagina'] ?? 1,
            ]);
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? [], '', 200);
            break;

        case 'detalhes':
            $id   = intval($dados['id'] ?? 0);
            $resp = apiGet("api/contatos/{$id}");
            jsonResponse($resp['sucesso'] ?? false, $resp['dados'] ?? null);

        case 'editar':
            $id = intval($dados['id'] ?? 0);
            if (!$id) jsonResponse(false, null, 'ID inválido', 400);
            $resp = apiPut("api/contatos/{$id}", [
                'nome'        => trim($dados['nome']        ?? ''),
                'email'       => trim($dados['email']       ?? ''),
                'tags'        => trim($dados['tags']        ?? ''),
                'observacoes' => trim($dados['observacoes'] ?? ''),
            ]);
            jsonResponse($resp['sucesso'] ?? false, null, $resp['mensagem'] ?? '');

        case 'bloquear':
            $id   = intval($dados['id'] ?? 0);
            $resp = apiPost("api/contatos/{$id}/bloquear");
            jsonResponse($resp['sucesso'] ?? false, $resp ?? null);

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.page-wrap { padding: 24px; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-titulo { font-size:22px; font-weight:700; }

.contatos-layout { display:grid; grid-template-columns:1fr 360px; gap:16px; }
@media(max-width:900px){ .contatos-layout{ grid-template-columns:1fr; } }

/* Tabela */
.contatos-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.contatos-toolbar { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.busca-contatos { flex:1; min-width:200px; background:var(--bg-tertiary); border:1px solid var(--border); border-radius:var(--radius); padding:8px 12px; color:var(--text-primary); font-size:13px; outline:none; }
.busca-contatos:focus { border-color:var(--accent); }

/* Avatar contato */
.contato-avatar {
  width:34px; height:34px; border-radius:50%;
  background:var(--bg-hover); display:inline-flex;
  align-items:center; justify-content:center;
  font-size:14px; font-weight:700; color:var(--text-secondary);
}

/* Painel detalhes */
.detalhes-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); overflow:hidden; height:fit-content;
  position:sticky; top:16px;
}
.detalhes-vazio {
  padding:50px 20px; text-align:center; color:var(--text-muted);
}
.detalhes-header { padding:16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
.detalhes-avatar { width:48px;height:48px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:var(--accent); }
.detalhes-nome   { font-size:16px;font-weight:700; }
.detalhes-num    { font-size:12.5px;color:var(--text-muted); }
.detalhes-body   { padding:16px; }
.detalhe-campo   { margin-bottom:14px; }
.detalhe-label   { font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px; }
.detalhe-form    { width:100%;background:var(--bg-tertiary);border:1px solid var(--border);border-radius:var(--radius);padding:8px 10px;color:var(--text-primary);font-size:13px;outline:none; }
.detalhe-form:focus { border-color:var(--accent); }
.historico-item { padding:8px 0; border-bottom:1px solid var(--border); }
.historico-item:last-child { border-bottom:none; }

/* Tag pills */
.tag-pill { display:inline-flex;padding:3px 9px;border-radius:20px;background:var(--bg-hover);border:1px solid var(--border);font-size:11px;color:var(--text-secondary);gap:5px;margin:2px; }
</style>

<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-titulo">Contatos</div>
      <p style="color:var(--text-muted);font-size:13px;margin-top:4px">
        Contatos cadastrados automaticamente ao receber mensagens
      </p>
    </div>
    <div style="font-size:13px;color:var(--text-muted)" id="total-contatos"></div>
  </div>

  <div class="contatos-layout">

    <!-- Tabela de contatos -->
    <div class="contatos-card">
      <div class="contatos-toolbar">
        <input type="text" class="busca-contatos" id="busca-contatos"
               placeholder="🔍  Buscar por nome ou número..." />
        <button class="btn btn-secondary btn-sm" onclick="carregarContatos(1)">Atualizar</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:44px"></th>
              <th>Nome</th>
              <th>Número</th>
              <th>Tags</th>
              <th>Status</th>
              <th style="width:80px"></th>
            </tr>
          </thead>
          <tbody id="contatos-tbody">
            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">
              <div class="spinner" style="margin:0 auto"></div>
            </td></tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:center;justify-content:space-between">
        <button class="btn btn-secondary btn-sm" id="btn-ant" onclick="mudarPag(-1)" disabled>← Anterior</button>
        <span style="font-size:13px;color:var(--text-muted)" id="pag-info">—</span>
        <button class="btn btn-secondary btn-sm" id="btn-prox" onclick="mudarPag(1)">Próxima →</button>
      </div>
    </div>

    <!-- Painel de detalhes -->
    <div class="detalhes-card" id="detalhes-card">
      <div class="detalhes-vazio" id="detalhes-vazio">
        <div style="font-size:40px;margin-bottom:10px">👤</div>
        <div style="font-size:14px;color:var(--text-secondary)">Selecione um contato</div>
      </div>

      <div id="detalhes-conteudo" style="display:none">
        <div class="detalhes-header">
          <div class="detalhes-avatar" id="det-avatar">?</div>
          <div>
            <div class="detalhes-nome" id="det-nome">—</div>
            <div class="detalhes-num"  id="det-num">—</div>
          </div>
        </div>
        <div class="detalhes-body">
          <div class="detalhe-campo">
            <div class="detalhe-label">Nome personalizado</div>
            <input type="text" class="detalhe-form" id="det-form-nome" placeholder="Nome de exibição" />
          </div>
          <div class="detalhe-campo">
            <div class="detalhe-label">E-mail</div>
            <input type="email" class="detalhe-form" id="det-form-email" placeholder="email@exemplo.com" />
          </div>
          <div class="detalhe-campo">
            <div class="detalhe-label">Tags (separadas por vírgula)</div>
            <input type="text" class="detalhe-form" id="det-form-tags" placeholder="vip, suporte, ativo" />
          </div>
          <div class="detalhe-campo">
            <div class="detalhe-label">Observações</div>
            <textarea class="detalhe-form" id="det-form-obs" rows="3" placeholder="Anotações internas..."></textarea>
          </div>
          <div style="display:flex;gap:8px;margin-bottom:16px">
            <button class="btn btn-primary" style="flex:1" onclick="salvarContato()">Salvar</button>
            <button class="btn btn-secondary btn-sm btn-icon" id="btn-bloquear" onclick="bloquearContato()" title="Bloquear/Desbloquear">🚫</button>
          </div>

          <!-- Histórico de atendimentos -->
          <div>
            <div class="detalhe-label" style="margin-bottom:10px">Histórico de atendimentos</div>
            <div id="det-historico">—</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
let paginaAtual  = 1;
const limitePag  = 50;
let totalContatos = 0;
let contatoSelecionado = null;
let debTimer;

// ── Carregar contatos ──────────────────────────────────────────
async function carregarContatos(pag = 1) {
  paginaAtual = pag;
  const busca = document.getElementById('busca-contatos').value.trim();
  const tbody = document.getElementById('contatos-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></td></tr>';

  const r = await postAjax('contatos.php', {
    action: 'listar', busca, limite: limitePag, pagina: pag,
  });

  totalContatos = r.total || 0;
  document.getElementById('total-contatos').textContent = `${totalContatos} contato${totalContatos !== 1 ? 's' : ''}`;

  const contatos = r.dados || [];
  if (!contatos.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">
      ${busca ? 'Nenhum contato encontrado para "' + esc(busca) + '"' : 'Nenhum contato cadastrado ainda'}
    </td></tr>`;
  } else {
    tbody.innerHTML = contatos.map(c => {
      const nome = esc(c.nome || c.nome_push || c.numero || '—');
      const ini  = nome[0].toUpperCase();
      const tags = (c.tags || '').split(',').filter(Boolean).map(t =>
        `<span class="tag-pill">${esc(t.trim())}</span>`).join('');
      return `
      <tr onclick="verContato(${c.id})" style="cursor:pointer">
        <td><div class="contato-avatar">${ini}</div></td>
        <td><div style="font-weight:500">${nome}</div>${c.nome_push && c.nome ? `<div style="font-size:11px;color:var(--text-muted)">${esc(c.nome_push)}</div>` : ''}</td>
        <td><span style="font-family:monospace;font-size:12.5px">+${esc(c.numero || '')}</span></td>
        <td>${tags || '<span style="color:var(--text-muted);font-size:12px">—</span>'}</td>
        <td>${c.bloqueado ? '<span class="pill pill-danger">Bloqueado</span>' : '<span class="pill pill-success">Ativo</span>'}</td>
        <td>
          <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();abrirInbox(${c.id})">Chat</button>
        </td>
      </tr>`;
    }).join('');
  }

  // Paginação
  const totalPags = Math.ceil(totalContatos / limitePag);
  document.getElementById('pag-info').textContent  = `Pág. ${paginaAtual} de ${totalPags || 1}`;
  document.getElementById('btn-ant').disabled  = paginaAtual <= 1;
  document.getElementById('btn-prox').disabled = paginaAtual >= totalPags;
}

function mudarPag(delta) {
  carregarContatos(paginaAtual + delta);
}

// ── Ver detalhes do contato ────────────────────────────────────
async function verContato(id) {
  // Destaca linha
  document.querySelectorAll('tbody tr').forEach(tr => tr.style.background = '');
  const tr = document.querySelector(`tbody tr[onclick*="${id}"]`);
  if (tr) tr.style.background = 'var(--bg-active)';

  const r = await postAjax('contatos.php', { action: 'detalhes', id });
  if (!r.sucesso || !r.dados) { toast('Erro ao carregar contato.', 'error'); return; }

  contatoSelecionado = r.dados;
  const c = r.dados;
  const nome = c.nome || c.nome_push || c.numero || '—';

  document.getElementById('detalhes-vazio').style.display    = 'none';
  document.getElementById('detalhes-conteudo').style.display = 'block';
  document.getElementById('det-avatar').textContent  = nome[0].toUpperCase();
  document.getElementById('det-nome').textContent    = nome;
  document.getElementById('det-num').textContent     = `+${c.numero || ''}`;
  document.getElementById('det-form-nome').value     = c.nome || '';
  document.getElementById('det-form-email').value    = c.email || '';
  document.getElementById('det-form-tags').value     = c.tags || '';
  document.getElementById('det-form-obs').value      = c.observacoes || '';

  const btnBloquear = document.getElementById('btn-bloquear');
  btnBloquear.textContent = c.bloqueado ? '✅ Desbloquear' : '🚫 Bloquear';
  btnBloquear.title       = c.bloqueado ? 'Desbloquear contato' : 'Bloquear contato';

  // Histórico
  const hist = document.getElementById('det-historico');
  if (c.historico && c.historico.length) {
    hist.innerHTML = c.historico.map(h => `
    <div class="historico-item">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-family:monospace;font-size:12px;color:var(--accent)">#${esc(h.protocolo || '—')}</span>
        <span class="pill ${h.status === 'resolvido' ? 'pill-success' : h.status === 'em_atendimento' ? 'pill-info' : 'pill-warning'}" style="font-size:10px">${esc(h.status)}</span>
      </div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
        ${esc(h.departamento || '—')} · ${h.atendente ? esc(h.atendente) : 'Chatbot'} · ${formatarData(h.aberto_em)}
      </div>
    </div>`).join('');
  } else {
    hist.innerHTML = '<span style="color:var(--text-muted);font-size:13px">Sem histórico de atendimentos</span>';
  }
}

async function salvarContato() {
  if (!contatoSelecionado) return;
  const r = await postAjax('contatos.php', {
    action:      'editar',
    id:          contatoSelecionado.id,
    nome:        document.getElementById('det-form-nome').value.trim(),
    email:       document.getElementById('det-form-email').value.trim(),
    tags:        document.getElementById('det-form-tags').value.trim(),
    observacoes: document.getElementById('det-form-obs').value.trim(),
  });
  if (r.sucesso) { toast('Contato atualizado!', 'success'); carregarContatos(paginaAtual); }
  else toast(r.mensagem || 'Erro ao salvar.', 'error');
}

async function bloquearContato() {
  if (!contatoSelecionado) return;
  const r = await postAjax('contatos.php', { action: 'bloquear', id: contatoSelecionado.id });
  if (r.sucesso) {
    contatoSelecionado.bloqueado = r.dados?.bloqueado ?? !contatoSelecionado.bloqueado;
    const btn = document.getElementById('btn-bloquear');
    btn.textContent = contatoSelecionado.bloqueado ? '✅ Desbloquear' : '🚫 Bloquear';
    toast(contatoSelecionado.bloqueado ? 'Contato bloqueado.' : 'Contato desbloqueado.', 'success');
    carregarContatos(paginaAtual);
  }
}

function abrirInbox(contatoId) {
  window.location.href = 'inbox.php';
}

// Busca com debounce
document.getElementById('busca-contatos').addEventListener('input', () => {
  clearTimeout(debTimer);
  debTimer = setTimeout(() => carregarContatos(1), 350);
});

carregarContatos();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
