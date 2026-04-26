require('dotenv').config();
const express    = require('express');
const http       = require('http');
const { Server } = require('socket.io');
const cors       = require('cors');
const path       = require('path');
const fs         = require('fs');

// ── App setup ────────────────────────────────────────────────
const app    = express();
const server = http.createServer(app);
const io     = new Server(server, {
  cors: {
    origin: process.env.FRONTEND_URL || '*',
    methods: ['GET', 'POST'],
    credentials: true,
  },
  // Aceita conexões vindas via proxy reverso Apache (X-Forwarded-*)
  allowRequest: (req, callback) => callback(null, true),
});

// Disponibiliza io globalmente (usado em whatsapp.js, fila.js, chatbot.js)
global._io = io;

// ── Middlewares ───────────────────────────────────────────────
app.use(cors({ origin: process.env.FRONTEND_URL || '*' }));
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ extended: true, limit: '50mb' }));

// Middleware de autenticação por API key
app.use('/api', (req, res, next) => {
  const key = req.headers['x-api-key'] || req.query.api_key;
  if (key !== process.env.API_SECRET) {
    return res.status(401).json({ sucesso: false, mensagem: 'Não autorizado' });
  }
  next();
});

// Middleware de tratamento de erros async
const asyncHandler = fn => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);
app.use((req, res, next) => {
  const originalRoute = req.route;
  next();
});

// ── Rotas ─────────────────────────────────────────────────────
app.use('/api/sessoes',         require('./src/routes/sessoes'));
app.use('/api/conversas',       require('./src/routes/conversas'));
app.use('/api/mensagens',       require('./src/routes/mensagens'));
app.use('/api/contatos',        require('./src/routes/contatos'));
app.use('/api/usuarios',        require('./src/routes/usuarios'));
app.use('/api/departamentos',   require('./src/routes/departamentos'));
app.use('/api/relatorios',      require('./src/routes/relatorios'));
app.use('/api/configuracoes',   require('./src/routes/configuracoes'));
app.use('/api/chatbot',         require('./src/routes/chatbot'));

// Health check (sem autenticação)
app.get('/health', (req, res) => {
  res.json({ status: 'ok', uptime: process.uptime(), timestamp: new Date() });
});

// Erro 404
app.use((req, res) => {
  res.status(404).json({ sucesso: false, mensagem: `Rota não encontrada: ${req.method} ${req.path}` });
});

// Erro genérico
app.use((err, req, res, next) => {
  console.error('[API] Erro:', err.message);
  res.status(500).json({ sucesso: false, mensagem: 'Erro interno do servidor', detalhe: err.message });
});

// ── Socket.IO ─────────────────────────────────────────────────
io.on('connection', (socket) => {
  console.log(`[Socket.IO] Cliente conectado: ${socket.id}`);

  socket.on('entrar_sala', (sala) => {
    socket.join(sala);
    console.log(`[Socket.IO] ${socket.id} entrou na sala: ${sala}`);
  });

  socket.on('sair_sala', (sala) => {
    socket.leave(sala);
  });

  socket.on('digitando', (dados) => {
    socket.broadcast.emit('digitando', dados);
  });

  socket.on('disconnect', () => {
    console.log(`[Socket.IO] Cliente desconectado: ${socket.id}`);
  });
});

// Eventos emitidos pelo backend:
// nova_mensagem        → { id, conversa_id, tipo, direcao, conteudo, ... }
// nova_conversa        → { id, contato_nome, ... }
// conversa_assumida    → { conversa_id, atendente_id }
// conversa_transferida → { conversa_id, departamento_id, atendente_id }
// conversa_encerrada   → { conversa_id }
// conversa_distribuida → { conversa_id, atendente_id }
// fila_atualizada      → { departamento_id }
// usuario_status       → { usuario_id, status }
// sessao_status        → { session_id, status, numero? }
// qr_code              → { session_id, qr }
// mensagem_ack         → { msg_id, ack }

// ── Inicialização ─────────────────────────────────────────────
const PORT = process.env.PORT || 3000;

server.listen(PORT, async () => {
  console.log(`\n╔══════════════════════════════════════╗`);
  console.log(`║  OlaSuite Backend — Porta ${PORT}       ║`);
  console.log(`╚══════════════════════════════════════╝\n`);

  // Garante que pasta de uploads existe
  const mediaDir = path.resolve(process.env.MEDIA_PATH || '../frontend/assets/uploads');
  if (!fs.existsSync(mediaDir)) fs.mkdirSync(mediaDir, { recursive: true });

  // Inicializa sessões WhatsApp salvas no banco
  try {
    const wa = require('./src/whatsapp');
    await wa.inicializarTodasSessoes();
  } catch (err) {
    console.error('[WA] Erro ao inicializar sessões:', err.message);
  }
});

process.on('unhandledRejection', (reason) => {
  console.error('[Processo] Rejeição não tratada:', reason);
});
