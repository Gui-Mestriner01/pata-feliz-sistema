-- =============================================================
-- Pata Feliz — Sistema de agendamento
-- Banco de dados MySQL 8 / MariaDB 10.4+
-- Importe este arquivo no phpMyAdmin da Hostinger.
-- =============================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- -------------------------------------------------------------
-- Usuários do painel
-- -------------------------------------------------------------
CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(120)  NOT NULL,
  email       VARCHAR(160)  NOT NULL UNIQUE,
  senha_hash  VARCHAR(255)  NOT NULL,
  papel       ENUM('admin','atendente') NOT NULL DEFAULT 'atendente',
  ativo       TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Clientes (tutores) e seus pets
-- -------------------------------------------------------------
CREATE TABLE clientes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(120) NOT NULL,
  whatsapp     VARCHAR(11)  NOT NULL,          -- só dígitos: 11987654321
  email        VARCHAR(160) NULL,
  observacoes  TEXT         NULL,
  criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_clientes_whatsapp (whatsapp),
  KEY idx_clientes_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id  INT NOT NULL,
  nome        VARCHAR(80) NOT NULL,
  especie     ENUM('cachorro','gato') NOT NULL DEFAULT 'cachorro',
  raca        VARCHAR(80)   NULL,
  porte       ENUM('pp','p','m','g','gg') NOT NULL DEFAULT 'm',
  peso_kg     DECIMAL(5,2)  NULL,
  observacoes TEXT          NULL,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pets_cliente FOREIGN KEY (cliente_id)
    REFERENCES clientes(id) ON DELETE CASCADE,
  KEY idx_pets_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Serviços e preço por porte
-- -------------------------------------------------------------
CREATE TABLE servicos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(90)  NOT NULL,
  descricao    VARCHAR(255) NULL,
  duracao_min  SMALLINT     NOT NULL DEFAULT 60,  -- usado para calcular o fim
  especie      ENUM('ambos','cachorro','gato') NOT NULL DEFAULT 'ambos',
  ativo        TINYINT(1)   NOT NULL DEFAULT 1,
  ordem        SMALLINT     NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE precos (
  servico_id INT NOT NULL,
  porte      ENUM('pp','p','m','g','gg') NOT NULL,
  preco      DECIMAL(8,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (servico_id, porte),
  CONSTRAINT fk_precos_servico FOREIGN KEY (servico_id)
    REFERENCES servicos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Agendamentos
--
-- Fluxo dos status:
--   pendente   -> pedido novo, feito pelo cliente no site
--   confirmado -> o pet shop aprovou
--   remarcar   -> o pet shop sugeriu outro dia/horário
--   recusado   -> o pet shop negou (com motivo)
--   concluido  -> atendimento realizado
--   cancelado  -> desistência
-- -------------------------------------------------------------
CREATE TABLE agendamentos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id     INT  NOT NULL,
  pet_id         INT  NOT NULL,
  servico_id     INT  NOT NULL,
  data           DATE NOT NULL,
  hora_inicio    TIME NOT NULL,
  hora_fim       TIME NOT NULL,
  status         ENUM('pendente','confirmado','remarcar','recusado','concluido','cancelado')
                 NOT NULL DEFAULT 'pendente',
  preco          DECIMAL(8,2) NULL,
  observacoes    TEXT NULL,               -- escrito pelo cliente
  motivo         VARCHAR(255) NULL,       -- motivo da recusa ou da remarcação
  sugestao_data  DATE NULL,               -- preenchido quando status = remarcar
  sugestao_hora  TIME NULL,
  avisado_em     DATETIME NULL,           -- quando a mensagem foi enviada ao cliente
  usuario_id     INT NULL,                -- quem decidiu
  criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ag_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_ag_pet     FOREIGN KEY (pet_id)     REFERENCES pets(id),
  CONSTRAINT fk_ag_servico FOREIGN KEY (servico_id) REFERENCES servicos(id),
  CONSTRAINT fk_ag_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  KEY idx_ag_data (data, hora_inicio),
  KEY idx_ag_status (status),
  KEY idx_ag_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Bloqueios de agenda (feriado, almoço, manutenção)
-- data_fim NULL = bloqueio de um dia só
-- -------------------------------------------------------------
CREATE TABLE bloqueios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  data        DATE NOT NULL,
  hora_inicio TIME NULL,   -- NULL = dia inteiro
  hora_fim    TIME NULL,
  motivo      VARCHAR(140) NOT NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bloqueios_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Configurações gerais (chave/valor)
-- -------------------------------------------------------------
CREATE TABLE configuracoes (
  chave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Dados iniciais
-- =============================================================

-- O usuário do painel NÃO vem pronto aqui, de propósito: senha de fábrica
-- em sistema publicado é senha conhecida por qualquer um.
-- Depois de importar este arquivo, abra instalar.php no navegador, crie o
-- seu acesso e apague o instalar.php do servidor.

INSERT INTO configuracoes (chave, valor) VALUES
('abre',                  '08:00'),  -- início do expediente
('fecha',                 '18:00'),  -- fim do expediente
('dias_semana',           '1,2,3,4,5,6'), -- 1=segunda ... 7=domingo
('intervalo_min',         '30'),     -- de quanto em quanto tempo abre um horário
('capacidade_simultanea', '2'),      -- quantos pets atendidos ao mesmo tempo
('antecedencia_horas',    '2'),      -- mínimo de antecedência para agendar
('dias_futuros',          '60');     -- até quantos dias à frente aceita pedido

INSERT INTO servicos (id, nome, descricao, duracao_min, especie, ordem) VALUES
(1, 'Banho',          'Higiene completa com produtos premium e secagem cuidadosa.', 60, 'ambos', 1),
(2, 'Tosa',           'Higiênica, estética ou no padrão da raça.',                  90, 'ambos', 2),
(3, 'Banho + tosa',   'O pacote completo, no ritmo do pet.',                       120, 'ambos', 3),
(4, 'Hidratação',     'Pelos mais macios, saudáveis e com brilho que dura.',        60, 'ambos', 4),
(5, 'Corte de unhas', 'Com segurança, sem estresse e muito cuidado.',               30, 'ambos', 5),
(6, 'Higiene felina', 'Sala exclusiva para gatos, com manejo calmo.',               60, 'gato',  6);

-- Preços de exemplo — ajuste no painel (Serviços).
INSERT INTO precos (servico_id, porte, preco) VALUES
(1,'pp',45),(1,'p',55),(1,'m',70),(1,'g',90),(1,'gg',110),
(2,'pp',60),(2,'p',70),(2,'m',90),(2,'g',115),(2,'gg',140),
(3,'pp',90),(3,'p',105),(3,'m',130),(3,'g',165),(3,'gg',200),
(4,'pp',40),(4,'p',50),(4,'m',60),(4,'g',75),(4,'gg',90),
(5,'pp',25),(5,'p',25),(5,'m',30),(5,'g',35),(5,'gg',40),
(6,'pp',70),(6,'p',75),(6,'m',85),(6,'g',95),(6,'gg',110);
