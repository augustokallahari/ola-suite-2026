/* ============================================================
   OlaSuite — JS Global
   ============================================================ */

// ── Socket.IO ─────────────────────────────────────────────────
// Em produção o Node.js é exposto via proxy reverso Apache no mesmo domínio.
// OLA_SOCKET_URL é definido pelo PHP no header.php como variável JS global.
const socket = io(window.OLA_SOCKET_URL || 'http://localhost:3000', {
  transports: ['websocket', 'polling'],
  path: '/socket.io'
});

socket.on('connect',    () => console.log('[Socket] Conectado ao servidor'));
socket.on('disconnect', () => console.log('[Socket] Desconectado'));

// Atualiza badge de não lidas
socket.on('nova_mensagem', (msg) => {
  if (msg.direcao === 'entrada') {
    incrementarBadge();
    tocarSomNotificacao();
  }
});

// Atualiza status do usuário na topbar
socket.on('usuario_status', ({ usuario_id, status }) => {
  if (usuario_id === OLA.usuarioId) {
    atualizarDotStatus(status);
  }
});

// ── Namespace global ──────────────────────────────────────────
window.OLA = window.OLA || {
  usuarioId:   null,
  usuarioNome: null,
  nivel:       null,
  apiUrl:      'http://localhost:3000/api',
  apiKey:      null, // Não expõe no frontend — usa PHP como proxy
};

// ── Sidebar toggle ────────────────────────────────────────────
function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  if (!sb) return;
  if (window.innerWidth <= 768) {
    sb.classList.toggle('aberta');
  } else {
    sb.classList.toggle('collapsed');
    localStorage.setItem('sidebar_collapsed', sb.classList.contains('collapsed') ? '1' : '0');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const sb = document.getElementById('sidebar');
  if (sb && localStorage.getItem('sidebar_collapsed') === '1' && window.innerWidth > 768) {
    sb.classList.add('collapsed');
  }
  const nivel = document.body.dataset.nivel || '';
  atualizarDotStatus(document.body.dataset.status || 'offline');
  OLA.usuarioId   = parseInt(document.body.dataset.uid || '0');
  OLA.usuarioNome = document.body.dataset.nome || '';
  OLA.nivel       = nivel;
});

// ── Menu status ───────────────────────────────────────────────
function toggleMenuStatus() {
  const m = document.getElementById('menu-status');
  if (!m) return;
  m.style.display = m.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('#btn-status-wrap')) {
    const m = document.getElementById('menu-status');
    if (m) m.style.display = 'none';
  }
});

function alterarStatus(status) {
  fetch('?action=alterar_status', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  }).then(r => r.json()).then(data => {
    if (data.sucesso) {
      atualizarDotStatus(status);
      toggleMenuStatus();
    }
  });
}

function atualizarDotStatus(status) {
  const dot = document.getElementById('dot-status-usuario');
  if (!dot) return;
  dot.className = 'status-dot ' + status;
}

// ── Badges de não lidas ───────────────────────────────────────
let totalNaoLidas = 0;

function incrementarBadge(n = 1) {
  totalNaoLidas += n;
  atualizarBadges();
}

function atualizarBadges() {
  const badgeTop  = document.getElementById('badge-nao-lidas');
  const countTop  = document.getElementById('count-nao-lidas');
  const badgeSb   = document.getElementById('sb-badge-inbox');

  if (badgeTop) badgeTop.style.display = totalNaoLidas > 0 ? 'flex' : 'none';
  if (countTop) countTop.textContent = totalNaoLidas > 99 ? '99+' : totalNaoLidas;
  if (badgeSb)  {
    badgeSb.style.display = totalNaoLidas > 0 ? 'flex' : 'none';
    badgeSb.textContent   = totalNaoLidas > 99 ? '99+' : totalNaoLidas;
  }

  document.title = totalNaoLidas > 0
    ? `(${totalNaoLidas}) OlaSuite`
    : 'OlaSuite';
}

function zerarBadge() {
  totalNaoLidas = 0;
  atualizarBadges();
}

// ── Som de notificação ────────────────────────────────────────
let somAtivo = true;
let audioCtx = null;

function tocarSomNotificacao() {
  if (!somAtivo) return;
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const osc  = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.frequency.setValueAtTime(880, audioCtx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(440, audioCtx.currentTime + 0.1);
    gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.3);
  } catch {}
}

// ── Toast ──────────────────────────────────────────────────────
function toast(mensagem, tipo = 'info', duracao = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const t = document.createElement('div');
  t.className = `toast ${tipo}`;
  t.textContent = mensagem;
  container.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, duracao);
  setTimeout(() => t.remove(), duracao + 350);
}

// ── Modal helper ───────────────────────────────────────────────
function abrirModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'flex';
}
function fecharModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}
// Fecha modal ao clicar no overlay
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.style.display = 'none';
  }
});

// ── AJAX helper ───────────────────────────────────────────────
async function postAjax(url, dados) {
  const r = await fetch(url, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(dados),
  });
  return r.json();
}

async function getAjax(url, params = {}) {
  const qs = new URLSearchParams(params).toString();
  const r  = await fetch(qs ? `${url}?${qs}` : url);
  return r.json();
}

// ── Formata data ───────────────────────────────────────────────
function formatarData(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const hoje = new Date();
  if (d.toDateString() === hoje.toDateString()) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) +
         ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

// ── Escapa HTML ────────────────────────────────────────────────
function esc(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
