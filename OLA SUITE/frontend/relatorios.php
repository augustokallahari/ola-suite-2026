<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

exigirLogin();
exigirNivel(NIVEL_SUPERVISOR);
$usuario      = usuarioLogado();
$tituloPagina = 'Relatórios';

// ── Handlers AJAX ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $dados  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $dados['action'] ?? '';
    $di     = $dados['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $df     = $dados['data_fim']    ?? date('Y-m-d');

    switch ($action) {
        case 'resumo':
            $r = apiGet('api/relatorios/resumo', ['data_inicio' => $di, 'data_fim' => $df]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? null);
        case 'por_dia':
            $r = apiGet('api/relatorios/por-dia', ['data_inicio' => $di, 'data_fim' => $df]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);
        case 'por_departamento':
            $r = apiGet('api/relatorios/por-departamento', ['data_inicio' => $di, 'data_fim' => $df]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);
        case 'por_agente':
            $r = apiGet('api/relatorios/por-agente', ['data_inicio' => $di, 'data_fim' => $df]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);
        case 'tempo_resposta':
            $r = apiGet('api/relatorios/tempo-resposta', ['data_inicio' => $di, 'data_fim' => $df]);
            jsonResponse($r['sucesso'] ?? false, $r['dados'] ?? []);
        default:
            jsonResponse(false, null, 'Ação desconhecida', 400);
    }
}

$dataInicioDefault = date('Y-m-d', strtotime('-30 days'));
$dataFimDefault    = date('Y-m-d');
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
.page-wrap  { padding:24px; max-width:1300px; }
.page-titulo { font-size:22px; font-weight:700; margin-bottom:6px; }

/* Filtros de data */
.filtro-bar {
  display:flex; align-items:center; gap:12px; flex-wrap:wrap;
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:14px 18px; margin-bottom:24px;
}
.filtro-bar label { font-size:13px; font-weight:500; color:var(--text-muted); }
.filtro-bar input[type=date] {
  background:var(--bg-tertiary); border:1px solid var(--border);
  border-radius:var(--radius); padding:7px 12px; color:var(--text-primary);
  font-size:13px; outline:none; transition:border-color .2s;
  color-scheme: dark;
}
.filtro-bar input[type=date]:focus { border-color:var(--accent); }
.filtro-atalhos { display:flex; gap:6px; margin-left:auto; flex-wrap:wrap; }
.btn-atalho {
  padding:5px 12px; border-radius:20px; border:1px solid var(--border);
  background:none; color:var(--text-muted); font-size:12px; cursor:pointer; transition:.15s;
}
.btn-atalho:hover,
.btn-atalho.ativo { background:var(--accent-light); color:var(--accent); border-color:var(--accent); }

