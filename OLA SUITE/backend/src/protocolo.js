const db = require('./database');

async function gerarProtocolo() {
  const prefixo = await getConfig('protocolo_prefixo') || 'OLA';
  const data    = new Date();
  const ano     = data.getFullYear();
  const mes     = String(data.getMonth() + 1).padStart(2, '0');
  const dia     = String(data.getDate()).padStart(2, '0');
  const rand    = Math.floor(Math.random() * 900000) + 100000;
  return `${prefixo}${ano}${mes}${dia}${rand}`;
}

async function getConfig(chave) {
  try {
    const [rows] = await db.query('SELECT valor FROM chat_configuracoes WHERE chave = ?', [chave]);
    return rows.length ? rows[0].valor : null;
  } catch {
    return null;
  }
}

module.exports = { gerarProtocolo, getConfig };
