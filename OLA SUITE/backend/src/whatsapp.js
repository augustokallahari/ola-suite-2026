const { Client, LocalAuth, MessageMedia } = require('whatsapp-web.js');
const qrcode  = require('qrcode');
const path    = require('path');
const fs      = require('fs');
const db      = require('./database');
const { processarMensagem } = require('./chatbot');
const { gerarProtocolo }    = require('./protocolo');

const sessoes = new Map(); // sessionId → { client, status }

const MEDIA_DIR = path.resolve(
  process.env.MEDIA_PATH || path.join(__dirname, '../../frontend/assets/uploads')
);

function getIO() {
  return global._io || null;
}

// Inicializa todas as sessões ativas ao subir o servidor
async function inicializarTodasSessoes() {
  const [rows] = await db.query(
    'SELECT * FROM chat_sessoes_whatsapp WHERE ativo = 1'
  );
  for (const sessao of rows) {
    await criarSessao(sessao.session_id, sessao.id);
  }
}

// Cria e registra uma sessão whatsapp
async function criarSessao(sessionId, dbId) {
  if (sessoes.has(sessionId)) return;

  console.log(`[WA] Iniciando sessão: ${sessionId}`);

  await db.query(
    `UPDATE chat_sessoes_whatsapp SET status = 'aguardando_qr', qr_code = NULL WHERE session_id = ?`,
    [sessionId]
  );

  const client = new Client({
    authStrategy: new LocalAuth({
      clientId: sessionId,
      dataPath: path.resolve(process.env.SESSION_PATH || path.join(__dirname, '../sessions')),
    }),
    puppeteer: {
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
      ],
    },
  });

  sessoes.set(sessionId, { client, status: 'aguardando_qr', dbId });

  // ── Eventos ──────────────────────────────────────────────

  client.on('qr', async (qr) => {
    try {
      const qrBase64 = await qrcode.toDataURL(qr);
      await db.query(
        `UPDATE chat_sessoes_whatsapp SET qr_code = ?, status = 'aguardando_qr' WHERE session_id = ?`,
        [qrBase64, sessionId]
      );
      const io = getIO();
      if (io) io.emit('qr_code', { session_id: sessionId, qr: qrBase64 });
      console.log(`[WA] QR gerado para sessão: ${sessionId}`);
    } catch (e) {
      console.error('[WA] Erro ao gerar QR:', e.message);
    }
  });

  client.on('ready', async () => {
    console.log(`[WA] Sessão conectada: ${sessionId}`);
    const info = client.info;
    await db.query(
      `UPDATE chat_sessoes_whatsapp
       SET status = 'conectado', numero = ?, qr_code = NULL
       WHERE session_id = ?`,
      [info?.wid?.user || null, sessionId]
    );
    const entry = sessoes.get(sessionId);
    if (entry) entry.status = 'conectado';
    const io = getIO();
    if (io) io.emit('sessao_status', { session_id: sessionId, status: 'conectado', numero: info?.wid?.user });
  });

  client.on('authenticated', () => {
    console.log(`[WA] Autenticado: ${sessionId}`);
  });

  client.on('auth_failure', async (msg) => {
    console.error(`[WA] Falha de autenticação (${sessionId}):`, msg);
    await db.query(
      `UPDATE chat_sessoes_whatsapp SET status = 'erro' WHERE session_id = ?`,
      [sessionId]
    );
    const io = getIO();
    if (io) io.emit('sessao_status', { session_id: sessionId, status: 'erro' });
  });

  client.on('disconnected', async (reason) => {
    console.warn(`[WA] Desconectado (${sessionId}):`, reason);
    await db.query(
      `UPDATE chat_sessoes_whatsapp SET status = 'desconectado' WHERE session_id = ?`,
      [sessionId]
    );
    const entry = sessoes.get(sessionId);
    if (entry) entry.status = 'desconectado';
    const io = getIO();
    if (io) io.emit('sessao_status', { session_id: sessionId, status: 'desconectado' });

    // Reconexão automática após 10 segundos
    setTimeout(() => {
      console.log(`[WA] Tentando reconectar sessão: ${sessionId}`);
      client.initialize().catch(err => console.error('[WA] Erro na reconexão:', err.message));
    }, 10000);
  });

  client.on('message', async (msg) => {
    try {
      await handleMensagemRecebida(msg, sessionId, dbId);
    } catch (err) {
      console.error('[WA] Erro ao processar mensagem:', err.message);
    }
  });

  client.on('message_ack', async (msg, ack) => {
    // ack: 0=erro, 1=enviado, 2=entregue, 3=lido
    const statusMap = { 0: 'erro', 1: 'enviado', 2: 'entregue', 3: 'lido' };
    await db.query(
      `UPDATE chat_mensagens SET status = ? WHERE whatsapp_msg_id = ?`,
      [statusMap[ack] || 'enviado', msg.id._serialized]
    );
    const io = getIO();
    if (io) io.emit('mensagem_ack', { msg_id: msg.id._serialized, ack });
  });

  await client.initialize();
}

