const router = require('express').Router();
const db     = require('../database');

// ── Fluxos ─────────────────────────────────────────────────

router.get('/fluxos', async (req, res) => {
  const [rows] = await db.query(`
    SELECT f.*, s.nome AS sessao_nome, d.nome AS departamento_nome
    FROM chat_chatbot_fluxos f
    LEFT JOIN chat_sessoes_whatsapp s ON s.id = f.sessao_id
    LEFT JOIN chat_departamentos d    ON d.id = f.departamento_id
    ORDER BY f.ordem
  `);
  res.json({ sucesso: true, dados: rows });
});

router.post('/fluxos', async (req, res) => {
  const { nome, sessao_id, departamento_id, ordem } = req.body;
  if (!nome) return res.status(400).json({ sucesso: false, mensagem: 'Nome obrigatório' });
  const [ins] = await db.query(
    'INSERT INTO chat_chatbot_fluxos (nome, sessao_id, departamento_id, ordem) VALUES (?, ?, ?, ?)',
    [nome, sessao_id || null, departamento_id || null, ordem || 0]
  );
  res.json({ sucesso: true, dados: { id: ins.insertId } });
});

router.put('/fluxos/:id', async (req, res) => {
  const { nome, sessao_id, departamento_id, ativo, ordem } = req.body;
  await db.query(
    'UPDATE chat_chatbot_fluxos SET nome=?, sessao_id=?, departamento_id=?, ativo=?, ordem=? WHERE id=?',
    [nome, sessao_id || null, departamento_id || null, ativo ? 1 : 0, ordem || 0, req.params.id]
  );
  res.json({ sucesso: true });
});

router.delete('/fluxos/:id', async (req, res) => {
  await db.query('DELETE FROM chat_chatbot_fluxos WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true });
});

// ── Etapas de um fluxo ─────────────────────────────────────

router.get('/fluxos/:fluxoId/etapas', async (req, res) => {
  const [rows] = await db.query(`
    SELECT e.*, d.nome AS departamento_nome
    FROM chat_chatbot_etapas e
    LEFT JOIN chat_departamentos d ON d.id = e.transferir_para_departamento_id
    WHERE e.fluxo_id = ?
    ORDER BY e.etapa_pai_id IS NOT NULL, e.ordem
  `, [req.params.fluxoId]);
  res.json({ sucesso: true, dados: rows });
});

router.post('/fluxos/:fluxoId/etapas', async (req, res) => {
  const { etapa_pai_id, gatilho, mensagem, tipo, transferir_para_departamento_id, ordem } = req.body;
  if (!mensagem) return res.status(400).json({ sucesso: false, mensagem: 'Mensagem obrigatória' });
  const [ins] = await db.query(
    `INSERT INTO chat_chatbot_etapas
       (fluxo_id, etapa_pai_id, gatilho, mensagem, tipo, transferir_para_departamento_id, ordem)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [req.params.fluxoId, etapa_pai_id || null, gatilho || null, mensagem,
     tipo || 'resposta', transferir_para_departamento_id || null, ordem || 0]
  );
  res.json({ sucesso: true, dados: { id: ins.insertId } });
});

router.put('/etapas/:id', async (req, res) => {
  const { etapa_pai_id, gatilho, mensagem, tipo, transferir_para_departamento_id, ordem } = req.body;
  await db.query(
    `UPDATE chat_chatbot_etapas
     SET etapa_pai_id=?, gatilho=?, mensagem=?, tipo=?, transferir_para_departamento_id=?, ordem=?
     WHERE id=?`,
    [etapa_pai_id || null, gatilho || null, mensagem,
     tipo || 'resposta', transferir_para_departamento_id || null, ordem || 0, req.params.id]
  );
  res.json({ sucesso: true });
});

router.delete('/etapas/:id', async (req, res) => {
  await db.query('DELETE FROM chat_chatbot_etapas WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true });
});

// ── Respostas automáticas ───────────────────────────────────

router.get('/respostas-automaticas', async (req, res) => {
  const [rows] = await db.query(
    'SELECT * FROM chat_respostas_automaticas ORDER BY palavra'
  );
  res.json({ sucesso: true, dados: rows });
});

router.post('/respostas-automaticas', async (req, res) => {
  const { sessao_id, palavra, resposta, exato } = req.body;
  if (!palavra || !resposta) {
    return res.status(400).json({ sucesso: false, mensagem: 'palavra e resposta são obrigatórios' });
  }
  const [ins] = await db.query(
    'INSERT INTO chat_respostas_automaticas (sessao_id, palavra, resposta, exato) VALUES (?, ?, ?, ?)',
    [sessao_id || null, palavra, resposta, exato ? 1 : 0]
  );
  res.json({ sucesso: true, dados: { id: ins.insertId } });
});

router.put('/respostas-automaticas/:id', async (req, res) => {
  const { palavra, resposta, exato, ativo } = req.body;
  await db.query(
    'UPDATE chat_respostas_automaticas SET palavra=?, resposta=?, exato=?, ativo=? WHERE id=?',
    [palavra, resposta, exato ? 1 : 0, ativo ? 1 : 0, req.params.id]
  );
  res.json({ sucesso: true });
});

router.delete('/respostas-automaticas/:id', async (req, res) => {
  await db.query('DELETE FROM chat_respostas_automaticas WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true });
});

module.exports = router;
