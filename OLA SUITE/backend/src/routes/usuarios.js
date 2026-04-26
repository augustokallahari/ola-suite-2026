const router = require('express').Router();
const db     = require('../database');
const bcrypt = require('bcrypt');

// Listar usuários
router.get('/', async (req, res) => {
  const [rows] = await db.query(`
    SELECT u.id, u.nome, u.email, u.nivel, u.status, u.ativo, u.ultimo_acesso, u.criado_em,
           d.nome AS departamento_nome
    FROM chat_usuarios u
    LEFT JOIN chat_departamentos d ON d.id = u.departamento_id
    ORDER BY u.nome
  `);
  res.json({ sucesso: true, dados: rows });
});

// Criar usuário
router.post('/', async (req, res) => {
  const { nome, email, senha, nivel, departamento_id } = req.body;
  if (!nome || !email || !senha) {
    return res.status(400).json({ sucesso: false, mensagem: 'nome, email e senha são obrigatórios' });
  }
  const hash = await bcrypt.hash(senha, 12);
  try {
    const [ins] = await db.query(
      `INSERT INTO chat_usuarios (nome, email, senha, nivel, departamento_id)
       VALUES (?, ?, ?, ?, ?)`,
      [nome, email, hash, nivel || 'atendente', departamento_id || null]
    );
    res.json({ sucesso: true, dados: { id: ins.insertId } });
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(409).json({ sucesso: false, mensagem: 'E-mail já cadastrado' });
    }
    throw err;
  }
});

// Atualizar usuário
router.put('/:id', async (req, res) => {
  const { nome, email, nivel, departamento_id, ativo, senha } = req.body;
  const updates = { nome, email, nivel, departamento_id: departamento_id || null, ativo: ativo ? 1 : 0 };
  if (senha) updates.senha = await bcrypt.hash(senha, 12);

  const fields = Object.keys(updates).map(k => `${k} = ?`).join(', ');
  await db.query(`UPDATE chat_usuarios SET ${fields} WHERE id = ?`, [...Object.values(updates), req.params.id]);
  res.json({ sucesso: true, mensagem: 'Usuário atualizado' });
});

// Deletar usuário
router.delete('/:id', async (req, res) => {
  await db.query('UPDATE chat_usuarios SET ativo = 0 WHERE id = ?', [req.params.id]);
  res.json({ sucesso: true, mensagem: 'Usuário desativado' });
});

// Atualizar status (online/ausente/offline)
router.post('/:id/status', async (req, res) => {
  const { status } = req.body;
  if (!['online', 'ausente', 'offline'].includes(status)) {
    return res.status(400).json({ sucesso: false, mensagem: 'Status inválido' });
  }
  await db.query('UPDATE chat_usuarios SET status = ? WHERE id = ?', [status, req.params.id]);
  const io = global._io;
  if (io) io.emit('usuario_status', { usuario_id: parseInt(req.params.id), status });
  res.json({ sucesso: true });
});

// Autenticação (login via API — PHP faz sua própria sessão, mas pode usar para validação)
router.post('/autenticar', async (req, res) => {
  const { email, senha } = req.body;
  if (!email || !senha) {
    return res.status(400).json({ sucesso: false, mensagem: 'E-mail e senha obrigatórios' });
  }
  const [rows] = await db.query(
    'SELECT * FROM chat_usuarios WHERE email = ? AND ativo = 1',
    [email]
  );
  if (!rows.length) {
    return res.status(401).json({ sucesso: false, mensagem: 'Credenciais inválidas' });
  }
  const usuario = rows[0];
  const ok = await bcrypt.compare(senha, usuario.senha);
  if (!ok) {
    return res.status(401).json({ sucesso: false, mensagem: 'Credenciais inválidas' });
  }
  await db.query('UPDATE chat_usuarios SET ultimo_acesso = NOW() WHERE id = ?', [usuario.id]);
  delete usuario.senha;
  res.json({ sucesso: true, dados: usuario });
});

module.exports = router;
