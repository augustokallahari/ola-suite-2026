const db      = require('./database');
const moment  = require('moment-timezone');
const { entrarNaFila, distribuirAutomaticamente } = require('./fila');

const FUSO = 'America/Sao_Paulo';

// Verifica se está dentro do horário de atendimento
async function dentroDHorario(sessaoId) {
  const agora = moment().tz(FUSO);
  const diaSemana = agora.day(); // 0=Dom, 6=Sab
  const horaAtual = agora.format('HH:mm:ss');

  const [rows] = await db.query(`
    SELECT * FROM chat_horarios_atendimento
    WHERE dia_semana = ?
      AND hora_inicio <= ?
      AND hora_fim >= ?
      AND ativo = 1
      AND (sessao_id = ? OR sessao_id IS NULL)
    ORDER BY sessao_id DESC
    LIMIT 1
  `, [diaSemana, horaAtual, horaAtual, sessaoId]);

  return rows.length > 0;
}

// Busca mensagem fora do horário
async function getMensagemForaHorario(sessaoId) {
  const [rows] = await db.query(`
    SELECT mensagem FROM chat_mensagem_fora_horario
    WHERE ativo = 1
      AND (sessao_id = ? OR sessao_id IS NULL)
    ORDER BY sessao_id DESC
    LIMIT 1
  `, [sessaoId]);
  return rows[0]?.mensagem || null;
}

// Processa mensagem recebida pelo chatbot
async function processarMensagem(conversa, mensagemTexto, io, whatsappCliente) {
  const conversaId  = conversa.id;
  const sessaoId    = conversa.sessao_id;
  const textoNorm   = (mensagemTexto || '').trim().toLowerCase();

  // Fora do horário
  const emHorario = await dentroDHorario(sessaoId);
  if (!emHorario) {
    const msgFora = await getMensagemForaHorario(sessaoId);
    if (msgFora) {
      await enviarMensagemBot(conversa, msgFora, whatsappCliente, io);
    }
    return;
  }

  // Verifica resposta automática por palavra-chave (antes do fluxo)
  const respondeuKeyword = await verificarRespostaAutomatica(conversa, textoNorm, whatsappCliente, io);
  if (respondeuKeyword) return;

  // Busca estado atual do chatbot
  const [estadoRows] = await db.query(
    'SELECT * FROM chat_chatbot_estados WHERE conversa_id = ?',
    [conversaId]
  );
  const estado = estadoRows[0] || null;

  // Sem estado → primeira mensagem, busca fluxo ativo
  if (!estado || !estado.etapa_id) {
    const fluxo = await getFluxoAtivo(sessaoId);
    if (!fluxo) {
      // Sem chatbot configurado → vai direto para fila
      await colocarNaFila(conversa, io);
      return;
    }

    // Busca etapas raiz do fluxo (menu principal)
    const [etapasRaiz] = await db.query(
      `SELECT * FROM chat_chatbot_etapas
       WHERE fluxo_id = ? AND etapa_pai_id IS NULL
       ORDER BY ordem ASC`,
      [fluxo.id]
    );

    if (!etapasRaiz.length) {
      await colocarNaFila(conversa, io);
      return;
    }

    // Salva estado inicial
    await db.query(
      `INSERT INTO chat_chatbot_estados (conversa_id, fluxo_id, etapa_id)
       VALUES (?, ?, NULL)
       ON DUPLICATE KEY UPDATE fluxo_id = ?, etapa_id = NULL`,
      [conversaId, fluxo.id, fluxo.id]
    );

    // Envia menu principal (etapa raiz do tipo menu)
    const etapaMenu = etapasRaiz.find(e => e.tipo === 'menu') || etapasRaiz[0];
    await enviarMensagemBot(conversa, etapaMenu.mensagem, whatsappCliente, io);
    await db.query(
      'UPDATE chat_chatbot_estados SET etapa_id = ? WHERE conversa_id = ?',
      [etapaMenu.id, conversaId]
    );
    return;
  }

  // Tem estado → processa resposta na etapa atual
  const [etapasFilhas] = await db.query(
    `SELECT * FROM chat_chatbot_etapas
     WHERE etapa_pai_id = ?
     ORDER BY ordem ASC`,
    [estado.etapa_id]
  );

  // Encontra etapa correspondente ao que o usuário digitou
  const etapaEscolhida = etapasFilhas.find(e =>
    e.gatilho && e.gatilho.toLowerCase() === textoNorm
  );

  if (!etapaEscolhida) {
    // Opção inválida → reenviar menu atual
    const [etapaAtual] = await db.query(
      'SELECT * FROM chat_chatbot_etapas WHERE id = ?',
      [estado.etapa_id]
    );
    if (etapaAtual.length) {
      await enviarMensagemBot(conversa,
        '❌ Opção inválida.\n\n' + etapaAtual[0].mensagem,
        whatsappCliente, io);
    }
    return;
  }

  // Executa etapa escolhida
  switch (etapaEscolhida.tipo) {
    case 'menu':
      await enviarMensagemBot(conversa, etapaEscolhida.mensagem, whatsappCliente, io);
      await db.query(
        'UPDATE chat_chatbot_estados SET etapa_id = ? WHERE conversa_id = ?',
        [etapaEscolhida.id, conversaId]
      );
      break;

    case 'resposta':
      await enviarMensagemBot(conversa, etapaEscolhida.mensagem, whatsappCliente, io);
      // Volta para etapa pai ou encerra chatbot
      const [pai] = await db.query(
        'SELECT etapa_pai_id FROM chat_chatbot_etapas WHERE id = ?',
        [etapaEscolhida.id]
      );
      const paiId = pai[0]?.etapa_pai_id || null;
      await db.query(
        'UPDATE chat_chatbot_estados SET etapa_id = ? WHERE conversa_id = ?',
        [paiId, conversaId]
      );
      break;

    case 'transferir':
      await enviarMensagemBot(conversa, etapaEscolhida.mensagem, whatsappCliente, io);
      await transferirParaDepartamento(
        conversa,
        etapaEscolhida.transferir_para_departamento_id,
        io
      );
      break;

    case 'encerrar':
      await enviarMensagemBot(conversa, etapaEscolhida.mensagem, whatsappCliente, io);
      await db.query(
        `UPDATE chat_conversas SET status = 'resolvido', chatbot_ativo = 0, fechado_em = NOW()
         WHERE id = ?`,
        [conversaId]
      );
      await db.query('DELETE FROM chat_chatbot_estados WHERE conversa_id = ?', [conversaId]);
      if (io) io.emit('conversa_encerrada', { conversa_id: conversaId });
      break;
  }
}