// Lida com mensagem recebida
async function handleMensagemRecebida(msg, sessionId, dbSessaoId) {
  if (msg.isStatus || msg.from === 'status@broadcast') return;

  const io         = getIO();
  const waId       = msg.from; // ex: 5511999999999@c.us
  const numero     = waId.replace('@c.us', '').replace('@g.us', '');
  const nomePush   = msg._data?.notifyName || null;

  // Busca sessão no banco
  const [sessaoRows] = await db.query(
    'SELECT * FROM chat_sessoes_whatsapp WHERE session_id = ?',
    [sessionId]
  );
  const sessaoDb = sessaoRows[0];
  if (!sessaoDb) return;

  // Upsert contato
  await db.query(
    `INSERT INTO chat_contatos (whatsapp_id, numero, nome_push)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE nome_push = COALESCE(?, nome_push)`,
    [waId, numero, nomePush, nomePush]
  );
  const [contatoRows] = await db.query(
    'SELECT * FROM chat_contatos WHERE whatsapp_id = ?',
    [waId]
  );
  const contato = contatoRows[0];

  // Busca conversa aberta ou cria nova
  let [conversaRows] = await db.query(
    `SELECT c.*, ct.whatsapp_id
     FROM chat_conversas c
     JOIN chat_contatos ct ON ct.id = c.contato_id
     WHERE c.contato_id = ?
       AND c.sessao_id  = ?
       AND c.status NOT IN ('resolvido','abandonado')
     ORDER BY c.aberto_em DESC
     LIMIT 1`,
    [contato.id, sessaoDb.id]
  );

  let conversa;
  if (conversaRows.length) {
    conversa = conversaRows[0];
  } else {
    const protocolo = await gerarProtocolo();
    const [ins] = await db.query(
      `INSERT INTO chat_conversas
         (contato_id, sessao_id, departamento_id, protocolo, status, chatbot_ativo)
       VALUES (?, ?, ?, ?, 'aguardando', 1)`,
      [contato.id, sessaoDb.id, sessaoDb.departamento_id, protocolo]
    );
    const [novaCv] = await db.query(
      `SELECT c.*, ct.whatsapp_id
       FROM chat_conversas c
       JOIN chat_contatos ct ON ct.id = c.contato_id
       WHERE c.id = ?`,
      [ins.insertId]
    );
    conversa = novaCv[0];
    if (io) io.emit('nova_conversa', conversa);
  }

  // Determina tipo e salva mídia
  let tipo      = 'texto';
  let conteudo  = msg.body || null;
  let midiaUrl  = null;
  let midiaNome = null;
  let midiaMime = null;
  let midiaTam  = null;

  if (msg.hasMedia) {
    tipo = detectarTipoMidia(msg.type);
    try {
      const media = await msg.downloadMedia();
      if (media) {
        midiaMime = media.mimetype;
        const ext  = media.mimetype.split('/')[1]?.split(';')[0] || 'bin';
        midiaNome  = media.filename || `${Date.now()}.${ext}`;
        const dest = path.join(MEDIA_DIR, midiaNome);
        if (!fs.existsSync(MEDIA_DIR)) fs.mkdirSync(MEDIA_DIR, { recursive: true });
        fs.writeFileSync(dest, Buffer.from(media.data, 'base64'));
        midiaUrl  = `/assets/uploads/${midiaNome}`;
        midiaTam  = Buffer.byteLength(media.data, 'base64');
      }
    } catch (e) {
      console.error('[WA] Erro ao baixar mídia:', e.message);
    }
  }

  // Salva mensagem
  const [msgIns] = await db.query(
    `INSERT INTO chat_mensagens
       (conversa_id, whatsapp_msg_id, tipo, direcao, conteudo,
        midia_url, midia_nome, midia_tamanho, midia_mime)
     VALUES (?, ?, ?, 'entrada', ?, ?, ?, ?, ?)`,
    [conversa.id, msg.id._serialized, tipo, conteudo,
     midiaUrl, midiaNome, midiaTam, midiaMime]
  );

  // Atualiza conversa
  await db.query(
    `UPDATE chat_conversas
     SET ultima_mensagem    = ?,
         ultima_mensagem_em = NOW(),
         nao_lidas          = nao_lidas + 1
     WHERE id = ?`,
    [(conteudo || midiaNome || tipo).substring(0, 255), conversa.id]
  );

  const novaMsgPayload = {
    id:          msgIns.insertId,
    conversa_id: conversa.id,
    tipo,
    direcao:     'entrada',
    conteudo,
    midia_url:   midiaUrl,
    midia_nome:  midiaNome,
    midia_mime:  midiaMime,
    is_bot:      0,
    criado_em:   new Date(),
  };

  if (io) io.emit('nova_mensagem', novaMsgPayload);

  // Chatbot
  if (conversa.chatbot_ativo && tipo === 'texto') {
    const { client } = sessoes.get(sessionId) || {};
    await processarMensagem(
      { ...conversa, whatsapp_id: waId },
      conteudo,
      io,
      client
    );
  }
}

