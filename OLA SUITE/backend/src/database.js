require('dotenv').config({ path: require('path').resolve(__dirname, '../.env') });
const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host:             process.env.DB_HOST || 'localhost',
  user:             process.env.DB_USER || 'root',
  password:         process.env.DB_PASS || '',
  database:         process.env.DB_NAME || 'chat-kallahari',
  charset:          'utf8mb4',
  waitForConnections: true,
  connectionLimit:  20,
  queueLimit:       0,
  timezone:         '-03:00',
  dateStrings:      false,
});

pool.getConnection()
  .then(conn => { console.log('[DB] MySQL conectado com sucesso.'); conn.release(); })
  .catch(err => { console.error('[DB] Falha ao conectar:', err.message); process.exit(1); });

module.exports = pool;
