const router = require('express').Router();
const db     = require('../database');

// Resumo geral
router.get('/resumo', async (req, res) => {
  const { data_inicio, data_fim } = req.query;
  const di = data_inicio || new Date(Date.now() - 30 * 864e5).toISOString().split('T')[0];
  const df = data_fim    || new Date().toISOString().split('T')[0];

  const [[totais]] = await db.query(`
    SELECT
      COUNT(*) AS total_atendimentos,
      SUM(status = 'resolvido')      AS resolvidos,
      SUM(status = 'em_atendimento') AS em_andamento,
      SUM(status = 'aguardando')     AS aguardando,
      SUM(status = 'abandonado')     AS abandonados,
      AVG(TIMESTAMPDIFF(MINUTE, aberto_em, IFNULL(fechado_em, NOW()))) AS tempo_medio_minutos
    FROM chat_conversas
    WHERE DATE(aberto_em) BETWEEN ? AND ?
  `, [di, df]);

  const [[{ total_mensagens }]] = await db.query(`
    SELECT COUNT(*) AS total_mensagens
    FROM chat_mensagens m
    JOIN chat_conversas c ON c.id = m.conversa_id
    WHERE DATE(c.aberto_em) BETWEEN ? AND ?
  `, [di, df]);

  res.json({ sucesso: true, dados: { ...totais, total_mensagens, data_inicio: di, data_fim: df } });
});

// Atendimentos por agente
router.get('/por-agente', async (req, res) => {
  const { data_inicio, data_fim } = req.query;
  const di = data_inicio || new Date(Date.now() - 30 * 864e5).toISOString().split('T')[0];
  const df = data_fim    || new Date().toISOString().split('T')[0];

  const [rows] = await db.query(`
    SELECT u.id, u.nome,
           COUNT(c.id)  AS total,
           SUM(c.status = 'resolvido') AS resolvidos,
           AVG(TIMESTAMPDIFF(MINUTE, c.aberto_em, IFNULL(c.fechado_em, NOW()))) AS tempo_medio_min
    FROM chat_usuarios u
    LEFT JOIN chat_conversas c
      ON c.atendente_id = u.id AND DATE(c.aberto_em) BETWEEN ? AND ?
    WHERE u.ativo = 1 AND u.nivel IN ('atendente','supervisor')
    GROUP BY u.id
    ORDER BY total DESC
  `, [di, df]);

  res.json({ sucesso: true, dados: rows });
});

// Atendimentos por departamento
router.get('/por-departamento', async (req, res) => {
  const { data_inicio, data_fim } = req.query;
  const di = data_inicio || new Date(Date.now() - 30 * 864e5).toISOString().split('T')[0];
  const df = data_fim    || new Date().toISOString().split('T')[0];

  const [rows] = await db.query(`
    SELECT d.id, d.nome, d.cor,
           COUNT(c.id) AS total,
           SUM(c.status = 'resolvido') AS resolvidos
    FROM chat_departamentos d
    LEFT JOIN chat_conversas c
      ON c.departamento_id = d.id AND DATE(c.aberto_em) BETWEEN ? AND ?
    WHERE d.ativo = 1
    GROUP BY d.id
    ORDER BY total DESC
  `, [di, df]);

  res.json({ sucesso: true, dados: rows });
});

// Atendimentos por dia (gráfico de linha)
router.get('/por-dia', async (req, res) => {
  const { data_inicio, data_fim } = req.query;
  const di = data_inicio || new Date(Date.now() - 30 * 864e5).toISOString().split('T')[0];
  const df = data_fim    || new Date().toISOString().split('T')[0];

  const [rows] = await db.query(`
    SELECT DATE(aberto_em) AS dia, COUNT(*) AS total
    FROM chat_conversas
    WHERE DATE(aberto_em) BETWEEN ? AND ?
    GROUP BY dia
    ORDER BY dia ASC
  `, [di, df]);

  res.json({ sucesso: true, dados: rows });
});

// Tempo de primeira resposta médio (bot excluído)
router.get('/tempo-resposta', async (req, res) => {
  const { data_inicio, data_fim } = req.query;
  const di = data_inicio || new Date(Date.now() - 30 * 864e5).toISOString().split('T')[0];
  const df = data_fim    || new Date().toISOString().split('T')[0];

  const [rows] = await db.query(`
    SELECT
      u.nome AS atendente,
      AVG(TIMESTAMPDIFF(MINUTE, c.aberto_em, primeira.criado_em)) AS min_primeira_resposta
    FROM chat_conversas c
    JOIN chat_usuarios u ON u.id = c.atendente_id
    JOIN (
      SELECT conversa_id, MIN(criado_em) AS criado_em
      FROM chat_mensagens
      WHERE direcao = 'saida' AND is_bot = 0
      GROUP BY conversa_id
    ) primeira ON primeira.conversa_id = c.id
    WHERE DATE(c.aberto_em) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY min_primeira_resposta ASC
  `, [di, df]);

  res.json({ sucesso: true, dados: rows });
});

module.exports = router;
