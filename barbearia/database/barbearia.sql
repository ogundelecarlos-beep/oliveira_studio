-- =====================================================================
--  Navalha & Co. Barbearia — Banco de dados
--  Compatível com MySQL 5.7+ / 8.x e MariaDB 10.3+
--  Charset: utf8mb4
--
--  Todos os dados abaixo são FICTÍCIOS e servem apenas para demonstração.
--  As datas dos agendamentos são relativas ao dia da importação (CURDATE()).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS barbearia
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE barbearia;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS login_tentativas;
DROP TABLE IF EXISTS recuperacao_senha;
DROP TABLE IF EXISTS mensagens;
DROP TABLE IF EXISTS avaliacoes;
DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS bloqueios;
DROP TABLE IF EXISTS horarios;
DROP TABLE IF EXISTS promocoes;
DROP TABLE IF EXISTS galeria;
DROP TABLE IF EXISTS servicos;
DROP TABLE IF EXISTS barbeiros;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracoes;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USUARIOS — acesso ao painel administrativo
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome       VARCHAR(120) NOT NULL,
  email      VARCHAR(160) NOT NULL,
  senha      VARCHAR(255) NOT NULL COMMENT 'Hash gerado por password_hash()',
  tipo       ENUM('admin','funcionario') NOT NULL DEFAULT 'funcionario',
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de recuperação de senha (armazenados como hash SHA-256)
CREATE TABLE recuperacao_senha (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expira_em   DATETIME NOT NULL,
  usado       TINYINT(1) NOT NULL DEFAULT 0,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rec_token (token_hash),
  KEY idx_rec_usuario (usuario_id),
  CONSTRAINT fk_rec_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Controle de tentativas de login (proteção contra força bruta)
CREATE TABLE login_tentativas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARCHAR(45) NOT NULL,
  email      VARCHAR(160) NOT NULL,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tent_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CLIENTES
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome           VARCHAR(120) NOT NULL,
  telefone       VARCHAR(20)  NOT NULL COMMENT 'Somente dígitos, com DDD',
  email          VARCHAR(160) NULL,
  data_cadastro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clientes_telefone (telefone),
  KEY idx_clientes_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- BARBEIROS
-- ---------------------------------------------------------------------
CREATE TABLE barbeiros (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome            VARCHAR(120) NOT NULL,
  descricao       TEXT NULL,
  foto            VARCHAR(255) NULL,
  especialidades  VARCHAR(255) NULL COMMENT 'Lista separada por vírgula',
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  ordem           INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SERVICOS
-- ---------------------------------------------------------------------
CREATE TABLE servicos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome       VARCHAR(120) NOT NULL,
  descricao  TEXT NULL,
  preco      DECIMAL(10,2) NOT NULL,
  duracao    SMALLINT UNSIGNED NOT NULL COMMENT 'Em minutos',
  foto       VARCHAR(255) NULL,
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  destaque   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Exibido na home',
  ordem      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- AGENDAMENTOS
--   hora_fim e valor são gravados no momento do agendamento, para que
--   mudanças futuras de duração/preço não alterem o histórico.
--   slot_ativo + índice único garantem, no próprio banco, que um barbeiro
--   nunca tenha dois agendamentos ativos começando no mesmo horário.
-- ---------------------------------------------------------------------
CREATE TABLE agendamentos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id    INT UNSIGNED NOT NULL,
  barbeiro_id   INT UNSIGNED NOT NULL,
  servico_id    INT UNSIGNED NOT NULL,
  data          DATE NOT NULL,
  hora          TIME NOT NULL,
  hora_fim      TIME NOT NULL,
  valor         DECIMAL(10,2) NOT NULL DEFAULT 0,
  status        ENUM('pendente','confirmado','concluido','cancelado') NOT NULL DEFAULT 'pendente',
  observacao    VARCHAR(500) NULL,
  token         CHAR(32) NOT NULL COMMENT 'Usado no link de cancelamento do cliente',
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  slot_ativo    TINYINT AS (IF(status = 'cancelado', NULL, 1)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agendamento_slot (barbeiro_id, data, hora, slot_ativo),
  UNIQUE KEY uq_agendamento_token (token),
  KEY idx_ag_data (data, hora),
  KEY idx_ag_cliente (cliente_id),
  KEY idx_ag_servico (servico_id),
  KEY idx_ag_status (status),
  CONSTRAINT fk_ag_cliente  FOREIGN KEY (cliente_id)  REFERENCES clientes  (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ag_barbeiro FOREIGN KEY (barbeiro_id) REFERENCES barbeiros (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ag_servico  FOREIGN KEY (servico_id)  REFERENCES servicos  (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- HORARIOS
--   barbeiro_id NULL  => horário de funcionamento da barbearia.
--   barbeiro_id = X   => jornada específica do barbeiro (opcional).
--   Um dia pode ter mais de um período (ex.: manhã e tarde).
--   dia_semana: 0 = domingo ... 6 = sábado. Dia sem registro = fechado.
-- ---------------------------------------------------------------------
CREATE TABLE horarios (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  barbeiro_id  INT UNSIGNED NULL,
  dia_semana   TINYINT UNSIGNED NOT NULL,
  hora_inicio  TIME NOT NULL,
  hora_fim     TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_hor_barbeiro (barbeiro_id, dia_semana),
  CONSTRAINT fk_hor_barbeiro FOREIGN KEY (barbeiro_id) REFERENCES barbeiros (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- BLOQUEIOS
--   barbeiro_id NULL          => bloqueio para toda a barbearia.
--   hora_inicio/hora_fim NULL => bloqueio do dia inteiro.
-- ---------------------------------------------------------------------
CREATE TABLE bloqueios (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  barbeiro_id  INT UNSIGNED NULL,
  data         DATE NOT NULL,
  hora_inicio  TIME NULL,
  hora_fim     TIME NULL,
  tipo         ENUM('feriado','folga','manutencao','compromisso','outro') NOT NULL DEFAULT 'outro',
  motivo       VARCHAR(255) NULL,
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bloq_data (data),
  CONSTRAINT fk_bloq_barbeiro FOREIGN KEY (barbeiro_id) REFERENCES barbeiros (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- AVALIACOES — aprovado: 0 = pendente, 1 = aprovada, 2 = oculta
-- ---------------------------------------------------------------------
CREATE TABLE avaliacoes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id      INT UNSIGNED NOT NULL,
  agendamento_id  INT UNSIGNED NULL,
  nota            TINYINT UNSIGNED NOT NULL,
  comentario      TEXT NULL,
  aprovado        TINYINT(1) NOT NULL DEFAULT 0,
  criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_av_cliente (cliente_id),
  KEY idx_av_aprovado (aprovado),
  CONSTRAINT chk_av_nota CHECK (nota BETWEEN 1 AND 5),
  CONSTRAINT fk_av_cliente     FOREIGN KEY (cliente_id)     REFERENCES clientes     (id) ON DELETE CASCADE,
  CONSTRAINT fk_av_agendamento FOREIGN KEY (agendamento_id) REFERENCES agendamentos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PROMOCOES — servico_id (opcional) aplica o preço promocional no agendamento
-- ---------------------------------------------------------------------
CREATE TABLE promocoes (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome               VARCHAR(120) NOT NULL,
  descricao          TEXT NULL,
  servico_id         INT UNSIGNED NULL,
  preco_antigo       DECIMAL(10,2) NOT NULL,
  preco_promocional  DECIMAL(10,2) NOT NULL,
  data_inicio        DATE NOT NULL,
  data_fim           DATE NOT NULL,
  imagem             VARCHAR(255) NULL,
  ativo              TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_promo_periodo (ativo, data_inicio, data_fim),
  CONSTRAINT fk_promo_servico FOREIGN KEY (servico_id) REFERENCES servicos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- GALERIA
-- ---------------------------------------------------------------------
CREATE TABLE galeria (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  imagem     VARCHAR(255) NOT NULL,
  titulo     VARCHAR(120) NULL,
  categoria  ENUM('degrade','social','barba','freestyle','outros') NOT NULL DEFAULT 'outros',
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gal_categoria (categoria, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MENSAGENS — formulário de contato do site
-- ---------------------------------------------------------------------
CREATE TABLE mensagens (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome       VARCHAR(120) NOT NULL,
  email      VARCHAR(160) NOT NULL,
  telefone   VARCHAR(20)  NULL,
  assunto    VARCHAR(150) NULL,
  mensagem   TEXT NOT NULL,
  lida       TINYINT(1) NOT NULL DEFAULT 0,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CONFIGURACOES — registro único (id = 1)
-- ---------------------------------------------------------------------
CREATE TABLE configuracoes (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome_barbearia         VARCHAR(120) NOT NULL,
  slogan                 VARCHAR(160) NULL,
  logo                   VARCHAR(255) NULL,
  endereco               VARCHAR(255) NULL,
  telefone               VARCHAR(20)  NULL,
  whatsapp               VARCHAR(20)  NULL COMMENT 'Somente dígitos, com DDI 55',
  email                  VARCHAR(160) NULL,
  instagram              VARCHAR(80)  NULL COMMENT 'Usuário sem @',
  facebook               VARCHAR(160) NULL,
  tiktok                 VARCHAR(80)  NULL,
  mapa                   TEXT NULL COMMENT 'URL de incorporação (embed) do Google Maps',
  mapa_link              VARCHAR(500) NULL COMMENT 'Link "Como chegar"',
  horario_funcionamento  VARCHAR(255) NULL COMMENT 'Texto livre complementar',
  antecedencia_minima    SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Minutos mínimos antes de um horário',
  dias_antecedencia      SMALLINT UNSIGNED NOT NULL DEFAULT 45 COMMENT 'Até quantos dias à frente o cliente pode agendar',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DADOS DE DEMONSTRAÇÃO (fictícios)
-- =====================================================================

INSERT INTO configuracoes (id, nome_barbearia, slogan, logo, endereco, telefone, whatsapp, email, instagram, facebook, tiktok, mapa, mapa_link, horario_funcionamento, antecedencia_minima, dias_antecedencia) VALUES
(1, 'Navalha & Co.', 'Barbearia de assinatura', NULL,
 'Rua das Palmeiras, 480 — Vila Madalena, São Paulo — SP, 05433-000',
 '(11) 3456-7890', '5511987654321', 'contato@navalhaeco.com.br', 'navalhaeco.barbearia',
 'https://facebook.com/navalhaeco', 'navalhaeco',
 'https://www.google.com/maps?q=Vila+Madalena,+S%C3%A3o+Paulo+-+SP&output=embed',
 'https://www.google.com/maps/dir/?api=1&destination=Vila+Madalena,+S%C3%A3o+Paulo+-+SP',
 'Atendimento com hora marcada. Encaixes sujeitos à disponibilidade.', 30, 45);

-- Senhas: admin123 / barber123 (troque após o primeiro acesso)
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador', 'admin@barbearia.com', '$2y$10$gTu.ZgEVp9Gq3ZLpImQnzuOVAWfVUna6SK87mQLd2sRJqcClochAK', 'admin'),
('Recepção', 'recepcao@barbearia.com', '$2y$10$A47wLNZb3NH6u2UOtBI7E.M/vP4VKkO1E8UmTAlvplKQR4SkdI64K', 'funcionario');

INSERT INTO barbeiros (id, nome, descricao, foto, especialidades, ativo, ordem) VALUES
(1, 'João Silva', 'Fundador da casa, 12 anos de tesoura. Referência em cortes clássicos e acabamento na navalha — do social executivo ao pompadour.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=800&q=80', 'Corte social, Navalha, Barba clássica', 1, 1),
(2, 'Rafael Costa', 'Especialista em degradê e transições limpas. Participou de campeonatos regionais de fade e ministra workshops para a equipe.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=800&q=80', 'Degradê, Low fade, Risco', 1, 2),
(3, 'Diego Martins', 'Barba é com ele: desenho, alinhamento e toalha quente. Atendimento calmo, detalhista e com aquele café no ponto.', 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=800&q=80', 'Barba, Toalha quente, Sobrancelha', 1, 3),
(4, 'Lucas Almeida', 'O mais criativo da equipe. Freestyle, desenhos e platinados para quem quer sair da cadeira com assinatura própria.', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=800&q=80', 'Freestyle, Platinado, Desenhos', 1, 4);

INSERT INTO servicos (id, nome, descricao, preco, duracao, foto, ativo, destaque, ordem) VALUES
(1, 'Corte Masculino', 'Consultoria de estilo, corte na máquina e tesoura, lavagem e finalização com pomada ou óleo à sua escolha.', 35.00, 40, 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=900&q=80', 1, 1, 1),
(2, 'Barba', 'Desenho, aparo e alinhamento na navalha com toalha quente, óleo pré-barba e balm hidratante.', 30.00, 30, 'https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=900&q=80', 1, 1, 2),
(3, 'Corte + Barba', 'O ritual completo: corte, barba com toalha quente e finalização. A combinação favorita da casa.', 60.00, 70, 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=900&q=80', 1, 1, 3),
(4, 'Sobrancelha', 'Limpeza e alinhamento com navalha ou pinça, mantendo a naturalidade da expressão.', 15.00, 15, 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=900&q=80', 1, 1, 4),
(5, 'Degradê Navalhado', 'Fade de alta precisão com acabamento na navalha, do zero ao topo sem marcas.', 45.00, 50, 'https://images.unsplash.com/photo-1605497788044-5a32c7078486?auto=format&fit=crop&w=900&q=80', 1, 0, 5),
(6, 'Pigmentação de Barba', 'Preenchimento de falhas com pigmento de alta fixação para uma barba mais cheia e uniforme.', 40.00, 30, 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=900&q=80', 1, 0, 6),
(7, 'Hidratação Capilar', 'Tratamento com máscara nutritiva e vapor de ozônio para fios mais fortes e brilhantes.', 40.00, 30, 'https://images.unsplash.com/photo-1512690459411-b9245aed614b?auto=format&fit=crop&w=900&q=80', 1, 0, 7),
(8, 'Corte Infantil', 'Corte para crianças de até 12 anos, com paciência e muito cuidado.', 30.00, 40, 'https://images.unsplash.com/photo-1517832606299-7ae9b720a186?auto=format&fit=crop&w=900&q=80', 1, 0, 8);

-- Horário de funcionamento da barbearia (barbeiro_id NULL). Domingo sem registro = fechado.
INSERT INTO horarios (barbeiro_id, dia_semana, hora_inicio, hora_fim) VALUES
(NULL, 1, '09:00', '20:00'),
(NULL, 2, '09:00', '20:00'),
(NULL, 3, '09:00', '20:00'),
(NULL, 4, '09:00', '20:00'),
(NULL, 5, '09:00', '20:00'),
(NULL, 6, '08:00', '18:00');

-- Jornada própria do Diego (com intervalo de almoço). Demais barbeiros seguem o horário da casa.
INSERT INTO horarios (barbeiro_id, dia_semana, hora_inicio, hora_fim) VALUES
(3, 2, '09:00', '12:00'), (3, 2, '13:00', '19:00'),
(3, 3, '09:00', '12:00'), (3, 3, '13:00', '19:00'),
(3, 4, '09:00', '12:00'), (3, 4, '13:00', '19:00'),
(3, 5, '09:00', '12:00'), (3, 5, '13:00', '19:00'),
(3, 6, '08:00', '12:00'), (3, 6, '13:00', '18:00');

INSERT INTO bloqueios (barbeiro_id, data, hora_inicio, hora_fim, tipo, motivo) VALUES
(NULL, '2026-10-12', NULL, NULL, 'feriado', 'Nossa Senhora Aparecida'),
(NULL, '2026-11-02', NULL, NULL, 'feriado', 'Finados'),
(NULL, '2026-11-20', NULL, NULL, 'feriado', 'Dia da Consciência Negra'),
(NULL, '2026-12-25', NULL, NULL, 'feriado', 'Natal'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, NULL, 'folga', 'Folga compensatória'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00', '13:00', 'compromisso', 'Reunião com fornecedor');

INSERT INTO clientes (id, nome, telefone, email, data_cadastro) VALUES
(1,  'Marcos Vinícius Rocha',   '11991230001', 'marcos.rocha@exemplo.com',    DATE_SUB(NOW(), INTERVAL 120 DAY)),
(2,  'Felipe Andrade',          '11991230002', 'felipe.andrade@exemplo.com',  DATE_SUB(NOW(), INTERVAL 110 DAY)),
(3,  'Gustavo Nogueira',        '11991230003', 'gustavo.n@exemplo.com',       DATE_SUB(NOW(), INTERVAL 95 DAY)),
(4,  'Thiago Moreira',          '11991230004', 'thiago.moreira@exemplo.com',  DATE_SUB(NOW(), INTERVAL 90 DAY)),
(5,  'Bruno Carvalho',          '11991230005', 'bruno.c@exemplo.com',         DATE_SUB(NOW(), INTERVAL 80 DAY)),
(6,  'Henrique Barros',         '11991230006', NULL,                          DATE_SUB(NOW(), INTERVAL 70 DAY)),
(7,  'Leonardo Pires',          '11991230007', 'leo.pires@exemplo.com',       DATE_SUB(NOW(), INTERVAL 60 DAY)),
(8,  'André Figueiredo',        '11991230008', 'andre.fig@exemplo.com',       DATE_SUB(NOW(), INTERVAL 45 DAY)),
(9,  'Caio Ribeiro',            '11991230009', 'caio.ribeiro@exemplo.com',    DATE_SUB(NOW(), INTERVAL 30 DAY)),
(10, 'Rodrigo Tavares',         '11991230010', 'rodrigo.t@exemplo.com',       DATE_SUB(NOW(), INTERVAL 20 DAY)),
(11, 'Eduardo Lima',            '11991230011', 'edu.lima@exemplo.com',        DATE_SUB(NOW(), INTERVAL 10 DAY)),
(12, 'Vinícius Prado',          '11991230012', 'vini.prado@exemplo.com',      DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Agendamentos passados (concluídos / cancelados), de hoje e futuros
INSERT INTO agendamentos (id, cliente_id, barbeiro_id, servico_id, data, hora, hora_fim, valor, status, observacao, token) VALUES
(1,  1, 1, 3, DATE_SUB(CURDATE(), INTERVAL 60 DAY), '10:00', '11:10', 60.00, 'concluido', NULL, MD5('demo-01')),
(2,  1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), '10:00', '10:40', 35.00, 'concluido', NULL, MD5('demo-02')),
(3,  2, 2, 5, DATE_SUB(CURDATE(), INTERVAL 28 DAY), '15:00', '15:50', 45.00, 'concluido', 'Low fade', MD5('demo-03')),
(4,  3, 3, 2, DATE_SUB(CURDATE(), INTERVAL 25 DAY), '09:00', '09:30', 30.00, 'concluido', NULL, MD5('demo-04')),
(5,  4, 4, 1, DATE_SUB(CURDATE(), INTERVAL 21 DAY), '17:00', '17:40', 35.00, 'concluido', 'Freestyle na lateral', MD5('demo-05')),
(6,  5, 1, 3, DATE_SUB(CURDATE(), INTERVAL 18 DAY), '14:00', '15:10', 60.00, 'concluido', NULL, MD5('demo-06')),
(7,  6, 2, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), '11:00', '11:40', 35.00, 'cancelado', NULL, MD5('demo-07')),
(8,  7, 3, 3, DATE_SUB(CURDATE(), INTERVAL 12 DAY), '16:00', '17:10', 60.00, 'concluido', NULL, MD5('demo-08')),
(9,  8, 2, 5, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  '10:00', '10:50', 45.00, 'concluido', NULL, MD5('demo-09')),
(10, 2, 2, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  '18:00', '18:40', 35.00, 'concluido', NULL, MD5('demo-10')),
(11, 9, 4, 6, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  '13:00', '13:30', 40.00, 'concluido', NULL, MD5('demo-11')),
(12, 10, 1, 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '09:40', '10:10', 30.00, 'concluido', NULL, MD5('demo-12')),
(13, 3, 3, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  '15:00', '16:10', 49.90, 'concluido', 'Promoção Corte + Barba', MD5('demo-13')),
(14, 11, 2, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '11:00', '11:40', 35.00, 'concluido', NULL, MD5('demo-14')),
(15, 5, 4, 7, DATE_SUB(CURDATE(), INTERVAL 1 DAY),  '16:00', '16:30', 40.00, 'concluido', NULL, MD5('demo-15')),
-- hoje
(16, 1, 1, 1, CURDATE(), '09:00', '09:40', 35.00, 'concluido',  NULL, MD5('demo-16')),
(17, 4, 2, 5, CURDATE(), '10:00', '10:50', 45.00, 'confirmado', NULL, MD5('demo-17')),
(18, 7, 3, 2, CURDATE(), '11:00', '11:30', 30.00, 'confirmado', NULL, MD5('demo-18')),
(19, 12, 1, 3, CURDATE(), '14:20', '15:30', 49.90, 'pendente',  'Primeira visita', MD5('demo-19')),
(20, 8, 4, 1, CURDATE(), '16:00', '16:40', 35.00, 'pendente',   NULL, MD5('demo-20')),
(21, 9, 2, 1, CURDATE(), '17:40', '18:20', 35.00, 'confirmado', NULL, MD5('demo-21')),
-- futuros
(22, 10, 1, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '15:00', '16:10', 49.90, 'confirmado', NULL, MD5('demo-22')),
(23, 11, 3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:30', '10:00', 30.00, 'pendente',   NULL, MD5('demo-23')),
(24, 2, 2, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '10:40', '11:30', 45.00, 'pendente',   NULL, MD5('demo-24')),
(25, 6, 4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '13:00', '13:40', 35.00, 'confirmado', NULL, MD5('demo-25')),
(26, 3, 1, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY),  '09:00', '09:40', 35.00, 'pendente',   NULL, MD5('demo-26'));

-- Os agendamentos de demonstração usam datas relativas; se algum cair em um
-- domingo (dia fechado), é movido para o sábado anterior.
UPDATE IGNORE agendamentos SET data = DATE_SUB(data, INTERVAL 1 DAY) WHERE DAYOFWEEK(data) = 1;

INSERT INTO avaliacoes (cliente_id, agendamento_id, nota, comentario, aprovado, criado_em) VALUES
(1, 2,  5, 'Melhor barbearia da região. O João tem uma mão absurda para corte social, saio sempre alinhado para a semana.', 1, DATE_SUB(NOW(), INTERVAL 29 DAY)),
(2, 3,  5, 'Degradê perfeito, sem nenhuma marca. O Rafael é muito detalhista e o ambiente é sensacional.', 1, DATE_SUB(NOW(), INTERVAL 27 DAY)),
(3, 4,  5, 'A barba com toalha quente do Diego é outro nível. Virei cliente fiel.', 1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(4, 5,  4, 'Curti muito o freestyle que o Lucas fez. Só demorou um pouco para começar, mas valeu a pena.', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(5, 6,  5, 'Agendamento pelo site super prático e atendimento pontual. Recomendo o combo corte + barba.', 1, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(7, 8,  5, 'Ambiente top, cerveja gelada e um trabalho impecável. Não troco por nada.', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
(8, 9,  4, 'Ótimo corte, equipe muito simpática. Voltarei com certeza.', 0, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(9, 11, 5, 'A pigmentação ficou super natural, ninguém percebeu. Serviço de primeira!', 0, DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO promocoes (nome, descricao, servico_id, preco_antigo, preco_promocional, data_inicio, data_fim, imagem, ativo) VALUES
('Corte + Barba', 'O ritual completo da casa com preço especial. Válido de segunda a quinta, para todos os barbeiros.', 3, 60.00, 49.90, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 40 DAY), 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=1400&q=80', 1),
('Pai & Filho', 'Corte masculino + corte infantil com 15% de desconto.', NULL, 65.00, 55.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY), NULL, 0);

INSERT INTO galeria (imagem, titulo, categoria, ativo) VALUES
('https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=900&q=80', 'Mid fade texturizado', 'degrade', 1),
('https://images.unsplash.com/photo-1605497788044-5a32c7078486?auto=format&fit=crop&w=900&q=80', 'Low fade com risco', 'degrade', 1),
('https://images.unsplash.com/photo-1621607512214-68297480165e?auto=format&fit=crop&w=900&q=80', 'Skin fade', 'degrade', 1),
('https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=900&q=80', 'Social executivo', 'social', 1),
('https://images.unsplash.com/photo-1517832606299-7ae9b720a186?auto=format&fit=crop&w=900&q=80', 'Clássico penteado', 'social', 1),
('https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=900&q=80', 'Barba com toalha quente', 'barba', 1),
('https://images.unsplash.com/photo-1621605815971-fbc98d665033?auto=format&fit=crop&w=900&q=80', 'Barba desenhada', 'barba', 1),
('https://images.unsplash.com/photo-1560869713-7d0a29430803?auto=format&fit=crop&w=900&q=80', 'Alinhamento na navalha', 'barba', 1),
('https://images.unsplash.com/photo-1532710093739-9470acff878f?auto=format&fit=crop&w=900&q=80', 'Desenho lateral', 'freestyle', 1),
('https://images.unsplash.com/photo-1567894340315-735d7c361db0?auto=format&fit=crop&w=900&q=80', 'Freestyle geométrico', 'freestyle', 1),
('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?auto=format&fit=crop&w=900&q=80', 'Nosso ambiente', 'outros', 1),
('https://images.unsplash.com/photo-1512690459411-b9245aed614b?auto=format&fit=crop&w=900&q=80', 'Estação de trabalho', 'outros', 1);

INSERT INTO mensagens (nome, email, telefone, assunto, mensagem, lida, criado_em) VALUES
('Paulo Mendes', 'paulo.mendes@exemplo.com', '11991230099', 'Evento corporativo', 'Olá! Gostaria de saber se vocês atendem eventos corporativos com 3 barbeiros no local.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY));
