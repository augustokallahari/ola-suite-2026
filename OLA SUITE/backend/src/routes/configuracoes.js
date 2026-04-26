const router = require('express').Router();
const db     = require('../database');

// Listar todas as configurações (agrupadas)
router.get('/', async (req, res) => {
  const [rows] = await db.query('SELECT * FROM chat_configuracoes ORDER BY grupo, chave');
  const grupos = {};
  for (const r of rows) {
    if (!grupos[r.grupo]) grupos[r.grupo] = {};
    grupos[r.grupo][r.chave] = r.valor;
  }
  res.json({ sucesso: true, dados: grupos });
});

// Atualizar uma configuração
router.put('/:chave', async (req, res) => {
  const { valor } = req.body;
  await db.query(
    'UPDATE chat_configuracoes SET valor = ? WHERE chave = ?',
    [valor ?? null, req.params.chave]
  );
  res.json({ sucesso: true, mensagem: 'Configuração salva' });
});

// Atualizar várias de uma vez
router.post('/lote', async (req, res) => {
  const { configuracoes } = req.body; // { chave: valor, ... }
  if (!configuracoes || typeof configuracoes !== 'object') {
    return res.status(400).json({ sucesso: false, mensagem: 'Payload inválido' });
  }
  const conn = await db.getConnection();
  try {
    await conn.beginTransaction();
    for (const [chave, valor] of Object.entries(configuracoes)) {
      await conn.query('UPDATE chat_configuracoes SET valor = ? WHERE chave = ?', [valor, chave]);
    }
    await conn.commit();
    res.json({ sucesso: true, mensagem: 'Configurações salvas' });
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
});

// Horários de atendimento
router.get('/horarios', async (req, res) => {
  const [rows] = await db.query(
    'SELECT * FROM chat_horarios_atendimento WHERE sessao_id IS NULL ORDER BY dia_semana'
  );
  res.json({ sucesso: true, dados: rows });
});

router.post('/horarios', async (req, res) => {
  const { horarios } = req.body; // [{dia_semana, hora_inicio, hora_fim, ativo}]
  if (!Array.isArray(horarios)) {
    return res.status(400).json({ sucesso: false, mensagem: 'horarios deve ser array' });
  }
  await db.query('DELETE FROM chat_horarios_atendimento WHERE sessao_id IS NULL');
  for (const h of horarios) {
    if (!h.ativo) continue;
    await db.query(
      'INSERT INTO chat_horarios_atendimento (sessao_id, dia_semana, hora_inicio, hora_fim) VALUES (NULL, ?, ?, ?)',
      [h.dia_semana, h.hora_inicio, h.hora_fim]
    );
  }
  res.json({ sucesso: true, mensagem: 'Horários salvos' });
});

// Mensagem fora do horário
router.get('/mensagem-fora-horario', async (req, res) => {
  const [rows] = await db.query(
    'SELECT * FROM chat_mensagem_fora_horario WHERE sessao_id IS NULL LIMIT 1'
  );
  res.json({ sucesso: true, dados: rows[0] || null });
});

router.put('/mensagem-fora-horario', async (req, res) => {
  const { mensagem, ativo } = req.body;
  await db.query(
    `INSERT INTO chat_mensagem_fora_horario (sessao_id, mensagem, ativo)
     VALUES (NULL, ?, ?)
     ON DUPLICATE KEY UPDATE mensagem = ?, ativo = ?`,
    [mensagem, ativo ? 1 : 0, mensagem, ativo ? 1 : 0]
  );
  res.json({ sucesso: true, mensagem: 'Mensagem salva' });
});

module.exports = router;