function detectarTipoMidia(type) {
  if (['image', 'sticker'].includes(type)) return type === 'sticker' ? 'sticker' : 'imagem';
  if (type === 'audio' || type === 'ptt') return 'audio';
  if (type === 'video') return 'video';
  if (type === 'document') return 'documento';
  if (type === 'location') return 'localizacao';
  if (type === 'vcard') return 'contato';
  return 'texto';
}

// Envia mensagem por uma sessão
async function enviarMensagem(sessionId, para, texto, mediaPath = null) {
  const entry = sessoes.get(sessionId);
  if (!entry || entry.status !== 'conectado') {
    throw new Error('Sessão não conectada');
  }
  const { client } = entry;

  let result;
  if (mediaPath) {
    const media = MessageMedia.fromFilePath(mediaPath);
    result = await client.sendMessage(para, media, { caption: texto || undefined });
  } else {
    result = await client.sendMessage(para, texto);
  }
  return result;
}

// Destrói sessão
async function destruirSessao(sessionId) {
  const entry = sessoes.get(sessionId);
  if (entry) {
    try { await entry.client.destroy(); } catch {}
    sessoes.delete(sessionId);
  }
  await db.query(
    `UPDATE chat_sessoes_whatsapp SET status = 'desconectado', qr_code = NULL WHERE session_id = ?`,
    [sessionId]
  );
}

function getStatus(sessionId) {
  return sessoes.get(sessionId)?.status || 'desconectado';
}

function getClient(sessionId) {
  return sessoes.get(sessionId)?.client || null;
}

module.exports = {
  criarSessao,
  destruirSessao,
  inicializarTodasSessoes,
  enviarMensagem,
  getStatus,
  getClient,
  sessoes,
};
