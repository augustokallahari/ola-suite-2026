#!/bin/bash
# ============================================================
#  OlaSuite — Script de deploy para produção
#  Servidor: Linux com XAMPP/LAMPP
#  URL: https://www.kallahari.com.br/olasuite
# ============================================================

set -e
PROJETO_DIR="/opt/lampp/htdocs/olasuite"
BACKEND_DIR="$PROJETO_DIR/backend"

echo "=== OlaSuite Deploy ==="

# ─────────────────────────────────────────────
# PASSO 1: Gerar o hash bcrypt da senha admin
# ─────────────────────────────────────────────
echo ""
echo "[1/7] Gerando hash bcrypt da senha do administrador..."
HASH=$(node -e "require('bcrypt').hash('52473400', 12).then(h => console.log(h))")
echo "Hash gerado: $HASH"

# Substitui o placeholder no SQL de produção
sed -i "s|HASH_BCRYPT_AQUI|$HASH|g" "$PROJETO_DIR/database/setup_producao.sql"

# ─────────────────────────────────────────────
# PASSO 2: Banco de dados
# ─────────────────────────────────────────────
echo ""
echo "[2/7] Importando banco de dados..."
echo "  → Execute manualmente como root do MySQL:"
echo "     mysql -u root -p < $PROJETO_DIR/database/schema.sql"
echo "     mysql -u root -p < $PROJETO_DIR/database/setup_producao.sql"

# ─────────────────────────────────────────────
# PASSO 3: Permissões de diretórios
# ─────────────────────────────────────────────
echo ""
echo "[3/7] Ajustando permissões..."
mkdir -p "$PROJETO_DIR/frontend/assets/uploads"
mkdir -p "$BACKEND_DIR/sessions"
mkdir -p "$BACKEND_DIR/logs"
chmod 775 "$PROJETO_DIR/frontend/assets/uploads"
chmod 750 "$BACKEND_DIR/sessions"
chmod 750 "$BACKEND_DIR/logs"
chown -R daemon:daemon "$PROJETO_DIR/frontend/assets/uploads" 2>/dev/null || true

# ─────────────────────────────────────────────
# PASSO 4: Dependências do Node.js
# ─────────────────────────────────────────────
echo ""
echo "[4/7] Instalando dependências Node.js..."
cd "$BACKEND_DIR"
npm install --production

# ─────────────────────────────────────────────
# PASSO 5: PM2 — gerenciador de processos
# ─────────────────────────────────────────────
echo ""
echo "[5/7] Configurando PM2..."
if ! command -v pm2 &>/dev/null; then
    npm install -g pm2
fi
pm2 delete olasuite-backend 2>/dev/null || true
pm2 start ecosystem.config.js
pm2 save
pm2 startup systemd -u root --hp /root 2>/dev/null || true

# ─────────────────────────────────────────────
# PASSO 6: Configuração Apache (VirtualHost / .htaccess)
# ─────────────────────────────────────────────
echo ""
echo "[6/7] Configuração Apache..."
cat > "$PROJETO_DIR/frontend/.htaccess" << 'HTACCESS'
Options -Indexes
DirectoryIndex index.php login.php

# Bloqueia acesso direto a includes
<FilesMatch "^(config|db|auth|api|header|sidebar)\.php$">
    Require all denied
</FilesMatch>

# Protege arquivos sensíveis
<FilesMatch "\.(env|log|json|lock)$">
    Require all denied
</FilesMatch>

# Headers de segurança
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Redireciona / para login
RewriteEngine On
RewriteRule ^$ login.php [R=301,L]
HTACCESS

# ─────────────────────────────────────────────
# PASSO 7: Proxy reverso Node.js (Socket.IO via HTTPS)
# ─────────────────────────────────────────────
echo ""
echo "[7/7] Configuração de proxy reverso para Socket.IO..."
echo ""
echo "  → Adicione ao seu VirtualHost HTTPS no Apache:"
echo ""
cat << 'APACHE_PROXY'
    # Proxy para Node.js (Socket.IO + API REST)
    ProxyRequests Off
    ProxyPreserveHost On

    # WebSocket / Socket.IO
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule /socket.io/(.*) ws://127.0.0.1:3000/socket.io/$1 [P,L]
    RewriteRule /socket.io/(.*) http://127.0.0.1:3000/socket.io/$1 [P,L]

    ProxyPass        /node/ http://127.0.0.1:3000/
    ProxyPassReverse /node/ http://127.0.0.1:3000/
APACHE_PROXY

echo ""
echo "=== Deploy concluído! ==="
echo ""
echo "Acesse: https://www.kallahari.com.br/olasuite/frontend/login.php"
echo "Login:  augusto@kallahari.com"
echo "Senha:  52473400"
echo ""
echo "IMPORTANTE: Troque a senha no primeiro acesso (Admin → Usuários)"