async function verificarRespostaAutomatica(conversa, textoNorm, whatsappCliente, io) {
  const [respostas] = await db.query(`
    SELECT * FROM chat_respostas_automaticas
    WHERE ativo = 1
      AND (sessao_id = ? OR sessao_id IS NULL)
    ORDER BY sessao_id DESC
  `, [conversa.sessao_id]);

  for (const r of respostas) {
    const palavra = (r.palavra || '').toLowerCase();
    const match   = r.exato ? textoNorm === palavra : textoNorm.includes(palavra);
    if (match) {
      await enviarMensagemBot(conversa, r.resposta, whatsappCliente, io);
      return true;
    }
  }
  return false;
}

async function getFluxoAtivo(sessaoId) {
  const [rows] = await db.query(`
    SELECT * FROM chat_chatbot_fluxos
    WHERE ativo = 1
      AND (sessao_id = ? OR sessao_id IS NULL)
    ORDER BY sessao_id DESC, ordem ASC
    LIMIT 1
  `, [sessaoId]);
  return rows[0] || null;
}

async function colocarNaFila(conversa, io) {
  await db.query(
    `UPDATE chat_conversas SET chatbot_ativo = 0, status = 'aguardando' WHERE id = ?`,
    [conversa.id]
  );
  await entrarNaFila(conversa.id, conversa.departamento_id);
  await distribuirAutomaticamente(conversa.id, conversa.departamento_id, io);
  if (io) io.emit('fila_atualizada', { departamento_id: conversa.departamento_id });
}

async function transferirParaDepartamento(conversa, departamentoId, io) {
  await db.query(
    `UPDATE chat_conversas
     SET departamento_id = ?, atendente_id = NULL, status = 'aguardando', chatbot_ativo = 0
     WHERE id = ?`,
    [departamentoId, conversa.id]
  );
  await db.query('DELETE FROM chat_chatbot_estados WHERE conversa_id = ?', [conversa.id]);
  await entrarNaFila(conversa.id, departamentoId);
  await distribuirAutomaticamente(conversa.id, departamentoId, io);
  if (io) io.emit('fila_atualizada', { departamento_id: departamentoId });
}

async function enviarMensagemBot(conversa, texto, whatsappCliente, io) {
  try {
    if (whatsappCliente) {
      await whatsappCliente.sendMessage(conversa.whatsapp_id, texto);
    }

    const [res] = await db.query(
      `INSERT INTO chat_mensagens
         (conversa_id, tipo, direcao, conteudo, is_bot)
       VALUES (?, 'texto', 'saida', ?, 1)`,
      [conversa.id, texto]
    );

    await db.query(
      `UPDATE chat_conversas
       SET ultima_mensagem = ?, ultima_mensagem_em = NOW()
       WHERE id = ?`,
      [texto.substring(0, 255), conversa.id]
    );

    if (io) {
      io.emit('nova_mensagem', {
        conversa_id: conversa.id,
        id: res.insertId,
        tipo: 'texto',
        direcao: 'saida',
        conteudo: texto,
        is_bot: 1,
        criado_em: new Date(),
      });
    }
  } catch (err) {
    console.error('[Chatbot] Erro ao enviar mensagem:', err.message);
  }
}

module.exports = { processarMensagem, dentroDHorario, colocarNaFila };
