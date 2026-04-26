const router = require('express').Router();
const db     = require('../database');
const wa     = require('../whatsapp');
const { entrarNaFila, sairDaFila, distribuirAutomaticamente } = require('../fila');

// Listar conversas (com filtros opcionais)
router.get('/', async (req, res) => {
  const { status, departamento_id, atendente_id, busca, limite = 50, pagina = 1 } = req.query;
  const offset = (parseInt(pagina) - 1) * parseInt(limite);
  const params = [];
  let where    = 'WHERE 1=1';

  if (status)          { where += ' AND c.status = ?';           params.push(status); }
  if (departamento_id) { where += ' AND c.departamento_id = ?';  params.push(departamento_id); }
  if (atendente_id)    { where += ' AND c.atendente_id = ?';     params.push(atendente_id); }
  if (busca) {
    where += ' AND (ct.nome LIKE ? OR ct.numero LIKE ? OR ct.nome_push LIKE ?)';
    params.push(`%${busca}%`, `%${busca}%`, `%${busca}%`);
  }

  const [rows] = await db.query(`
    SELECT c.*,
           ct.nome        AS contato_nome,
           ct.nome_push   AS contato_nome_push,
           ct.numero      AS contato_numero,
           d.nome         AS departamento_nome,
           d.cor          AS departamento_cor,
           u.nome         AS atendente_nome,
           s.nome         AS sessao_nome
    FROM chat_conversas c
    JOIN chat_contatos ct         ON ct.id = c.contato_id
    LEFT JOIN chat_departamentos d ON d.id  = c.departamento_id
    LEFT JOIN chat_usuarios u      ON u.id  = c.atendente_id
    LEFT JOIN chat_sessoes_whatsapp s ON s.id = c.sessao_id
    ${where}
    ORDER BY c.ultima_mensagem_em DESC
    LIMIT ? OFFSET ?
  `, [...params, parseInt(limite), offset]);

  const [[{ total }]] = await db.query(
    `SELECT COUNT(*) AS total FROM chat_conversas c
     JOIN chat_contatos ct ON ct.id = c.contato_id ${where}`,
    params
  );

  res.json({ sucesso: true, dados: rows, total, pagina: parseInt(pagina), limite: parseInt(limite) });
});

// Detalhes de uma conversa
router.get('/:id', async (req, res) => {
  const [rows] = await db.query(`
    SELECT c.*,
           ct.nome AS contato_nome, ct.nome_push AS contato_nome_push,
           ct.numero AS contato_numero, ct.tags, ct.observacoes,
           d.nome AS departamento_nome,
           u.nome AS atendente_nome
    FROM chat_conversas c
    JOIN chat_contatos ct          ON ct.id = c.contato_id
    LEFT JOIN chat_departamentos d ON d.id  = c.departamento_id
    LEFT JOIN chat_usuarios u      ON u.id  = c.atendente_id
    WHERE c.id = ?
  `, [req.params.id]);
  if (!rows.length) return res.status(404).json({ sucesso: false, mensagem: 'Conversa não encontrada' });
  res.json({ sucesso: true, dados: rows[0] });
});

// Assumir conversa (atendente toma da fila/chatbot)
router.post('/:id/assumir', async (req, res) => {
  const { atendente_id } = req.body;
  if (!atendente_id) return res.status(400).json({ sucesso: false, mensagem: 'atendente_id obrigatório' });

  await db.query(
    `UPDATE chat_conversas
     SET atendente_id = ?, status = 'em_atendimento', chatbot_ativo = 0
     WHERE id = ?`,
    [atendente_id, req.params.id]
  );
  await sairDaFila(req.params.id);

  // Zera mensagens não lidas
  await db.query('UPDATE chat_conversas SET nao_lidas = 0 WHERE id = ?', [req.params.id]);

  const io = global._io;
  if (io) io.emit('conversa_assumida', { conversa_id: parseInt(req.params.id), atendente_id });

  res.json({ sucesso: true, mensagem: 'Conversa assumida com sucesso' });
});

