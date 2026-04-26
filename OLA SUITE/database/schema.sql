-- ============================================================
--  OlaSuite — Schema completo do banco de dados
--  Banco: chat-kallahari
--  Prefixo de tabelas: chat_
--  Encoding: utf8mb4 (suporte a emojis)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `chat-kallahari`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `chat-kallahari`;

-- ============================================================
-- 1. USUÁRIOS / ATENDENTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_usuarios` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`              VARCHAR(120)  NOT NULL,
  `email`             VARCHAR(180)  NOT NULL,
  `senha`             VARCHAR(255)  NOT NULL COMMENT 'bcrypt hash',
  `nivel`             ENUM('admin','supervisor','atendente') NOT NULL DEFAULT 'atendente',
  `departamento_id`   INT UNSIGNED DEFAULT NULL,
  `avatar`            VARCHAR(255)  DEFAULT NULL,
  `status`            ENUM('online','ausente','offline') NOT NULL DEFAULT 'offline',
  `ativo`             TINYINT(1)    NOT NULL DEFAULT 1,
  `ultimo_acesso`     DATETIME      DEFAULT NULL,
  `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_nivel` (`nivel`),
  KEY `idx_departamento` (`departamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário administrador padrão (senha: admin123 — trocar no primeiro acesso)
INSERT INTO `chat_usuarios` (`nome`, `email`, `senha`, `nivel`, `ativo`) VALUES
('Administrador', 'admin@olasuite.local', '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMqJqhujGBRUBP5c0HqZKPU3qi', 'admin', 1);

-- ============================================================
-- 2. DEPARTAMENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_departamentos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(100) NOT NULL,
  `descricao`   VARCHAR(255) DEFAULT NULL,
  `cor`         VARCHAR(7)   NOT NULL DEFAULT '#3b82f6' COMMENT 'hex color',
  `ativo`       TINYINT(1)   NOT NULL DEFAULT 1,
  `criado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chat_departamentos` (`nome`, `descricao`, `cor`) VALUES
('Suporte',    'Atendimento técnico',       '#3b82f6'),
('Vendas',     'Comercial e orçamentos',    '#10b981'),
('Financeiro', 'Cobranças e pagamentos',    '#f59e0b');

-- FK: usuários → departamentos
ALTER TABLE `chat_usuarios`
  ADD CONSTRAINT `fk_usuario_departamento`
  FOREIGN KEY (`departamento_id`) REFERENCES `chat_departamentos` (`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================
-- 3. SESSÕES WHATSAPP
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_sessoes_whatsapp` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`            VARCHAR(100) NOT NULL COMMENT 'Nome/apelido da sessão',
  `numero`          VARCHAR(30)  DEFAULT NULL COMMENT 'Número conectado (ex: 5511999999999)',
  `status`          ENUM('desconectado','aguardando_qr','conectado','erro') NOT NULL DEFAULT 'desconectado',
  `session_id`      VARCHAR(80)  NOT NULL UNIQUE COMMENT 'ID usado no whatsapp-web.js',
  `qr_code`         TEXT         DEFAULT NULL COMMENT 'Base64 do QR Code atual',
  `departamento_id` INT UNSIGNED DEFAULT NULL,
  `ativo`           TINYINT(1)   NOT NULL DEFAULT 1,
  `criado_em`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_sessao_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `chat_departamentos` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CONTATOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_contatos` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `whatsapp_id`   VARCHAR(50)  NOT NULL UNIQUE COMMENT 'ex: 5511999999999@c.us',
  `numero`        VARCHAR(30)  NOT NULL,
  `nome`          VARCHAR(120) DEFAULT NULL,
  `nome_push`     VARCHAR(120) DEFAULT NULL COMMENT 'Nome exibido no WhatsApp',
  `email`         VARCHAR(180) DEFAULT NULL,
  `tags`          VARCHAR(255) DEFAULT NULL COMMENT 'CSV de tags',
  `observacoes`   TEXT         DEFAULT NULL,
  `bloqueado`     TINYINT(1)   NOT NULL DEFAULT 0,
  `criado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_numero` (`numero`),
  KEY `idx_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. CONVERSAS / TICKETS
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_conversas` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contato_id`        INT UNSIGNED NOT NULL,
  `sessao_id`         INT UNSIGNED NOT NULL,
  `departamento_id`   INT UNSIGNED DEFAULT NULL,
  `atendente_id`      INT UNSIGNED DEFAULT NULL,
  `protocolo`         VARCHAR(30)  DEFAULT NULL COMMENT 'Número de protocolo gerado',
  `status`            ENUM('aguardando','em_atendimento','resolvido','abandonado') NOT NULL DEFAULT 'aguardando',
  `origem`            ENUM('whatsapp','interno') NOT NULL DEFAULT 'whatsapp',
  `chatbot_ativo`     TINYINT(1)   NOT NULL DEFAULT 1,
  `ultima_mensagem`   TEXT         DEFAULT NULL,
  `ultima_mensagem_em` DATETIME    DEFAULT NULL,
  `nao_lidas`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `aberto_em`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fechado_em`        DATETIME     DEFAULT NULL,
  `atualizado_em`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_protocolo` (`protocolo`),
  KEY `idx_status` (`status`),
  KEY `idx_contato` (`contato_id`),
  KEY `idx_atendente` (`atendente_id`),
  KEY `idx_departamento` (`departamento_id`),
  KEY `idx_sessao` (`sessao_id`),
  CONSTRAINT `fk_conversa_contato`
    FOREIGN KEY (`contato_id`) REFERENCES `chat_contatos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_conversa_sessao`
    FOREIGN KEY (`sessao_id`) REFERENCES `chat_sessoes_whatsapp` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_conversa_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `chat_departamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_conversa_atendente`
    FOREIGN KEY (`atendente_id`) REFERENCES `chat_usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. MENSAGENS
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_mensagens` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversa_id`     INT UNSIGNED    NOT NULL,
  `whatsapp_msg_id` VARCHAR(100)    DEFAULT NULL UNIQUE COMMENT 'ID da mensagem no WhatsApp',
  `tipo`            ENUM('texto','imagem','audio','video','documento','localizacao','contato','sticker','sistema') NOT NULL DEFAULT 'texto',
  `direcao`         ENUM('entrada','saida') NOT NULL,
  `conteudo`        TEXT            DEFAULT NULL,
  `midia_url`       VARCHAR(500)    DEFAULT NULL,
  `midia_nome`      VARCHAR(255)    DEFAULT NULL,
  `midia_tamanho`   INT UNSIGNED    DEFAULT NULL COMMENT 'bytes',
  `midia_mime`      VARCHAR(100)    DEFAULT NULL,
  `enviado_por_id`  INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = enviado pelo contato ou chatbot',
  `status`          ENUM('enviando','enviado','entregue','lido','erro') DEFAULT NULL,
  `is_bot`          TINYINT(1)      NOT NULL DEFAULT 0,
  `lida`            TINYINT(1)      NOT NULL DEFAULT 0,
  `criado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversa` (`conversa_id`),
  KEY `idx_criado_em` (`criado_em`),
  KEY `idx_direcao` (`direcao`),
  CONSTRAINT `fk_mensagem_conversa`
    FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mensagem_usuario`
    FOREIGN KEY (`enviado_por_id`) REFERENCES `chat_usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. FILA DE ATENDIMENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_fila` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversa_id`     INT UNSIGNED NOT NULL,
  `departamento_id` INT UNSIGNED DEFAULT NULL,
  `posicao`         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `prioridade`      TINYINT UNSIGNED  NOT NULL DEFAULT 5 COMMENT '1=mais alta, 10=mais baixa',
  `entrada_em`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversa_fila` (`conversa_id`),
  KEY `idx_departamento_fila` (`departamento_id`),
  CONSTRAINT `fk_fila_conversa`
    FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fila_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `chat_departamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. HISTÓRICO DE TRANSFERÊNCIAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_transferencias` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversa_id`           INT UNSIGNED NOT NULL,
  `de_atendente_id`       INT UNSIGNED DEFAULT NULL,
  `para_atendente_id`     INT UNSIGNED DEFAULT NULL,
  `de_departamento_id`    INT UNSIGNED DEFAULT NULL,
  `para_departamento_id`  INT UNSIGNED DEFAULT NULL,
  `motivo`                TEXT         DEFAULT NULL,
  `transferido_por_id`    INT UNSIGNED DEFAULT NULL,
  `criado_em`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transferencia_conversa` (`conversa_id`),
  CONSTRAINT `fk_transf_conversa`
    FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. FLUXOS DE CHATBOT (menus/etapas)
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_chatbot_fluxos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`            VARCHAR(120) NOT NULL,
  `sessao_id`       INT UNSIGNED DEFAULT NULL COMMENT 'NULL = aplicado a todas as sessões',
  `departamento_id` INT UNSIGNED DEFAULT NULL COMMENT 'Encaminha para este departamento ao final',
  `ativo`           TINYINT(1)   NOT NULL DEFAULT 1,
  `ordem`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessao_fluxo` (`sessao_id`),
  CONSTRAINT `fk_fluxo_sessao`
    FOREIGN KEY (`sessao_id`) REFERENCES `chat_sessoes_whatsapp` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_fluxo_departamento`
    FOREIGN KEY (`departamento_id`) REFERENCES `chat_departamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. ETAPAS DO CHATBOT
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_chatbot_etapas` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fluxo_id`      INT UNSIGNED NOT NULL,
  `etapa_pai_id`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL = etapa raiz (menu principal)',
  `gatilho`       VARCHAR(50)  DEFAULT NULL COMMENT 'Texto/número que ativa esta etapa',
  `mensagem`      TEXT         NOT NULL COMMENT 'Resposta enviada ao cliente',
  `tipo`          ENUM('menu','resposta','transferir','encerrar') NOT NULL DEFAULT 'resposta',
  `transferir_para_departamento_id` INT UNSIGNED DEFAULT NULL,
  `ordem`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `criado_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fluxo_etapa` (`fluxo_id`),
  KEY `idx_etapa_pai` (`etapa_pai_id`),
  CONSTRAINT `fk_etapa_fluxo`
    FOREIGN KEY (`fluxo_id`) REFERENCES `chat_chatbot_fluxos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_etapa_pai`
    FOREIGN KEY (`etapa_pai_id`) REFERENCES `chat_chatbot_etapas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_etapa_departamento`
    FOREIGN KEY (`transferir_para_departamento_id`) REFERENCES `chat_departamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. RESPOSTAS AUTOMÁTICAS POR PALAVRA-CHAVE
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_respostas_automaticas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sessao_id`   INT UNSIGNED DEFAULT NULL COMMENT 'NULL = todas as sessões',
  `palavra`     VARCHAR(200) NOT NULL COMMENT 'Palavra ou frase a detectar (case-insensitive)',
  `resposta`    TEXT         NOT NULL,
  `exato`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = match exato, 0 = contém',
  `ativo`       TINYINT(1)   NOT NULL DEFAULT 1,
  `criado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessao_resp` (`sessao_id`),
  CONSTRAINT `fk_resp_sessao`
    FOREIGN KEY (`sessao_id`) REFERENCES `chat_sessoes_whatsapp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. HORÁRIOS DE ATENDIMENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_horarios_atendimento` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sessao_id`       INT UNSIGNED DEFAULT NULL COMMENT 'NULL = padrão global',
  `dia_semana`      TINYINT UNSIGNED NOT NULL COMMENT '0=Dom,1=Seg,...,6=Sab',
  `hora_inicio`     TIME         NOT NULL,
  `hora_fim`        TIME         NOT NULL,
  `ativo`           TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_sessao_horario` (`sessao_id`),
  CONSTRAINT `fk_horario_sessao`
    FOREIGN KEY (`sessao_id`) REFERENCES `chat_sessoes_whatsapp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horário padrão: seg-sex 08:00-18:00
INSERT INTO `chat_horarios_atendimento` (`sessao_id`, `dia_semana`, `hora_inicio`, `hora_fim`) VALUES
(NULL, 1, '08:00:00', '18:00:00'),
(NULL, 2, '08:00:00', '18:00:00'),
(NULL, 3, '08:00:00', '18:00:00'),
(NULL, 4, '08:00:00', '18:00:00'),
(NULL, 5, '08:00:00', '18:00:00');

-- ============================================================
-- 13. MENSAGEM FORA DO HORÁRIO
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_mensagem_fora_horario` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sessao_id` INT UNSIGNED DEFAULT NULL,
  `mensagem`  TEXT         NOT NULL,
  `ativo`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_fora_horario_sessao`
    FOREIGN KEY (`sessao_id`) REFERENCES `chat_sessoes_whatsapp` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chat_mensagem_fora_horario` (`sessao_id`, `mensagem`) VALUES
(NULL, 'Olá! No momento estamos fora do horário de atendimento. Nosso horário é de segunda a sexta, das 08h às 18h. Deixe sua mensagem e retornaremos em breve!');

-- ============================================================
-- 14. CONFIGURAÇÕES GERAIS DO SISTEMA
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_configuracoes` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(100) NOT NULL UNIQUE,
  `valor` TEXT         DEFAULT NULL,
  `grupo` VARCHAR(60)  NOT NULL DEFAULT 'geral',
  PRIMARY KEY (`id`),
  KEY `idx_grupo` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chat_configuracoes` (`chave`, `valor`, `grupo`) VALUES
('nome_empresa',              'OlaSuite',               'geral'),
('logo_url',                  NULL,                     'geral'),
('fuso_horario',              'America/Sao_Paulo',      'geral'),
('protocolo_prefixo',         'OLA',                    'geral'),
('max_atendimentos_agente',   '10',                     'atendimento'),
('distribuicao_automatica',   '1',                      'atendimento'),
('tempo_ociosidade_minutos',  '30',                     'atendimento'),
('som_notificacao_ativo',     '1',                      'notificacao'),
('node_api_url',              'http://localhost:3000',  'integracao');

-- ============================================================
-- 15. NOTIFICAÇÕES
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_notificacoes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `tipo`        ENUM('mensagem','transferencia','fila','sistema') NOT NULL DEFAULT 'mensagem',
  `titulo`      VARCHAR(150) NOT NULL,
  `corpo`       TEXT         DEFAULT NULL,
  `conversa_id` INT UNSIGNED DEFAULT NULL,
  `lida`        TINYINT(1)   NOT NULL DEFAULT 0,
  `criado_em`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_usuario` (`usuario_id`),
  KEY `idx_notif_lida` (`lida`),
  CONSTRAINT `fk_notif_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `chat_usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. LOGS DE AUDITORIA
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED    DEFAULT NULL,
  `acao`        VARCHAR(100)    NOT NULL,
  `tabela`      VARCHAR(60)     DEFAULT NULL,
  `registro_id` INT UNSIGNED    DEFAULT NULL,
  `detalhe`     TEXT            DEFAULT NULL,
  `ip`          VARCHAR(45)     DEFAULT NULL,
  `criado_em`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_usuario` (`usuario_id`),
  KEY `idx_log_acao` (`acao`),
  KEY `idx_log_data` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. ESTADOS DO CHATBOT POR CONVERSA (rastreia etapa atual)
-- ============================================================
CREATE TABLE IF NOT EXISTS `chat_chatbot_estados` (
  `conversa_id`   INT UNSIGNED NOT NULL,
  `fluxo_id`      INT UNSIGNED DEFAULT NULL,
  `etapa_id`      INT UNSIGNED DEFAULT NULL,
  `aguardando`    VARCHAR(60)  DEFAULT NULL COMMENT 'campo esperado do usuário (nome, email...)',
  `dados_temp`    JSON         DEFAULT NULL COMMENT 'dados coletados temporariamente',
  `atualizado_em` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversa_id`),
  CONSTRAINT `fk_estado_conversa`
    FOREIGN KEY (`conversa_id`) REFERENCES `chat_conversas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_estado_fluxo`
    FOREIGN KEY (`fluxo_id`) REFERENCES `chat_chatbot_fluxos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_estado_etapa`
    FOREIGN KEY (`etapa_id`) REFERENCES `chat_chatbot_etapas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIM DO SCHEMA
-- ============================================================
