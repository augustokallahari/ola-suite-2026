<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();
exigirNivel(NIVEL_SUPERVISOR);
$usuario      = usuarioLogado();
$tituloPagina = 'Chatbot';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';

    switch ($action) {

        // ── Fluxos ─────────────────────────────────────────────
        case 'listar_fluxos':
            $r = apiGet('api/chatbot/fluxos');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'criar_fluxo':
            $r = apiPost('api/chatbot/fluxos', [
                'nome'            => trim($dados['nome']            ?? ''),
                'sessao_id'       => intval($dados['sessao_id']     ?? 0) ?: null,
                'departamento_id' => intval($dados['departamento_id'] ?? 0) ?: null,
                'ordem'           => intval($dados['ordem']         ?? 0),
            ]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null, $r['mensagem'] ?? '');

        case 'editar_fluxo':
            $id = intval($dados['id'] ?? 0);
            $r  = apiPut("api/chatbot/fluxos/{$id}", [
                'nome'            => trim($dados['nome']            ?? ''),
                'sessao_id'       => intval($dados['sessao_id']     ?? 0) ?: null,
                'departamento_id' => intval($dados['departamento_id'] ?? 0) ?: null,
                'ativo'           => $dados['ativo'] ?? 1,
                'ordem'           => intval($dados['ordem']         ?? 0),
            ]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        case 'excluir_fluxo':
            $id = intval($dados['id'] ?? 0);
            $r  = apiDelete("api/chatbot/fluxos/{$id}");
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        // ── Etapas ─────────────────────────────────────────────
        case 'listar_etapas':
            $fluxoId = intval($dados['fluxo_id'] ?? 0);
            $r = apiGet("api/chatbot/fluxos/{$fluxoId}/etapas");
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'criar_etapa':
            $fluxoId = intval($dados['fluxo_id'] ?? 0);
            $r = apiPost("api/chatbot/fluxos/{$fluxoId}/etapas", [
                'etapa_pai_id'                   => intval($dados['etapa_pai_id'] ?? 0) ?: null,
                'gatilho'                        => trim($dados['gatilho'] ?? ''),
                'mensagem'                       => trim($dados['mensagem'] ?? ''),
                'tipo'                           => $dados['tipo'] ?? 'resposta',
                'transferir_para_departamento_id'=> intval($dados['transferir_para_departamento_id'] ?? 0) ?: null,
                'ordem'                          => intval($dados['ordem'] ?? 0),
            ]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null, $r['mensagem'] ?? '');

        case 'editar_etapa':
            $id = intval($dados['id'] ?? 0);
            $r  = apiPut("api/chatbot/etapas/{$id}", [
                'etapa_pai_id'                   => intval($dados['etapa_pai_id'] ?? 0) ?: null,
                'gatilho'                        => trim($dados['gatilho'] ?? ''),
                'mensagem'                       => trim($dados['mensagem'] ?? ''),
                'tipo'                           => $dados['tipo'] ?? 'resposta',
                'transferir_para_departamento_id'=> intval($dados['transferir_para_departamento_id'] ?? 0) ?: null,
                'ordem'                          => intval($dados['ordem'] ?? 0),
            ]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        case 'excluir_etapa':
            $id = intval($dados['id'] ?? 0);
            $r  = apiDelete("api/chatbot/etapas/{$id}");
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        // ── Respostas automáticas ──────────────────────────────
        case 'listar_respostas':
            $r = apiGet('api/chatbot/respostas-automaticas');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'criar_resposta':
            $r = apiPost('api/chatbot/respostas-automaticas', [
                'sessao_id' => intval($dados['sessao_id'] ?? 0) ?: null,
                'palavra'   => trim($dados['palavra']   ?? ''),
                'resposta'  => trim($dados['resposta']  ?? ''),
                'exato'     => $dados['exato'] ? 1 : 0,
            ]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null, $r['mensagem'] ?? '');

        case 'editar_resposta':
            $id = intval($dados['id'] ?? 0);
            $r  = apiPut("api/chatbot/respostas-automaticas/{$id}", [
                'palavra'  => trim($dados['palavra']  ?? ''),
                'resposta' => trim($dados['resposta'] ?? ''),
                'exato'    => $dados['exato']  ? 1 : 0,
                'ativo'    => $dados['ativo']  ? 1 : 0,
            ]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        case 'excluir_resposta':
            $id = intval($dados['id'] ?? 0);
            $r  = apiDelete("api/chatbot/respostas-automaticas/{$id}");
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        // ── Horários ───────────────────────────────────────────
        case 'listar_horarios':
            $r = apiGet('api/configuracoes/horarios');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);

        case 'salvar_horarios':
            $r = apiPost('api/configuracoes/horarios', ['horarios' => $dados['horarios'] ?? []]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        // ── Mensagem fora do horário ───────────────────────────
        case 'get_msg_fora':
            $r = apiGet('api/configuracoes/mensagem-fora-horario');
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null);

        case 'salvar_msg_fora':
            $r = apiPut('api/configuracoes/mensagem-fora-horario', [
                'mensagem' => trim($dados['mensagem'] ?? ''),
                'ativo'    => $dados['ativo'] ? 1 : 0,
            ]);
            jsonResponse($r['sucesso'] ?? false, null, $r['mensagem'] ?? '');

        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

// Pré-carrega sessões e departamentos para os selects
$sessoes      = apiGet('api/sessoes')['dados']      ?? [];
$departamentos = apiGet('api/departamentos')['dados'] ?? [];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.page-wrap  { padding: 24px; max-width: 1300px; }
.page-titulo { font-size:22px; font-weight:700; margin-bottom:20px; }

/* Tabs de navegação */
.chatbot-tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:24px; overflow-x:auto; }
.chatbot-tab {
  padding: 10px 20px;
  border: none; background: none;
  color: var(--text-muted); font-size:14px; font-weight:500;
  cursor: pointer; border-bottom: 2px solid transparent;
  white-space: nowrap; transition: color .2s, border-color .2s;
}
.chatbot-tab:hover { color: var(--text-primary); }
.chatbot-tab.ativo { color: var(--accent); border-bottom-color: var(--accent); }

.tab-panel { display:none; }
.tab-panel.ativo { display:block; }

/* ── Painel: Fluxos ────────────────────────────────────────── */
.fluxos-layout { display:grid; grid-template-columns:280px 1fr; gap:16px; align-items:start; }
@media(max-width:900px){ .fluxos-layout{ grid-template-columns:1fr; } }

/* Lista de fluxos */
.fluxos-lista {
  background: var(--bg-card); border:1px solid var(--border);
  border-radius: var(--radius-lg); overflow:hidden;
}
.fluxos-lista-header {
  padding:14px 16px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between;
  font-weight:600; font-size:14px;
}
.fluxo-item {
  padding:12px 16px; cursor:pointer; border-bottom:1px solid var(--border);
  display:flex; align-items:center; justify-content:space-between; gap:8px;
  transition: background .15s;
}
.fluxo-item:last-child  { border-bottom:none; }
.fluxo-item:hover       { background: var(--bg-hover); }
.fluxo-item.ativo       { background: var(--bg-active); }
.fluxo-nome             { font-size:13.5px; font-weight:600; }
.fluxo-sessao           { font-size:11px; color:var(--text-muted); margin-top:2px; }
.fluxo-acoes            { display:flex; gap:4px; flex-shrink:0; }

/* Construtor de fluxo */
.builder-card {
  background: var(--bg-card); border:1px solid var(--border);
  border-radius: var(--radius-lg); min-height:400px; overflow:hidden;
}
.builder-header {
  padding:16px 20px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:12px;
}
.builder-titulo { font-size:16px; font-weight:700; flex:1; }

/* Árvore de etapas */
.etapas-tree { padding:16px; }
.etapa-raiz  { margin-bottom:16px; }

.etapa-card {
  background: var(--bg-secondary); border:1px solid var(--border);
  border-radius: var(--radius); padding:14px 16px;
  transition: border-color .2s;
  position: relative;
}
.etapa-card:hover { border-color: var(--border-light); }

.etapa-header { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; }
.etapa-tipo-badge {
  padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700;
  text-transform:uppercase; letter-spacing:.05em; flex-shrink:0;
}
.tipo-menu      { background:rgba(79,110,247,.15);  color:var(--accent); }
.tipo-resposta  { background:rgba(16,185,129,.12);  color:var(--success); }
.tipo-transferir{ background:rgba(245,158,11,.12);  color:var(--warning); }
.tipo-encerrar  { background:rgba(239,68,68,.12);   color:var(--danger); }

.etapa-gatilho  { font-size:11px; color:var(--text-muted); margin-bottom:4px; }
.etapa-gatilho strong { color:var(--accent); font-family:monospace; }
.etapa-msg      { font-size:13px; color:var(--text-primary); line-height:1.5; white-space:pre-wrap; word-break:break-word; }
.etapa-acoes    { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }

/* Filhos (sub-etapas) */
.etapa-filhos {
  margin-top:10px; margin-left:20px; padding-left:16px;
  border-left:2px dashed var(--border);
  display:flex; flex-direction:column; gap:10px;
}

/* Botão adicionar etapa */
.btn-add-etapa {
  display:flex; align-items:center; gap:6px;
  padding:8px 12px; background:none;
  border:1px dashed var(--border); border-radius:var(--radius);
  color:var(--text-muted); font-size:13px; cursor:pointer;
  transition:background .15s, color .15s, border-color .15s;
  width:100%; justify-content:center;
}
.btn-add-etapa:hover { background:var(--bg-hover); color:var(--text-primary); border-color:var(--accent); }

/* ── Painel: Respostas automáticas ─────────────────────────── */
.respostas-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.respostas-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.match-badge { display:inline-flex; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:700; }
.match-exato    { background:rgba(239,68,68,.12); color:var(--danger); }
.match-contem   { background:rgba(79,110,247,.12); color:var(--accent); }

/* ── Painel: Horários ──────────────────────────────────────── */
.horarios-grid { display:grid; gap:12px; max-width:600px; }
.horario-linha {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius); padding:14px 16px;
  display:grid; grid-template-columns:120px 1fr 1fr 80px; gap:12px; align-items:center;
}
.dia-nome { font-weight:600; font-size:14px; }
.horario-ativo-toggle {
  width:42px; height:22px; border-radius:11px;
  background:var(--bg-hover); border:none; cursor:pointer;
  position:relative; transition:background .2s;
}
.horario-ativo-toggle.on { background:var(--success); }
.horario-ativo-toggle::after {
  content:''; position:absolute; top:3px; left:3px;
  width:16px; height:16px; border-radius:50%; background:#fff;
  transition:transform .2s;
}
.horario-ativo-toggle.on::after { transform:translateX(20px); }
</style>

<div class="page-wrap">
  <div class="page-titulo">🤖 Chatbot & Automação</div>

  <!-- Tabs -->
  <div class="chatbot-tabs">
    <button class="chatbot-tab ativo" onclick="trocarTab('fluxos')">📋 Fluxos de Menu</button>
    <button class="chatbot-tab" onclick="trocarTab('respostas')">⚡ Respostas Automáticas</button>
    <button class="chatbot-tab" onclick="trocarTab('horarios')">🕐 Horários de Atendimento</button>
    <button class="chatbot-tab" onclick="trocarTab('fora_horario')">🌙 Mensagem Fora do Horário</button>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 1 — Fluxos de Menu
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel ativo" id="tab-fluxos">
    <div class="fluxos-layout">

      <!-- Lista de fluxos -->
      <div class="fluxos-lista">
        <div class="fluxos-lista-header">
          <span>Fluxos</span>
          <button class="btn btn-primary btn-sm" onclick="abrirModal('modal-fluxo')">+ Novo</button>
        </div>
        <div id="fluxos-lista-corpo">
          <div style="padding:30px;text-align:center"><div class="spinner" style="margin:0 auto"></div></div>
        </div>
      </div>

      <!-- Builder de etapas -->
      <div class="builder-card" id="builder-card">
        <div class="builder-header">
          <div class="builder-titulo" id="builder-titulo">Selecione um fluxo</div>
          <button class="btn btn-primary btn-sm" id="btn-add-raiz" style="display:none"
                  onclick="abrirModalEtapa(null, null)">
            + Etapa raiz
          </button>
        </div>
        <div class="etapas-tree" id="etapas-tree">
          <div class="empty-state" style="padding:60px">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-titulo">Nenhum fluxo selecionado</div>
            <p style="font-size:13px">Selecione ou crie um fluxo ao lado</p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 2 — Respostas Automáticas
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-respostas">
    <div class="respostas-card">
      <div class="respostas-header">
        <div>
          <div style="font-size:16px;font-weight:700">Respostas Automáticas</div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:3px">
            Respondidas automaticamente quando a mensagem contém a palavra-chave
          </div>
        </div>
        <button class="btn btn-primary" onclick="abrirModal('modal-resposta')">+ Nova Resposta</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Palavra-chave</th>
              <th>Tipo de match</th>
              <th>Resposta</th>
              <th>Sessão</th>
              <th style="width:80px">Status</th>
              <th style="width:100px"></th>
            </tr>
          </thead>
          <tbody id="respostas-tbody">
            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">
              <div class="spinner" style="margin:0 auto"></div>
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 3 — Horários de atendimento
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-horarios">
    <div style="max-width:640px">
      <div style="margin-bottom:20px">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px">Horários de Atendimento</div>
        <div style="font-size:13.5px;color:var(--text-muted)">
          Fora destes horários, o sistema envia a mensagem automática configurada na próxima aba.
        </div>
      </div>

      <div class="horarios-grid" id="horarios-grid">
        <div style="padding:30px;text-align:center"><div class="spinner" style="margin:0 auto"></div></div>
      </div>

      <div style="margin-top:16px;display:flex;gap:10px">
        <button class="btn btn-primary" onclick="salvarHorarios()">Salvar Horários</button>
        <button class="btn btn-secondary" onclick="carregarHorarios()">Resetar</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════════
       TAB 4 — Mensagem fora do horário
  ═══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tab-fora_horario">
    <div style="max-width:600px">
      <div style="margin-bottom:20px">
        <div style="font-size:16px;font-weight:700;margin-bottom:6px">Mensagem Fora do Horário</div>
        <div style="font-size:13.5px;color:var(--text-muted)">
          Enviada automaticamente quando alguém escreve fora dos horários configurados.
        </div>
      </div>

      <div class="card">
        <div class="form-group">
          <label class="form-label">Mensagem automática</label>
          <textarea class="form-control" id="msg-fora-texto" rows="5"
                    placeholder="Olá! No momento estamos fora do horário de atendimento..."></textarea>
          <small style="color:var(--text-muted);font-size:12px;margin-top:6px;display:block">
            Você pode usar emojis. A mensagem é enviada uma única vez por conversa fora do horário.
          </small>
        </div>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
            <input type="checkbox" id="msg-fora-ativo" checked
                   style="width:16px;height:16px;accent-color:var(--accent)" />
            Ativar mensagem fora do horário
          </label>
        </div>

        <div style="display:flex;gap:10px">
          <button class="btn btn-primary" onclick="salvarMsgFora()">Salvar Mensagem</button>
          <button class="btn btn-secondary" onclick="previewMsgFora()">Pré-visualizar</button>
        </div>
      </div>

      <!-- Preview -->
      <div id="preview-msg-fora" style="display:none;margin-top:16px">
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Pré-visualização</div>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:16px">
          <div style="display:flex;gap:12px;align-items:flex-start">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#25D366,#128C7E);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">🤖</div>
            <div style="background:var(--bg-secondary);border:1px solid var(--border);border-radius:14px;border-bottom-left-radius:4px;padding:10px 14px;font-size:13.5px;line-height:1.55;max-width:400px;white-space:pre-wrap" id="preview-msg-fora-texto"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /.page-wrap -->

<!-- ══════════════════════════════════════════════════════════
     Modal: Criar / Editar Fluxo
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-fluxo" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo" id="modal-fluxo-titulo">Novo Fluxo</div>
      <button class="modal-close" onclick="fecharModal('modal-fluxo')">✕</button>
    </div>
    <input type="hidden" id="fluxo-id" />

    <div class="form-group">
      <label class="form-label">Nome do fluxo *</label>
      <input type="text" class="form-control" id="fluxo-nome"
             placeholder="Ex: Atendimento Principal, Suporte Técnico..." />
    </div>
    <div class="form-group">
      <label class="form-label">Aplicar a sessão (opcional)</label>
      <select class="form-control" id="fluxo-sessao">
        <option value="">— Todas as sessões —</option>
        <?php foreach ($sessoes as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?>
            <?= $s['numero'] ? ' (+' . $s['numero'] . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Encaminhar para departamento ao concluir (opcional)</label>
      <select class="form-control" id="fluxo-departamento">
        <option value="">— Nenhum —</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Ordem de prioridade</label>
      <input type="number" class="form-control" id="fluxo-ordem" value="0" min="0" />
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-fluxo')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarFluxo()">Salvar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: Criar / Editar Etapa
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-etapa" style="display:none">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <div class="modal-titulo" id="modal-etapa-titulo">Nova Etapa</div>
      <button class="modal-close" onclick="fecharModal('modal-etapa')">✕</button>
    </div>
    <input type="hidden" id="etapa-id" />
    <input type="hidden" id="etapa-pai-id" />
    <input type="hidden" id="etapa-fluxo-id" />

    <div class="form-group">
      <label class="form-label">Tipo da etapa *</label>
      <select class="form-control" id="etapa-tipo" onchange="toggleCamposEtapa()">
        <option value="menu">📋 Menu — exibe opções para o usuário escolher</option>
        <option value="resposta">💬 Resposta — envia mensagem e volta ao menu pai</option>
        <option value="transferir">🔀 Transferir — encaminha para um departamento</option>
        <option value="encerrar">✅ Encerrar — finaliza o atendimento</option>
      </select>
    </div>

    <div class="form-group" id="campo-gatilho">
      <label class="form-label">Gatilho (o que o usuário digita) *</label>
      <input type="text" class="form-control" id="etapa-gatilho"
             placeholder="Ex: 1, 2, suporte, sim..." maxlength="50" />
      <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">
        Texto que o usuário digita para ativar esta etapa (comparação sem diferença de maiúsculas)
      </small>
    </div>

    <div class="form-group">
      <label class="form-label">Mensagem *</label>
      <textarea class="form-control" id="etapa-mensagem" rows="5"
                placeholder="Digite a mensagem que será enviada..."></textarea>
      <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">
        Para menus, liste as opções aqui. Ex: "Digite 1 para Suporte\nDigite 2 para Vendas"
      </small>
    </div>

    <div class="form-group" id="campo-departamento-etapa" style="display:none">
      <label class="form-label">Transferir para departamento *</label>
      <select class="form-control" id="etapa-departamento">
        <option value="">— Selecione o departamento —</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Ordem</label>
      <input type="number" class="form-control" id="etapa-ordem" value="0" min="0" style="width:100px" />
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-etapa')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarEtapa()">Salvar Etapa</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: Criar / Editar Resposta Automática
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-resposta" style="display:none">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-titulo" id="modal-resposta-titulo">Nova Resposta Automática</div>
      <button class="modal-close" onclick="fecharModal('modal-resposta')">✕</button>
    </div>
    <input type="hidden" id="resposta-id" />

    <div class="form-group">
      <label class="form-label">Palavra-chave *</label>
      <input type="text" class="form-control" id="resposta-palavra"
             placeholder="Ex: preço, orçamento, cancelar..." />
    </div>

    <div class="form-group">
      <label class="form-label">Tipo de correspondência</label>
      <div style="display:flex;gap:12px">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
          <input type="radio" name="resposta-exato" id="resp-contem" value="0" checked
                 style="accent-color:var(--accent)" />
          <span>Contém a palavra</span>
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
          <input type="radio" name="resposta-exato" id="resp-exato" value="1"
                 style="accent-color:var(--accent)" />
          <span>Mensagem exata</span>
        </label>
      </div>
      <small style="color:var(--text-muted);font-size:12px;margin-top:4px;display:block">
        "Contém" detecta a palavra em qualquer parte da mensagem. "Exata" requer correspondência completa.
      </small>
    </div>

    <div class="form-group">
      <label class="form-label">Resposta automática *</label>
      <textarea class="form-control" id="resposta-texto" rows="4"
                placeholder="Mensagem que será enviada automaticamente..."></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Aplicar apenas na sessão (opcional)</label>
      <select class="form-control" id="resposta-sessao">
        <option value="">— Todas as sessões —</option>
        <?php foreach ($sessoes as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="fecharModal('modal-resposta')">Cancelar</button>
      <button class="btn btn-primary" onclick="salvarResposta()">Salvar</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     JavaScript
═══════════════════════════════════════════════════════════ -->
<script>
// ── Estado ────────────────────────────────────────────────────
let fluxoAtivo  = null;
let etapasCache = [];

// ── Tabs ──────────────────────────────────────────────────────
function trocarTab(tab) {
  document.querySelectorAll('.chatbot-tab').forEach((b, i) => {
    const tabs = ['fluxos','respostas','horarios','fora_horario'];
    b.classList.toggle('ativo', tabs[i] === tab);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('ativo'));
  document.getElementById(`tab-${tab}`).classList.add('ativo');

  if (tab === 'respostas'    && !document.getElementById('respostas-tbody').dataset.loaded) carregarRespostas();
  if (tab === 'horarios'     && !document.getElementById('horarios-grid').dataset.loaded)   carregarHorarios();
  if (tab === 'fora_horario' && !document.getElementById('msg-fora-texto').dataset.loaded)  carregarMsgFora();
}

// ══════════════════════════════════════════════════════════════
// FLUXOS
// ══════════════════════════════════════════════════════════════
async function carregarFluxos() {
  const corpo = document.getElementById('fluxos-lista-corpo');
  const r = await postAjax('chatbot.php', { action: 'listar_fluxos' });
  const fluxos = r.dados || [];

  if (!fluxos.length) {
    corpo.innerHTML = `<div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px">
      Nenhum fluxo criado.<br/>Crie o primeiro fluxo clicando em "+ Novo".
    </div>`;
    return;
  }

  corpo.innerHTML = fluxos.map(f => `
  <div class="fluxo-item ${fluxoAtivo?.id === f.id ? 'ativo' : ''}"
       onclick="selecionarFluxo(${f.id}, ${JSON.stringify(f.nome).replace(/"/g,"'")})"
       data-fluxo-id="${f.id}">
    <div style="flex:1;min-width:0">
      <div class="fluxo-nome">${esc(f.nome)}</div>
      <div class="fluxo-sessao">${f.sessao_nome ? esc(f.sessao_nome) : 'Todas as sessões'}</div>
    </div>
    <div class="fluxo-acoes">
      <span class="pill ${f.ativo ? 'pill-success' : 'pill-muted'}" style="font-size:10px">
        ${f.ativo ? 'Ativo' : 'Inativo'}
      </span>
      <button class="btn btn-secondary btn-sm btn-icon" onclick="event.stopPropagation();editarFluxo(${f.id})" title="Editar">
        ✏️
      </button>
      <button class="btn btn-danger btn-sm btn-icon" onclick="event.stopPropagation();confirmarExcluirFluxo(${f.id},'${esc(f.nome)}')" title="Excluir">
        🗑️
      </button>
    </div>
  </div>`).join('');
}

async function selecionarFluxo(id, nome) {
  fluxoAtivo = { id, nome };
  document.getElementById('builder-titulo').textContent = nome;
  document.getElementById('btn-add-raiz').style.display = 'inline-flex';

  // Marca ativo na lista
  document.querySelectorAll('.fluxo-item').forEach(el => {
    el.classList.toggle('ativo', parseInt(el.dataset.fluxoId) === id);
  });

  await carregarEtapas(id);
}

// ── Modal Fluxo ────────────────────────────────────────────────
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }

function abrirModalFluxo() {
  document.getElementById('modal-fluxo-titulo').textContent = 'Novo Fluxo';
  document.getElementById('fluxo-id').value      = '';
  document.getElementById('fluxo-nome').value    = '';
  document.getElementById('fluxo-sessao').value  = '';
  document.getElementById('fluxo-departamento').value = '';
  document.getElementById('fluxo-ordem').value   = '0';
  abrirModal('modal-fluxo');
}

async function editarFluxo(id) {
  const r = await postAjax('chatbot.php', { action: 'listar_fluxos' });
  const f = (r.dados || []).find(x => x.id === id);
  if (!f) return;
  document.getElementById('modal-fluxo-titulo').textContent = 'Editar Fluxo';
  document.getElementById('fluxo-id').value           = f.id;
  document.getElementById('fluxo-nome').value         = f.nome;
  document.getElementById('fluxo-sessao').value       = f.sessao_id || '';
  document.getElementById('fluxo-departamento').value = f.departamento_id || '';
  document.getElementById('fluxo-ordem').value        = f.ordem || 0;
  abrirModal('modal-fluxo');
}

async function salvarFluxo() {
  const id    = document.getElementById('fluxo-id').value;
  const nome  = document.getElementById('fluxo-nome').value.trim();
  if (!nome) { toast('Nome obrigatório.', 'error'); return; }

  const payload = {
    action:          id ? 'editar_fluxo' : 'criar_fluxo',
    id:              id ? parseInt(id) : undefined,
    nome,
    sessao_id:       parseInt(document.getElementById('fluxo-sessao').value)       || null,
    departamento_id: parseInt(document.getElementById('fluxo-departamento').value) || null,
    ordem:           parseInt(document.getElementById('fluxo-ordem').value)        || 0,
    ativo:           1,
  };

  const r = await postAjax('chatbot.php', payload);
  if (r.sucesso) {
    toast(id ? 'Fluxo atualizado!' : 'Fluxo criado!', 'success');
    fecharModal('modal-fluxo');
    await carregarFluxos();
    if (!id && r.dados?.id) selecionarFluxo(r.dados.id, nome);
  } else {
    toast(r.mensagem || 'Erro ao salvar fluxo.', 'error');
  }
}

async function confirmarExcluirFluxo(id, nome) {
  if (!confirm(`Excluir o fluxo "${nome}"?\nTodas as etapas serão removidas.`)) return;
  const r = await postAjax('chatbot.php', { action: 'excluir_fluxo', id });
  if (r.sucesso) {
    toast('Fluxo excluído.', 'success');
    if (fluxoAtivo?.id === id) {
      fluxoAtivo = null;
      document.getElementById('builder-titulo').textContent   = 'Selecione um fluxo';
      document.getElementById('btn-add-raiz').style.display   = 'none';
      document.getElementById('etapas-tree').innerHTML = `
        <div class="empty-state" style="padding:60px">
          <div class="empty-state-icon">📋</div>
          <div class="empty-state-titulo">Nenhum fluxo selecionado</div>
        </div>`;
    }
    carregarFluxos();
  } else {
    toast(r.mensagem || 'Erro ao excluir.', 'error');
  }
}

// ══════════════════════════════════════════════════════════════
// ETAPAS
// ══════════════════════════════════════════════════════════════
async function carregarEtapas(fluxoId) {
  const tree = document.getElementById('etapas-tree');
  tree.innerHTML = '<div style="padding:30px;text-align:center"><div class="spinner" style="margin:0 auto"></div></div>';

  const r = await postAjax('chatbot.php', { action: 'listar_etapas', fluxo_id: fluxoId });
  etapasCache = r.dados || [];

  if (!etapasCache.length) {
    tree.innerHTML = `
    <div class="empty-state" style="padding:40px">
      <div class="empty-state-icon">🌱</div>
      <div class="empty-state-titulo">Fluxo vazio</div>
      <p style="font-size:13px">Adicione a primeira etapa usando o botão "+ Etapa raiz"</p>
    </div>`;
    return;
  }

  // Monta árvore: etapas raiz (pai = null)
  const raizes = etapasCache.filter(e => !e.etapa_pai_id);
  tree.innerHTML = raizes.map(e => renderEtapa(e, 0)).join('');
}

function renderEtapa(etapa, nivel) {
  const filhos = etapasCache.filter(e => e.etapa_pai_id === etapa.id);
  const tipoLabel = { menu:'Menu', resposta:'Resposta', transferir:'Transferir', encerrar:'Encerrar' };

  const podeTerFilhos = etapa.tipo === 'menu';
  const msgPreview    = etapa.mensagem.length > 120
    ? etapa.mensagem.substring(0, 120) + '...'
    : etapa.mensagem;

  let html = `
  <div class="etapa-card" data-etapa-id="${etapa.id}">
    <div class="etapa-header">
      <span class="etapa-tipo-badge tipo-${etapa.tipo}">${tipoLabel[etapa.tipo] || etapa.tipo}</span>
      ${etapa.gatilho ? `<span style="background:var(--bg-active);border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-family:monospace;font-size:12px;color:var(--accent)">
        Digite: <strong>${esc(etapa.gatilho)}</strong>
      </span>` : '<span style="font-size:12px;color:var(--text-muted)">Etapa raiz (menu principal)</span>'}
    </div>
    <div class="etapa-msg">${esc(msgPreview)}</div>
    ${etapa.tipo === 'transferir' && etapa.departamento_nome ? `
      <div style="margin-top:6px;font-size:12px;color:var(--warning)">
        → Transfere para: <strong>${esc(etapa.departamento_nome)}</strong>
      </div>` : ''}
    <div class="etapa-acoes">
      ${podeTerFilhos ? `
      <button class="btn btn-secondary btn-sm" onclick="abrirModalEtapa(${etapa.fluxo_id}, ${etapa.id})">
        + Sub-opção
      </button>` : ''}
      <button class="btn btn-secondary btn-sm" onclick="abrirModalEditarEtapa(${etapa.id})">✏️ Editar</button>
      <button class="btn btn-danger btn-sm" onclick="confirmarExcluirEtapa(${etapa.id})">🗑️</button>
    </div>
  </div>`;

  if (filhos.length) {
    html += `<div class="etapa-filhos">` + filhos.map(f => renderEtapa(f, nivel + 1)).join('') + `</div>`;
  }

  if (nivel === 0) {
    return `<div class="etapa-raiz">${html}</div>`;
  }
  return html;
}

// Modal Etapa
function abrirModalEtapa(fluxoId, paiId) {
  document.getElementById('modal-etapa-titulo').textContent = 'Nova Etapa';
  document.getElementById('etapa-id').value          = '';
  document.getElementById('etapa-fluxo-id').value    = fluxoId ?? fluxoAtivo?.id ?? '';
  document.getElementById('etapa-pai-id').value      = paiId ?? '';
  document.getElementById('etapa-tipo').value        = paiId ? 'resposta' : 'menu';
  document.getElementById('etapa-gatilho').value     = '';
  document.getElementById('etapa-mensagem').value    = '';
  document.getElementById('etapa-departamento').value = '';
  document.getElementById('etapa-ordem').value       = '0';
  document.getElementById('campo-gatilho').style.display = paiId ? 'block' : 'none';
  toggleCamposEtapa();
  abrirModal('modal-etapa');
}

function abrirModalEditarEtapa(id) {
  const etapa = etapasCache.find(e => e.id === id);
  if (!etapa) return;
  document.getElementById('modal-etapa-titulo').textContent = 'Editar Etapa';
  document.getElementById('etapa-id').value           = etapa.id;
  document.getElementById('etapa-fluxo-id').value     = etapa.fluxo_id;
  document.getElementById('etapa-pai-id').value       = etapa.etapa_pai_id || '';
  document.getElementById('etapa-tipo').value         = etapa.tipo;
  document.getElementById('etapa-gatilho').value      = etapa.gatilho || '';
  document.getElementById('etapa-mensagem').value     = etapa.mensagem;
  document.getElementById('etapa-departamento').value = etapa.transferir_para_departamento_id || '';
  document.getElementById('etapa-ordem').value        = etapa.ordem || 0;
  document.getElementById('campo-gatilho').style.display = etapa.etapa_pai_id ? 'block' : 'none';
  toggleCamposEtapa();
  abrirModal('modal-etapa');
}

function toggleCamposEtapa() {
  const tipo = document.getElementById('etapa-tipo').value;
  document.getElementById('campo-departamento-etapa').style.display = tipo === 'transferir' ? 'block' : 'none';
}

async function salvarEtapa() {
  const id      = document.getElementById('etapa-id').value;
  const fluxoId = parseInt(document.getElementById('etapa-fluxo-id').value);
  const paiId   = parseInt(document.getElementById('etapa-pai-id').value) || null;
  const tipo    = document.getElementById('etapa-tipo').value;
  const gatilho = document.getElementById('etapa-gatilho').value.trim();
  const mensagem = document.getElementById('etapa-mensagem').value.trim();
  const deptId  = parseInt(document.getElementById('etapa-departamento').value) || null;
  const ordem   = parseInt(document.getElementById('etapa-ordem').value) || 0;

  if (!mensagem) { toast('Mensagem é obrigatória.', 'error'); return; }
  if (paiId && !gatilho) { toast('Gatilho é obrigatório para sub-etapas.', 'error'); return; }
  if (tipo === 'transferir' && !deptId) { toast('Selecione o departamento de destino.', 'error'); return; }

  const action  = id ? 'editar_etapa' : 'criar_etapa';
  const payload = { action, fluxo_id: fluxoId, etapa_pai_id: paiId, gatilho, mensagem, tipo,
                    transferir_para_departamento_id: deptId, ordem };
  if (id) payload.id = parseInt(id);

  const r = await postAjax('chatbot.php', payload);
  if (r.sucesso) {
    toast(id ? 'Etapa atualizada!' : 'Etapa criada!', 'success');
    fecharModal('modal-etapa');
    carregarEtapas(fluxoId);
  } else {
    toast(r.mensagem || 'Erro ao salvar etapa.', 'error');
  }
}

async function confirmarExcluirEtapa(id) {
  if (!confirm('Excluir esta etapa? As sub-etapas filhas também serão removidas.')) return;
  const r = await postAjax('chatbot.php', { action: 'excluir_etapa', id });
  if (r.sucesso) {
    toast('Etapa removida.', 'success');
    carregarEtapas(fluxoAtivo.id);
  } else {
    toast(r.mensagem || 'Erro ao excluir.', 'error');
  }
}

// ══════════════════════════════════════════════════════════════
// RESPOSTAS AUTOMÁTICAS
// ══════════════════════════════════════════════════════════════
async function carregarRespostas() {
  const tbody = document.getElementById('respostas-tbody');
  tbody.dataset.loaded = '1';
  const r = await postAjax('chatbot.php', { action: 'listar_respostas' });
  const respostas = r.dados || [];

  if (!respostas.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">Nenhuma resposta automática cadastrada</td></tr>';
    return;
  }

  tbody.innerHTML = respostas.map(r => `
  <tr>
    <td>
      <code style="background:var(--bg-hover);padding:3px 8px;border-radius:4px;font-size:13px">
        ${esc(r.palavra)}
      </code>
    </td>
    <td>
      <span class="match-badge ${r.exato ? 'match-exato' : 'match-contem'}">
        ${r.exato ? 'Exato' : 'Contém'}
      </span>
    </td>
    <td style="max-width:280px">
      <div style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px" title="${esc(r.resposta)}">
        ${esc(r.resposta)}
      </div>
    </td>
    <td style="font-size:12.5px;color:var(--text-muted)">${r.sessao_id ? esc(r.sessao_nome || 'Sessão #' + r.sessao_id) : 'Todas'}</td>
    <td>
      <button class="btn btn-sm ${r.ativo ? 'btn-success' : 'btn-secondary'}"
              onclick="toggleResposta(${r.id}, ${r.ativo ? 0 : 1}, event)"
              title="${r.ativo ? 'Desativar' : 'Ativar'}">
        ${r.ativo ? 'Ativo' : 'Inativo'}
      </button>
    </td>
    <td>
      <div style="display:flex;gap:4px">
        <button class="btn btn-secondary btn-sm btn-icon" onclick="editarResposta(${r.id})" title="Editar">✏️</button>
        <button class="btn btn-danger btn-sm btn-icon" onclick="excluirResposta(${r.id})" title="Excluir">🗑️</button>
      </div>
    </td>
  </tr>`).join('');
}

// Guarda dados para edição
let _respostasCache = [];
async function _cacheRespostas() {
  const r = await postAjax('chatbot.php', { action: 'listar_respostas' });
  _respostasCache = r.dados || [];
}

function abrirNovaResposta() {
  document.getElementById('modal-resposta-titulo').textContent = 'Nova Resposta Automática';
  document.getElementById('resposta-id').value       = '';
  document.getElementById('resposta-palavra').value  = '';
  document.getElementById('resposta-texto').value    = '';
  document.getElementById('resposta-sessao').value   = '';
  document.getElementById('resp-contem').checked     = true;
  abrirModal('modal-resposta');
}

async function editarResposta(id) {
  if (!_respostasCache.length) await _cacheRespostas();
  const resp = _respostasCache.find(r => r.id === id);
  if (!resp) { await _cacheRespostas(); return editarResposta(id); }

  document.getElementById('modal-resposta-titulo').textContent = 'Editar Resposta';
  document.getElementById('resposta-id').value      = resp.id;
  document.getElementById('resposta-palavra').value = resp.palavra;
  document.getElementById('resposta-texto').value   = resp.resposta;
  document.getElementById('resposta-sessao').value  = resp.sessao_id || '';
  document.getElementById(resp.exato ? 'resp-exato' : 'resp-contem').checked = true;
  abrirModal('modal-resposta');
}

async function salvarResposta() {
  const id      = document.getElementById('resposta-id').value;
  const palavra = document.getElementById('resposta-palavra').value.trim();
  const resposta = document.getElementById('resposta-texto').value.trim();
  const exato   = document.querySelector('input[name="resposta-exato"]:checked')?.value === '1';
  const sessaoId = parseInt(document.getElementById('resposta-sessao').value) || null;

  if (!palavra || !resposta) { toast('Palavra-chave e resposta são obrigatórios.', 'error'); return; }

  const action  = id ? 'editar_resposta' : 'criar_resposta';
  const payload = { action, palavra, resposta, exato: exato ? 1 : 0, sessao_id: sessaoId, ativo: 1 };
  if (id) payload.id = parseInt(id);

  const r = await postAjax('chatbot.php', payload);
  if (r.sucesso) {
    toast(id ? 'Resposta atualizada!' : 'Resposta criada!', 'success');
    fecharModal('modal-resposta');
    _respostasCache = [];
    carregarRespostas();
  } else {
    toast(r.mensagem || 'Erro ao salvar.', 'error');
  }
}

async function toggleResposta(id, novoAtivo, e) {
  const btn = e.target;
  const r = await postAjax('chatbot.php', {
    action: 'editar_resposta', id,
    palavra:  btn.closest('tr').querySelector('code').textContent.trim(),
    resposta: btn.closest('tr').querySelector('div[title]').title,
    ativo:    novoAtivo, exato: 0,
  });
  if (r.sucesso) carregarRespostas();
}

async function excluirResposta(id) {
  if (!confirm('Excluir esta resposta automática?')) return;
  const r = await postAjax('chatbot.php', { action: 'excluir_resposta', id });
  if (r.sucesso) { toast('Resposta excluída.', 'success'); _respostasCache = []; carregarRespostas(); }
  else toast(r.mensagem || 'Erro ao excluir.', 'error');
}

// ══════════════════════════════════════════════════════════════
// HORÁRIOS
// ══════════════════════════════════════════════════════════════
const DIAS = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
// Estado local dos horários (0=Dom … 6=Sab)
let horariosLocal = Array.from({length:7}, (_, i) => ({
  dia_semana: i, ativo: i >= 1 && i <= 5, hora_inicio: '08:00', hora_fim: '18:00',
}));

async function carregarHorarios() {
  const grid = document.getElementById('horarios-grid');
  grid.dataset.loaded = '1';
  const r = await postAjax('chatbot.php', { action: 'listar_horarios' });
  const horarios = r.dados || [];

  // Mescla com padrão
  horariosLocal = Array.from({length:7}, (_, i) => {
    const h = horarios.find(x => x.dia_semana === i);
    return h
      ? { dia_semana: i, ativo: !!h.ativo, hora_inicio: h.hora_inicio.substring(0,5), hora_fim: h.hora_fim.substring(0,5) }
      : { dia_semana: i, ativo: i >= 1 && i <= 5, hora_inicio: '08:00', hora_fim: '18:00' };
  });

  renderHorarios();
}

function renderHorarios() {
  const grid = document.getElementById('horarios-grid');
  grid.innerHTML = horariosLocal.map((h, i) => `
  <div class="horario-linha">
    <div class="dia-nome">${DIAS[i]}</div>
    <input type="time" class="form-control" value="${h.hora_inicio}"
           onchange="horariosLocal[${i}].hora_inicio=this.value" style="width:110px" ${h.ativo ? '' : 'disabled'} />
    <input type="time" class="form-control" value="${h.hora_fim}"
           onchange="horariosLocal[${i}].hora_fim=this.value" style="width:110px" ${h.ativo ? '' : 'disabled'} />
    <button class="horario-ativo-toggle ${h.ativo ? 'on' : ''}"
            onclick="toggleDia(${i})" title="${h.ativo ? 'Desativar dia' : 'Ativar dia'}">
    </button>
  </div>`).join('');
}

function toggleDia(i) {
  horariosLocal[i].ativo = !horariosLocal[i].ativo;
  renderHorarios();
}

async function salvarHorarios() {
  const r = await postAjax('chatbot.php', {
    action:   'salvar_horarios',
    horarios: horariosLocal,
  });
  if (r.sucesso) toast('Horários salvos!', 'success');
  else toast(r.mensagem || 'Erro ao salvar horários.', 'error');
}

// ══════════════════════════════════════════════════════════════
// MENSAGEM FORA DO HORÁRIO
// ══════════════════════════════════════════════════════════════
async function carregarMsgFora() {
  document.getElementById('msg-fora-texto').dataset.loaded = '1';
  const r = await postAjax('chatbot.php', { action: 'get_msg_fora' });
  const dados = r.dados;
  if (dados) {
    document.getElementById('msg-fora-texto').value = dados.mensagem || '';
    document.getElementById('msg-fora-ativo').checked = !!dados.ativo;
  }
}

async function salvarMsgFora() {
  const mensagem = document.getElementById('msg-fora-texto').value.trim();
  const ativo    = document.getElementById('msg-fora-ativo').checked;
  if (!mensagem) { toast('Digite a mensagem.', 'error'); return; }
  const r = await postAjax('chatbot.php', { action: 'salvar_msg_fora', mensagem, ativo });
  if (r.sucesso) toast('Mensagem salva!', 'success');
  else toast(r.mensagem || 'Erro ao salvar.', 'error');
}

function previewMsgFora() {
  const texto = document.getElementById('msg-fora-texto').value.trim();
  if (!texto) { toast('Digite a mensagem antes de pré-visualizar.', 'error'); return; }
  const preview = document.getElementById('preview-msg-fora');
  document.getElementById('preview-msg-fora-texto').textContent = texto;
  preview.style.display = preview.style.display === 'none' ? 'block' : 'none';
}

// ── Inicialização ──────────────────────────────────────────────
carregarFluxos();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
