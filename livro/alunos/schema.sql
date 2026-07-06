-- ============================================================
-- Módulo: Cadastro de Alunos
-- Banco  : pitfallcom_demanda
-- ============================================================

CREATE TABLE IF NOT EXISTS `turmas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dia_semana`  VARCHAR(20)  NOT NULL,
  `horario`     VARCHAR(10)  NOT NULL,
  `descricao`   VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alunos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`         VARCHAR(150) NOT NULL,
  `responsavel`  VARCHAR(150) DEFAULT NULL,
  `cpf`          VARCHAR(14)  DEFAULT NULL,
  `whatsapp`     VARCHAR(20)  DEFAULT NULL,
  `criado_em`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aluno_turmas` (
  `aluno_id`  INT UNSIGNED NOT NULL,
  `turma_id`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`aluno_id`, `turma_id`),
  FOREIGN KEY (`aluno_id`) REFERENCES `alunos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`turma_id`) REFERENCES `turmas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Turmas fixas
INSERT IGNORE INTO `turmas` (`id`, `dia_semana`, `horario`, `descricao`) VALUES
  (1,  'Segunda', '06h', 'Praia (Adulto)'),
  (2,  'Segunda', '14h', 'Sub Masculino'),
  (3,  'Segunda', '15h', 'Sub Feminino'),
  (4,  'Segunda', '16h', 'Sub Masculino'),
  (5,  'Segunda', '19h', 'MUV'),
  (6,  'Terça',   '15h', 'Iniciação'),
  (7,  'Terça',   '19h', 'Adulto MUV'),
  (8,  'Quarta',  '10h', 'Kids (ArenaBeachside)'),
  (9,  'Quarta',  '14h', 'Sub Masculino'),
  (10, 'Quarta',  '15h', 'Sub Feminino'),
  (11, 'Quarta',  '16h', 'Sub Masculino'),
  (12, 'Quarta',  '16h', 'Adulto (ArenaBeachside)'),
  (13, 'Quinta',  '17h', 'Iniciação MUV'),
  (14, 'Quinta',  '18h', 'Adulto Iniciante MU');
