const router = require('express').Router();
const db     = require('../database');
const wa     = require('../whatsapp');
const multer = require('multer');
const path   = require('path');
const fs     = require('fs');

const MEDIA_DIR = path.resolve(
  process.env.MEDIA_PATH || path.join(__dirname, '../../../frontend/assets/uploads')
);
if (!fs.existsSync(MEDIA_DIR)) fs.mkdirSync(MEDIA_DIR, { recursive: true });

const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, MEDIA_DIR),
  filename:    (req, file, cb) => {
    const ext  = path.extname(file.originalname);
    cb(null, `${Date.now()}_${Math.random().toString(36).substring(2)}${ext}`);
  },
});
const upload = multer({ storage, limits: { fileSize: 64 * 1024 * 1024 } }); // 64MB

// Listar mensagens de uma conversa
router.get('/conversa/:conversaId', async (req, res) => {
  const { limite = 50, antes_de } = req.query;
  const params = [parseInt(req.params.conversaId)];
  let extra = '';
  if (antes_de) { extra = 'AND m.id < ?'; params.push(parseInt(antes_de)); }

  const [rows] = await db.query(`
    SELECT m.*, u.nome AS enviado_por_nome
    FROM chat_mensagens m
    LEFT JOIN chat_usuarios u ON u.id = m.enviado_por_id
    WHERE m.conversa_id = ? ${extra}
    ORDER BY m.criado_em DESC
    LIMIT ?
  `, [...params, parseInt(limite)]);

  res.json({ sucesso: true, dados: rows.reverse() });
});

// Enviar mensagem de texto
router.post('/enviar', async (req, res) => {
  const { conversa_id, conteudo, enviado_por_id } = req.body;
  if (!conversa_id || !conteudo) {
    return res.status(400).json({ sucesso: false, mensagem: 'conversa_id e conteudo são obrigatórios' });
  }

  const [cvRows] = await db.query(`
    SELECT c.*, ct.whatsapp_id, s.session_id
    FROM chat_conversas c
    JOIN chat_contatos ct          ON ct.id = c.contato_id
    JOIN chat_sessoes_whatsapp s   ON s.id  = c.sessao_id
    WHERE c.id = ?
  `, [conversa_id]);
  if (!cvRows.length) return res.status(404).json({ sucesso: false, mensagem: 'Conversa não encontrada' });

  const cv = cvRows[0];

  try {
    const msgWa = await wa.enviarMensagem(cv.session_id, cv.whatsapp_id, conteudo);
    const [ins] = await db.query(
      `INSERT INTO chat_mensagens
         (conversa_id, whatsapp_msg_id, tipo, direcao, conteudo, enviado_por_id, status)
       VALUES (?, ?, 'texto', 'saida', ?, ?, 'enviado')`,
      [conversa_id, msgWa?.id?._serialized || null, conteudo, enviado_por_id || null]
    );

    await db.query(
      `UPDATE chat_conversas SET ultima_mensagem = ?, ultima_mensagem_em = NOW() WHERE id = ?`,
      [conteudo.substring(0, 255), conversa_id]
    );

    const payload = {
      id: ins.insertId,
      conversa_id: parseInt(conversa_id),
      tipo: 'texto',
      direcao: 'saida',
      conteudo,
      enviado_por_id,
      is_bot: 0,
      criado_em: new Date(),
    };

    const io = global._io;
    if (io) io.emit('nova_mensagem', payload);

    res.json({ sucesso: true, dados: payload });
  } catch (err) {
    res.status(500).json({ sucesso: false, mensagem: err.message });
  }
});

// Enviar mensagem com mídia
router.post('/enviar-midia', upload.single('arquivo'), async (req, res) => {
  const { conversa_id, conteudo, enviado_por_id } = req.body;
  if (!conversa_id || !req.file) {
    return res.status(400).json({ sucesso: false, mensagem: 'conversa_id e arquivo são obrigatórios' });
  }

  const [cvRows] = await db.query(`
    SELECT c.*, ct.whatsapp_id, s.session_id
    FROM chat_conversas c
    JOIN chat_contatos ct         ON ct.id = c.contato_id
    JOIN chat_sessoes_whatsapp s  ON s.id  = c.sessao_id
    WHERE c.id = ?
  `, [conversa_id]);
  if (!cvRows.length) return res.status(404).json({ sucesso: false, mensagem: 'Conversa não encontrada' });

  const cv       = cvRows[0];
  const filePath = req.file.path;
  const midiaNome = req.file.filename;
  const midiaUrl  = `/assets/uploads/${midiaNome}`;
  const midiaMime = req.file.mimetype;
  const midiaTam  = req.file.size;
  const tipo      = detectarTipoArquivo(midiaMime);

  try {
    const msgWa = await wa.enviarMensagem(cv.session_id, cv.whatsapp_id, conteudo || '', filePath);
    const [ins] = await db.query(
      `INSERT INTO chat_mensagens
         (conversa_id, whatsapp_msg_id, tipo, direcao, conteudo,
          midia_url, midia_nome, midia_tamanho, midia_mime, enviado_por_id, status)
       VALUES (?, ?, ?, 'saida', ?, ?, ?, ?, ?, ?, 'enviado')`,
      [conversa_id, msgWa?.id?._serialized || null, tipo, conteudo || null,
       midiaUrl, midiaNome, midiaTam, midiaMime, enviado_por_id || null]
    );

    await db.query(
      `UPDATE chat_conversas SET ultima_mensagem = ?, ultima_mensagem_em = NOW() WHERE id = ?`,
      [(conteudo || midiaNome).substring(0, 255), conversa_id]
    );

    const payload = {
      id: ins.insertId, conversa_id: parseInt(conversa_id), tipo,
      direcao: 'saida', conteudo, midia_url: midiaUrl, midia_nome: midiaNome,
      midia_mime: midiaMime, is_bot: 0, criado_em: new Date(),
    };

    const io = global._io;
    if (io) io.emit('nova_mensagem', payload);

    res.json({ sucesso: true, dados: payload });
  } catch (err) {
    res.status(500).json({ sucesso: false, mensagem: err.message });
  }
});

function detectarTipoArquivo(mime) {
  if (mime.startsWith('image/'))  return 'imagem';
  if (mime.startsWith('audio/'))  return 'audio';
  if (mime.startsWith('video/'))  return 'video';
  return 'documento';
}

module.exports = router;
