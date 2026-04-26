-- ============================================================
--  OlaSuite — Script de configuração de PRODUÇÃO
--  Executar APÓS importar o schema.sql principal
-- ============================================================

USE `chat-kallahari`;

-- 1. Cria usuário MySQL dedicado para a aplicação
--    (execute como root do MySQL)
CREATE USER IF NOT EXISTS 'olasuite'@'localhost' IDENTIFIED BY 'OlaSuite@2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON `chat-kallahari`.* TO 'olasuite'@'localhost';
FLUSH PRIVILEGES;

-- 2. Remove o admin genérico inserido pelo schema.sql
DELETE FROM `chat_usuarios` WHERE `email` = 'admin@olasuite.local';

-- 3. Insere o administrador de produção
--    Senha: 52473400  (hash bcrypt gerado com: node -e "require('bcrypt').hash('52473400',12).then(h=>console.log(h))")
--    IMPORTANTE: substitua o hash abaixo pelo gerado no passo do deploy
INSERT INTO `chat_usuarios` (`nome`, `email`, `senha`, `nivel`, `ativo`) VALUES
('Augusto', 'augusto@kallahari.com', 'HASH_BCRYPT_AQUI', 'admin', 1);

-- 4. Atualiza configurações de sistema para produção
UPDATE `chat_configuracoes` SET `valor` = 'OlaSuite' WHERE `chave` = 'nome_empresa';
UPDATE `chat_configuracoes` SET `valor` = 'https://www.kallahari.com.br/olasuite/frontend' WHERE `chave` = 'url_sistema';