// Transferir conversa
router.post('/:id/transferir', async (req, res) => {
  const { departamento_id, atendente_id, motivo, transferido_por_id } = req.body;
  const conversaId = parseInt(req.params.id);

  const [cv] = await db.query('SELECT * FROM chat_conversas WHERE id = ?', [conversaId]);
  if (!cv.length) return res.status(404).json({ sucesso: false, mensagem: 'Conversa não encontrada' });

  const antiga = cv[0];

  await db.query(
    `INSERT INTO chat_transferencias
       (conversa_id, de_atendente_id, para_atendente_id, de_departamento_id, para_departamento_id, motivo, transferido_por_id)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [conversaId, antiga.atendente_id, atendente_id || null,
     antiga.departamento_id, departamento_id || null, motivo || null, transferido_por_id || null]
  );

  const update = { status: 'aguardando', chatbot_ativo: 0 };
  if (departamento_id) update.departamento_id = departamento_id;
  if (atendente_id)    { update.atendente_id = atendente_id; update.status = 'em_atendimento'; }
  else                 update.atendente_id = null;

  const fields = Object.keys(update).map(k => `${k} = ?`).join(', ');
  await db.query(`UPDATE chat_conversas SET ${fields} WHERE id = ?`, [...Object.values(update), conversaId]);

  if (!atendente_id) {
    await sairDaFila(conversaId);
    await entrarNaFila(conversaId, departamento_id || antiga.departamento_id);
    await distribuirAutomaticamente(conversaId, departamento_id || antiga.departamento_id, global._io);
  }

  const io = global._io;
  if (io) io.emit('conversa_transferida', { conversa_id: conversaId, departamento_id, atendente_id });

  res.json({ sucesso: true, mensagem: 'Transferência realizada' });
});

// Finalizar / resolver conversa
router.post('/:id/finalizar', async (req, res) => {
  await db.query(
    `UPDATE chat_conversas
     SET status = 'resolvido', chatbot_ativo = 0, fechado_em = NOW()
     WHERE id = ?`,
    [req.params.id]
  );
  await sairDaFila(req.params.id);
  await db.query('DELETE FROM chat_chatbot_estados WHERE conversa_id = ?', [req.params.id]);

  const io = global._io;
  if (io) io.emit('conversa_encerrada', { conversa_id: parseInt(req.params.id) });

  res.json({ sucesso: true, mensagem: 'Conversa finalizada' });
});

// Marcar mensagens como lidas
router.post('/:id/marcar-lida', async (req, res) => {
  await db.query('UPDATE chat_conversas SET nao_lidas = 0 WHERE id = ?', [req.params.id]);
  await db.query(
    `UPDATE chat_mensagens SET lida = 1 WHERE conversa_id = ? AND direcao = 'entrada'`,
    [req.params.id]
  );
  res.json({ sucesso: true });
});

// Histórico de transferências
router.get('/:id/transferencias', async (req, res) => {
  const [rows] = await db.query(`
    SELECT t.*,
           ua.nome AS de_atendente, ub.nome AS para_atendente,
           da.nome AS de_departamento, db.nome AS para_departamento,
           up.nome AS transferido_por
    FROM chat_transferencias t
    LEFT JOIN chat_usuarios ua      ON ua.id = t.de_atendente_id
    LEFT JOIN chat_usuarios ub      ON ub.id = t.para_atendente_id
    LEFT JOIN chat_departamentos da ON da.id = t.de_departamento_id
    LEFT JOIN chat_departamentos db ON db.id = t.para_departamento_id
    LEFT JOIN chat_usuarios up      ON up.id = t.transferido_por_id
    WHERE t.conversa_id = ?
    ORDER BY t.criado_em ASC
  `, [req.params.id]);
  res.json({ sucesso: true, dados: rows });
});

module.exports = router;
