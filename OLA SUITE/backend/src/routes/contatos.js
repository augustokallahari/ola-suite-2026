const router = require('express').Router();
const db     = require('../database');

// Listar contatos
router.get('/', async (req, res) => {
  const { busca, limite = 50, pagina = 1 } = req.query;
  const offset = (parseInt(pagina) - 1) * parseInt(limite);
  const params = [];
  let where = 'WHERE 1=1';

  if (busca) {
    where += ' AND (nome LIKE ? OR nome_push LIKE ? OR numero LIKE ?)';
    params.push(`%${busca}%`, `%${busca}%`, `%${busca}%`);
  }

  const [rows] = await db.query(
    `SELECT * FROM chat_contatos ${where} ORDER BY nome, nome_push LIMIT ? OFFSET ?`,
    [...params, parseInt(limite), offset]
  );
  const [[{ total }]] = await db.query(
    `SELECT COUNT(*) AS total FROM chat_contatos ${where}`, params
  );

  res.json({ sucesso: true, dados: rows, total, pagina: parseInt(pagina), limite: parseInt(limite) });
});

// Detalhes do contato + histórico
router.get('/:id', async (req, res) => {
  const [rows] = await db.query('SELECT * FROM chat_contatos WHERE id = ?', [req.params.id]);
  if (!rows.length) return res.status(404).json({ sucesso: false, mensagem: 'Contato não encontrado' });

  const [historico] = await db.query(`
    SELECT c.id, c.protocolo, c.status, c.aberto_em, c.fechado_em,
           d.nome AS departamento, u.nome AS atendente
    FROM chat_conversas c
    LEFT JOIN chat_departamentos d ON d.id = c.departamento_id
    LEFT JOIN chat_usuarios u      ON u.id = c.atendente_id
    WHERE c.contato_id = ?
    ORDER BY c.aberto_em DESC
    LIMIT 20
  `, [req.params.id]);

  res.json({ sucesso: true, dados: { ...rows[0], historico } });
});

// Atualizar contato
router.put('/:id', async (req, res) => {
  const { nome, email, tags, observacoes, bloqueado } = req.body;
  await db.query(
    `UPDATE chat_contatos
     SET nome = ?, email = ?, tags = ?, observacoes = ?, bloqueado = ?
     WHERE id = ?`,
    [nome || null, email || null, tags || null, observacoes || null,
     bloqueado ? 1 : 0, req.params.id]
  );
  res.json({ sucesso: true, mensagem: 'Contato atualizado' });
});

// Bloquear / desbloquear
router.post('/:id/bloquear', async (req, res) => {
  const [rows] = await db.query('SELECT bloqueado FROM chat_contatos WHERE id = ?', [req.params.id]);
  if (!rows.length) return res.status(404).json({ sucesso: false, mensagem: 'Não encontrado' });
  const novo = rows[0].bloqueado ? 0 : 1;
  await db.query('UPDATE chat_contatos SET bloqueado = ? WHERE id = ?', [novo, req.params.id]);
  res.json({ sucesso: true, bloqueado: !!novo });
});

module.exports = router;
