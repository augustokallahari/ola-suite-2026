const db = require('./database');

// Adiciona conversa na fila do departamento
async function entrarNaFila(conversaId, departamentoId = null) {
  const [existente] = await db.query('SELECT id FROM chat_fila WHERE conversa_id = ?', [conversaId]);
  if (existente.length) return;

  const [filaDept] = await db.query(
    'SELECT MAX(posicao) AS max_pos FROM chat_fila WHERE departamento_id <=> ?',
    [departamentoId]
  );
  const posicao = (filaDept[0].max_pos || 0) + 1;

  await db.query(
    'INSERT INTO chat_fila (conversa_id, departamento_id, posicao) VALUES (?, ?, ?)',
    [conversaId, departamentoId, posicao]
  );
}

// Remove conversa da fila (ao ser assumida)
async function sairDaFila(conversaId) {
  await db.query('DELETE FROM chat_fila WHERE conversa_id = ?', [conversaId]);
}

// Distribui automaticamente para o atendente com menos atendimentos no departamento
async function distribuirAutomaticamente(conversaId, departamentoId, io) {
  const [cfg] = await db.query(
    'SELECT valor FROM chat_configuracoes WHERE chave = ?',
    ['distribuicao_automatica']
  );
  if (!cfg.length || cfg[0].valor !== '1') return;

  const [maxCfg] = await db.query(
    'SELECT valor FROM chat_configuracoes WHERE chave = ?',
    ['max_atendimentos_agente']
  );
  const maxAtendimentos = parseInt(maxCfg[0]?.valor || '10');

  // Busca atendente online do departamento com menor carga
  const [atendentes] = await db.query(`
    SELECT u.id,
           COUNT(c.id) AS em_atendimento
    FROM chat_usuarios u
    LEFT JOIN chat_conversas c
      ON c.atendente_id = u.id AND c.status = 'em_atendimento'
    WHERE u.ativo = 1
      AND u.status = 'online'
      AND (u.departamento_id = ? OR ? IS NULL)
    GROUP BY u.id
    HAVING em_atendimento < ?
    ORDER BY em_atendimento ASC
    LIMIT 1
  `, [departamentoId, departamentoId, maxAtendimentos]);

  if (!atendentes.length) return;

  const atendenteId = atendentes[0].id;
  await db.query(
    `UPDATE chat_conversas
     SET atendente_id = ?, status = 'em_atendimento', chatbot_ativo = 0
     WHERE id = ?`,
    [atendenteId, conversaId]
  );
  await sairDaFila(conversaId);

  if (io) {
    io.emit('conversa_distribuida', { conversa_id: conversaId, atendente_id: atendenteId });
  }
}

module.exports = { entrarNaFila, sairDaFila, distribuirAutomaticamente };
