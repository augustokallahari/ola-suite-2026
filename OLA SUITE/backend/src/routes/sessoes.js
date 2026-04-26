const router    = require('express').Router();
const db        = require('../database');
const wa        = require('../whatsapp');
const { v4: uuidv4 } = require('uuid');

// Listar sessões
router.get('/', async (req, res) => {
  const [rows] = await db.query(
    'SELECT id, nome, numero, status, session_id, departamento_id, ativo, criado_em FROM chat_sessoes_whatsapp ORDER BY id'
  );
  // Enriquece com status em memória
  const data = rows.map(r => ({ ...r, status_runtime: wa.getStatus(r.session_id) }));
  res.json({ sucesso: true, dados: data });
});

// Criar nova sessão
router.post('/', async (req, res) => {
  const { nome, departamento_id } = req.body;
  if (!nome) return res.status(400).json({ sucesso: false, mensagem: 'Nome obrigatório' });

  const sessionId = `sessao_${uuidv4().replace(/-/g, '').substring(0, 12)}`;
  const [ins] = await db.query(
    `INSERT INTO chat_sessoes_whatsapp (nome, session_id, departamento_id, status)
     VALUES (?, ?, ?, 'desconectado')`,
    [nome, sessionId, departamento_id || null]
  );

  res.json({ sucesso: true, dados: { id: ins.insertId, session_id: sessionId } });
});

// Conectar / gerar QR
router.post('/:sessionId/conectar', async (req, res) => {
  const { sessionId } = req.params;
  const [rows] = await db.query(
    'SELECT * FROM chat_sessoes_whatsapp WHERE session_id = ?',
    [sessionId]
  );
  if (!rows.length) return res.status(404).json({ sucesso: false, mensagem: 'Sessão não encontrada' });

  const status = wa.getStatus(sessionId);
  if (status === 'conectado') {
    return res.json({ sucesso: true, mensagem: 'Já conectada' });
  }

  await wa.criarSessao(sessionId, rows[0].id);
  res.json({ sucesso: true, mensagem: 'Inicializando sessão, aguarde QR Code...' });
});

// Desconectar sessão
router.post('/:sessionId/desconectar', async (req, res) => {
  const { sessionId } = req.params;
  await wa.destruirSessao(sessionId);
  res.json({ sucesso: true, mensagem: 'Sessão desconectada' });
});

// QR Code atual (base64)
router.get('/:sessionId/qr', async (req, res) => {
  const [rows] = await db.query(
    'SELECT qr_code, status FROM chat_sessoes_whatsapp WHERE session_id = ?',
    [req.params.sessionId]
  );
  if (!rows.length) return res.status(404).json({ sucesso: false, mensagem: 'Sessão não encontrada' });
  res.json({ sucesso: true, qr: rows[0].qr_code, status: rows[0].status });
});

// Status da sessão
router.get('/:sessionId/status', async (req, res) => {
  const { sessionId } = req.params;
  const status = wa.getStatus(sessionId);
  const [rows] = await db.query(
    'SELECT numero FROM chat_sessoes_whatsapp WHERE session_id = ?',
    [sessionId]
  );
  res.json({ sucesso: true, status, numero: rows[0]?.numero || null });
});

// Deletar sessão
router.delete('/:id', async (req, res) => {
  const [rows] = await db.query('SELECT session_id FROM chat_sessoes_whatsapp WHERE id = ?', [req.params.id]);
  if (rows.length) await wa.destruirSessao(rows[0].session_id);
  await db.query('UPDATE chat_sessoes_whatsapp SET ativo = 0 WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true, mensagem: 'Sessão removida' });
});

module.exports = router;
