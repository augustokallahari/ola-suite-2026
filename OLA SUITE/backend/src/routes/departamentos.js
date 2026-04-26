const router = require('express').Router();
const db     = require('../database');

router.get('/', async (req, res) => {
  const [rows] = await db.query(`
    SELECT d.*,
           COUNT(DISTINCT u.id)  AS total_atendentes,
           COUNT(DISTINCT c.id)  AS atendimentos_abertos
    FROM chat_departamentos d
    LEFT JOIN chat_usuarios u    ON u.departamento_id = d.id AND u.ativo = 1
    LEFT JOIN chat_conversas c   ON c.departamento_id = d.id AND c.status IN ('aguardando','em_atendimento')
    WHERE d.ativo = 1
    GROUP BY d.id
    ORDER BY d.nome
  `);
  res.json({ sucesso: true, dados: rows });
});

router.post('/', async (req, res) => {
  const { nome, descricao, cor } = req.body;
  if (!nome) return res.status(400).json({ sucesso: false, mensagem: 'Nome obrigatório' });
  const [ins] = await db.query(
    'INSERT INTO chat_departamentos (nome, descricao, cor) VALUES (?, ?, ?)',
    [nome, descricao || null, cor || '#3b82f6']
  );
  res.json({ sucesso: true, dados: { id: ins.insertId } });
});

router.put('/:id', async (req, res) => {
  const { nome, descricao, cor, ativo } = req.body;
  await db.query(
    'UPDATE chat_departamentos SET nome = ?, descricao = ?, cor = ?, ativo = ? WHERE id = ?',
    [nome, descricao || null, cor || '#3b82f6', ativo ? 1 : 0, req.params.id]
  );
  res.json({ sucesso: true, mensagem: 'Departamento atualizado' });
});

router.delete('/:id', async (req, res) => {
  await db.query('UPDATE chat_departamentos SET ativo = 0 WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true, mensagem: 'Departamento desativado' });
});

// Fila do departamento
router.get('/:id/fila', async (req, res) => {
  const [rows] = await db.query(`
    SELECT f.*, c.protocolo, c.aberto_em,
           ct.nome AS contato_nome, ct.numero AS contato_numero
    FROM chat_fila f
    JOIN chat_conversas c  ON c.id  = f.conversa_id
    JOIN chat_contatos ct  ON ct.id = c.contato_id
    WHERE f.departamento_id = ?
    ORDER BY f.prioridade ASC, f.entrada_em ASC
  `, [req.params.id]);
  res.json({ sucesso: true, dados: rows });
});

module.exports = router;