/* Cards de resumo */
.resumo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:24px; }
.resumo-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:18px;
  display:flex; flex-direction:column; gap:6px;
  position:relative; overflow:hidden;
}
.resumo-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.resumo-card.total::before   { background:var(--accent); }
.resumo-card.resolvido::before { background:var(--success); }
.resumo-card.andamento::before { background:var(--warning); }
.resumo-card.abandonado::before { background:var(--danger); }
.resumo-card.tempo::before   { background:#8b5cf6; }
.resumo-card.msgs::before    { background:#06b6d4; }

.resumo-icone   { font-size:26px; }
.resumo-valor   { font-size:32px; font-weight:800; line-height:1; }
.resumo-label   { font-size:12px; color:var(--text-muted); font-weight:500; }
.resumo-loading { font-size:24px; font-weight:800; color:var(--text-muted); }

/* Gráficos */
.graficos-grid { display:grid; grid-template-columns:2fr 1fr; gap:16px; margin-bottom:24px; }
@media(max-width:900px){ .graficos-grid{ grid-template-columns:1fr; } }

.grafico-card {
  background:var(--bg-card); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:20px;
}
.grafico-titulo { font-size:15px; font-weight:700; margin-bottom:16px; }

/* Tabelas de agentes / departamentos */
.tabelas-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:900px){ .tabelas-grid{ grid-template-columns:1fr; } }

.ranking-item {
  display:flex; align-items:center; gap:10px;
  padding:10px 0; border-bottom:1px solid var(--border);
}
.ranking-item:last-child { border-bottom:none; }
.ranking-pos  { font-size:16px; font-weight:800; color:var(--text-muted); width:24px; flex-shrink:0; }
.ranking-info { flex:1; min-width:0; }
.ranking-nome { font-size:13.5px; font-weight:600; }
.ranking-sub  { font-size:12px; color:var(--text-muted); }
.ranking-val  { font-size:18px; font-weight:800; color:var(--accent); }

/* Barra de progresso */
.barra-wrap { height:6px; background:var(--bg-hover); border-radius:3px; margin-top:5px; }
.barra-fill { height:6px; border-radius:3px; background:var(--accent); transition:width .5s ease; }
</style>

<div class="page-wrap">
  <div class="page-titulo">📊 Relatórios</div>
  <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:20px">
    Análise de desempenho do atendimento
  </p>

  <!-- Filtros -->
  <div class="filtro-bar">
    <label>De</label>
    <input type="date" id="data-inicio" value="<?= $dataInicioDefault ?>" />
    <label>até</label>
    <input type="date" id="data-fim" value="<?= $dataFimDefault ?>" />
    <button class="btn btn-primary btn-sm" onclick="carregarTodos()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      Filtrar
    </button>
    <div class="filtro-atalhos">
      <button class="btn-atalho" onclick="atalho(7)">7 dias</button>
      <button class="btn-atalho ativo" onclick="atalho(30)">30 dias</button>
      <button class="btn-atalho" onclick="atalho(90)">90 dias</button>
      <button class="btn-atalho" onclick="atalhoMesAtual()">Este mês</button>
    </div>
  </div>

  <!-- Cards de resumo -->
  <div class="resumo-grid" id="resumo-grid">
    <?php foreach ([
      ['total','💬','—','Total de Atendimentos'],
      ['resolvido','✅','—','Resolvidos'],
      ['andamento','🔄','—','Em Andamento'],
      ['abandonado','❌','—','Abandonados'],
      ['tempo','⏱️','—','Tempo Médio (min)'],
      ['msgs','📨','—','Mensagens Enviadas'],
    ] as [$cls, $ico, $val, $label]): ?>
    <div class="resumo-card <?= $cls ?>">
      <div class="resumo-icone"><?= $ico ?></div>
      <div class="resumo-valor resumo-loading" id="res-<?= $cls ?>">—</div>
      <div class="resumo-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Gráficos -->
  <div class="graficos-grid">
    <div class="grafico-card">
      <div class="grafico-titulo">Atendimentos por dia</div>
      <canvas id="chart-por-dia" height="220"></canvas>
    </div>
    <div class="grafico-card">
      <div class="grafico-titulo">Por departamento</div>
      <canvas id="chart-por-dept" height="220"></canvas>
    </div>
  </div>

  <!-- Tabelas -->
  <div class="tabelas-grid">

    <!-- Ranking de agentes -->
    <div class="grafico-card">
      <div class="grafico-titulo">Desempenho por agente</div>
      <div id="ranking-agentes">
        <div style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></div>
      </div>
    </div>

    <!-- Tempo de resposta -->
    <div class="grafico-card">
      <div class="grafico-titulo">Tempo de 1ª resposta (min)</div>
      <div id="ranking-tempo">
        <div style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></div>
      </div>
    </div>

  </div>
</div>

<script>
let chartDia  = null;
let chartDept = null;

// ── Helpers de data ────────────────────────────────────────────
function atalho(dias) {
  document.querySelectorAll('.btn-atalho').forEach(b => b.classList.remove('ativo'));
  event.target.classList.add('ativo');
  const fim = new Date();
  const ini = new Date();
  ini.setDate(fim.getDate() - dias);
  document.getElementById('data-inicio').value = ini.toISOString().split('T')[0];
  document.getElementById('data-fim').value    = fim.toISOString().split('T')[0];
  carregarTodos();
}

function atalhoMesAtual() {
  document.querySelectorAll('.btn-atalho').forEach(b => b.classList.remove('ativo'));
  event.target.classList.add('ativo');
  const now = new Date();
  document.getElementById('data-inicio').value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-01`;
  document.getElementById('data-fim').value    = now.toISOString().split('T')[0];
  carregarTodos();
}

function getFiltros() {
  return {
    data_inicio: document.getElementById('data-inicio').value,
    data_fim:    document.getElementById('data-fim').value,
  };
}

// ── Carrega tudo ───────────────────────────────────────────────
async function carregarTodos() {
  await Promise.all([
    carregarResumo(),
    carregarGraficoDia(),
    carregarGraficoDept(),
    carregarAgentes(),
    carregarTempoResposta(),
  ]);
}

// ── Resumo ─────────────────────────────────────────────────────
async function carregarResumo() {
  ['total','resolvido','andamento','abandonado','tempo','msgs'].forEach(id => {
    document.getElementById(`res-${id}`).textContent = '…';
  });

  const r = await postAjax('relatorios.php', { action: 'resumo', ...getFiltros() });
  const d = r.dados || {};

  document.getElementById('res-total').textContent     = d.total_atendimentos       ?? 0;
  document.getElementById('res-resolvido').textContent = d.resolvidos               ?? 0;
  document.getElementById('res-andamento').textContent = d.em_andamento             ?? 0;
  document.getElementById('res-abandonado').textContent = d.abandonados             ?? 0;
  document.getElementById('res-tempo').textContent     = d.tempo_medio_minutos
    ? Math.round(d.tempo_medio_minutos) : '—';
  document.getElementById('res-msgs').textContent      = d.total_mensagens          ?? 0;
}

// ── Gráfico por dia ────────────────────────────────────────────
async function carregarGraficoDia() {
  const r    = await postAjax('relatorios.php', { action: 'por_dia', ...getFiltros() });
  const dias = r.dados || [];

  const labels = dias.map(d => {
    const dt = new Date(d.dia + 'T12:00:00');
    return dt.toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit' });
  });
  const valores = dias.map(d => d.total);

  const ctx = document.getElementById('chart-por-dia').getContext('2d');
  if (chartDia) chartDia.destroy();

  chartDia = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label:           'Atendimentos',
        data:            valores,
        borderColor:     '#4f6ef7',
        backgroundColor: 'rgba(79,110,247,.12)',
        borderWidth:     2,
        pointRadius:     3,
        pointHoverRadius: 5,
        fill:            true,
        tension:         0.35,
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1e2235',
          borderColor:     '#2a2f45',
          borderWidth:     1,
          titleColor:      '#e8eaf0',
          bodyColor:       '#8b90a7',
        },
      },
      scales: {
        x: { grid: { color: '#2a2f45' }, ticks: { color: '#8b90a7', maxTicksLimit: 10 } },
        y: { grid: { color: '#2a2f45' }, ticks: { color: '#8b90a7', precision: 0 }, beginAtZero: true },
      },
    },
  });
}

// ── Gráfico por departamento ────────────────────────────────────
async function carregarGraficoDept() {
  const r     = await postAjax('relatorios.php', { action: 'por_departamento', ...getFiltros() });
  const depts = r.dados || [];

  const CORES = ['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
  const labels = depts.map(d => d.nome);
  const valores = depts.map(d => d.total);

  const ctx = document.getElementById('chart-por-dept').getContext('2d');
  if (chartDept) chartDept.destroy();

  if (!depts.length) {
    ctx.canvas.parentNode.innerHTML = '<div class="grafico-titulo">Por departamento</div><div class="empty-state" style="padding:40px"><p>Sem dados no período</p></div>';
    return;
  }

  chartDept = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data:            valores,
        backgroundColor: CORES.slice(0, depts.length),
        borderColor:     '#1a1d27',
        borderWidth:     3,
        hoverOffset:     6,
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#8b90a7', padding: 12, font: { size: 12 } },
        },
        tooltip: {
          backgroundColor: '#1e2235',
          borderColor:     '#2a2f45',
          borderWidth:     1,
          titleColor:      '#e8eaf0',
          bodyColor:       '#8b90a7',
        },
      },
    },
  });
}

// ── Ranking de agentes ─────────────────────────────────────────
async function carregarAgentes() {
  const wrap = document.getElementById('ranking-agentes');
  const r    = await postAjax('relatorios.php', { action: 'por_agente', ...getFiltros() });
  const lista = (r.dados || []).filter(a => a.total > 0);

  if (!lista.length) {
    wrap.innerHTML = '<div class="empty-state" style="padding:30px"><p>Sem dados no período</p></div>';
    return;
  }

  const maxTotal = Math.max(...lista.map(a => a.total));
  wrap.innerHTML = lista.map((a, i) => `
  <div class="ranking-item">
    <div class="ranking-pos">${i+1}</div>
    <div class="ranking-info">
      <div class="ranking-nome">${esc(a.nome)}</div>
      <div class="ranking-sub">${a.resolvidos ?? 0} resolvidos · ${a.tempo_medio_min ? Math.round(a.tempo_medio_min) + ' min avg' : '—'}</div>
      <div class="barra-wrap" style="margin-top:5px">
        <div class="barra-fill" style="width:${maxTotal ? Math.round((a.total/maxTotal)*100) : 0}%;background:${['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6'][i % 5]}"></div>
      </div>
    </div>
    <div class="ranking-val">${a.total}</div>
  </div>`).join('');
}

// ── Tempo de resposta ──────────────────────────────────────────
async function carregarTempoResposta() {
  const wrap = document.getElementById('ranking-tempo');
  const r    = await postAjax('relatorios.php', { action: 'tempo_resposta', ...getFiltros() });
  const lista = r.dados || [];

  if (!lista.length) {
    wrap.innerHTML = '<div class="empty-state" style="padding:30px"><p>Sem dados no período</p></div>';
    return;
  }

  const maxTempo = Math.max(...lista.map(a => a.min_primeira_resposta || 0));
  wrap.innerHTML = lista.map((a, i) => {
    const min = a.min_primeira_resposta ? Math.round(a.min_primeira_resposta) : 0;
    const cor = min <= 2 ? '#10b981' : min <= 10 ? '#f59e0b' : '#ef4444';
    return `
    <div class="ranking-item">
      <div class="ranking-pos">${i+1}</div>
      <div class="ranking-info">
        <div class="ranking-nome">${esc(a.atendente)}</div>
        <div class="barra-wrap" style="margin-top:6px">
          <div class="barra-fill" style="width:${maxTempo ? Math.round((min/maxTempo)*100) : 0}%;background:${cor}"></div>
        </div>
      </div>
      <div class="ranking-val" style="color:${cor}">${min}<span style="font-size:12px;font-weight:500">min</span></div>
    </div>`;
  }).join('');
}

// ── Inicialização ──────────────────────────────────────────────
carregarTodos();

// Filtrar ao pressionar Enter nos campos de data
['data-inicio','data-fim'].forEach(id => {
  document.getElementById(id).addEventListener('change', carregarTodos);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
