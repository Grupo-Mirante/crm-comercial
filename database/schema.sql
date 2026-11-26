-- =====================================================================
-- CRM Comercial · Grupo Mirante — ESTRUTURA COMPLETA do banco crm_grupo_mirante
-- Gerado a partir de database/migrations. Para instalar, prefira o instalador (instalar.php).
-- Uso manual: mysql -u USUARIO -p crm_grupo_mirante < database/schema.sql
-- =====================================================================

-- >>> 001_estrutura.sql
-- =====================================================================
-- CRM Comercial · Grupo Mirante de Comunicação
-- Migration 001 — estrutura completa do banco crm_grupo_mirante
-- MySQL 8.0+ · InnoDB · utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. ACESSO, PERMISSÕES E SEGURANÇA (RN-71 a RN-78)
-- ---------------------------------------------------------------------

CREATE TABLE perfis (
    id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo        VARCHAR(20)  NOT NULL,
    nome          VARCHAR(60)  NOT NULL,
    descricao     VARCHAR(255) NULL,
    ordem         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_perfis_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-72: perfis de acesso';

CREATE TABLE permissoes (
    id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    modulo        VARCHAR(40)  NOT NULL,
    acao          VARCHAR(40)  NOT NULL,
    descricao     VARCHAR(160) NOT NULL,
    ordem         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_permissoes (modulo, acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Permissões por módulo e ação';

CREATE TABLE perfil_permissoes (
    perfil_id     TINYINT UNSIGNED  NOT NULL,
    permissao_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (perfil_id, permissao_id),
    KEY ix_pp_permissao (permissao_id),
    CONSTRAINT fk_pp_perfil    FOREIGN KEY (perfil_id)    REFERENCES perfis (id)     ON DELETE CASCADE,
    CONSTRAINT fk_pp_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE usuarios (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome                  VARCHAR(120) NOT NULL,
    login                 VARCHAR(60)  NOT NULL,
    email                 VARCHAR(160) NULL,
    matricula             VARCHAR(30)  NULL,
    telefone              VARCHAR(40)  NULL,
    perfil_id             TINYINT UNSIGNED NOT NULL,
    gestor_id             INT UNSIGNED NULL COMMENT 'RN-73: gerente responsável pela equipe',
    senha_hash            VARCHAR(255) NOT NULL COMMENT 'RN-78: Argon2id',
    trocar_senha          TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'RN-71/75: senha provisória exige troca',
    ativo                 TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'RN-77: desativado não entra',
    falhas_consecutivas   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate         DATETIME NULL,
    ultimo_acesso_em      DATETIME NULL,
    senha_alterada_em     DATETIME NULL,
    created_by            INT UNSIGNED NULL,
    updated_by            INT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuarios_login (login),
    UNIQUE KEY uk_usuarios_email (email),
    KEY ix_usuarios_perfil (perfil_id),
    KEY ix_usuarios_gestor (gestor_id),
    CONSTRAINT fk_usuarios_perfil FOREIGN KEY (perfil_id) REFERENCES perfis (id),
    CONSTRAINT fk_usuarios_gestor FOREIGN KEY (gestor_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_tentativas (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    login         VARCHAR(60) NOT NULL,
    ip            VARCHAR(45) NOT NULL,
    sucesso       TINYINT(1)  NOT NULL,
    motivo        VARCHAR(40) NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_lt_login (login, criado_em),
    KEY ix_lt_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-77: controle de tentativas';

CREATE TABLE senha_tokens (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL,
    expira_em     DATETIME NOT NULL,
    usado_em      DATETIME NULL,
    ip            VARCHAR(45) NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_st_token (token_hash),
    KEY ix_st_usuario (usuario_id),
    CONSTRAINT fk_st_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Recuperação de senha por token de uso único';

CREATE TABLE sessoes (
    id            CHAR(64) NOT NULL COMMENT 'SHA-256 do ID da sessão',
    usuario_id    INT UNSIGNED NULL,
    ip            VARCHAR(45) NULL,
    user_agent    VARCHAR(255) NULL,
    dados         MEDIUMBLOB NOT NULL,
    ultimo_uso    INT UNSIGNED NOT NULL,
    criada_em     INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY ix_sessoes_usuario (usuario_id),
    KEY ix_sessoes_uso (ultimo_uso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE logs_auditoria (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED NULL,
    usuario_nome  VARCHAR(120) NULL,
    acao          VARCHAR(60)  NOT NULL,
    entidade      VARCHAR(40)  NULL,
    entidade_id   VARCHAR(40)  NULL,
    descricao     VARCHAR(255) NULL,
    dados_antes   JSON NULL,
    dados_depois  JSON NULL,
    ip            VARCHAR(45)  NULL,
    user_agent    VARCHAR(255) NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_la_entidade (entidade, entidade_id),
    KEY ix_la_usuario (usuario_id, criado_em),
    KEY ix_la_criado (criado_em),
    KEY ix_la_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Auditoria: somente inserção';

-- ---------------------------------------------------------------------
-- 2. EMPRESAS, VEÍCULOS E TABELAS COMERCIAIS (RN-02, RN-09 a RN-15)
-- ---------------------------------------------------------------------

CREATE TABLE empresas (
    id                    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_curto            VARCHAR(60)  NOT NULL,
    razao_social          VARCHAR(160) NULL COMMENT 'Vazio = a preencher pelo administrador',
    cnpj                  VARCHAR(18)  NULL,
    endereco              VARCHAR(255) NULL,
    telefone              VARCHAR(60)  NULL,
    inscricao_municipal   VARCHAR(40)  NULL,
    dados_bancarios       VARCHAR(255) NULL,
    ativo                 TINYINT(1) NOT NULL DEFAULT 1,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_empresas_nome (nome_curto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Empresa emissora da PI';

CREATE TABLE veiculos (
    id                        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome                      VARCHAR(60) NOT NULL,
    tipo                      ENUM('TV','Rádio','Digital') NOT NULL,
    prefixo                   VARCHAR(8)  NOT NULL COMMENT 'RN-03: TV, FM, NEWS, G1, IMI',
    empresa_id                SMALLINT UNSIGNED NOT NULL,
    titulo_pi                 VARCHAR(120) NOT NULL,
    bloco_insercoes           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RN-09',
    bloco_patrocinio          TINYINT(1) NOT NULL DEFAULT 0,
    bloco_digital             TINYINT(1) NOT NULL DEFAULT 0,
    bloco_publicidade_legal   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Somente Imirante',
    divide_pi_por_mes         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RN-17: somente TV',
    clausula_6                TEXT NULL,
    clausula_7                TEXT NULL,
    texto_atendimento         VARCHAR(400) NULL,
    ordem                     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo                     TINYINT(1) NOT NULL DEFAULT 1,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_veiculos_nome (nome),
    UNIQUE KEY uk_veiculos_prefixo (prefixo),
    KEY ix_veiculos_empresa (empresa_id),
    CONSTRAINT fk_veiculos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE tabelas_precos (
    id              SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    veiculo_id      TINYINT UNSIGNED NOT NULL,
    codigo          VARCHAR(10)  NOT NULL COMMENT 'MA1, IMP, CDO, BAS, MAE, MAI, SAT, LOC, NAC, MA',
    descricao       VARCHAR(80)  NOT NULL,
    cidade          VARCHAR(60)  NOT NULL DEFAULT 'São Luís - MA',
    ano_vigencia    SMALLINT UNSIGNED NOT NULL,
    sem_tabela      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Mirante SAT: exige preço manual',
    ordem           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tp (veiculo_id, codigo, ano_vigencia),
    CONSTRAINT fk_tp_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-10: tabela ou praça';

CREATE TABLE indices_duracao (
    duracao_seg   SMALLINT UNSIGNED NOT NULL,
    indice        DECIMAL(7,4) NOT NULL,
    PRIMARY KEY (duracao_seg)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-12: índice sobre o 30 segundos';

CREATE TABLE programas (
    id                SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    veiculo_id        TINYINT UNSIGNED NOT NULL,
    sigla             VARCHAR(12)  NOT NULL,
    nome              VARCHAR(120) NOT NULL,
    dias_exibicao     VARCHAR(40)  NULL COMMENT 'Texto do cadastro (ex.: SEG/SEX)',
    dias_mascara      TINYINT UNSIGNED NULL COMMENT 'RN-23: bits DOM=1, SEG=2 … SÁB=64; NULL = sem restrição',
    faixa_horaria     VARCHAR(40)  NULL,
    indice_15s        DECIMAL(7,4) NULL COMMENT 'TV: índice do 15" próprio do programa',
    ativo             TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_programas (veiculo_id, sigla),
    KEY ix_programas_nome (nome),
    CONSTRAINT fk_programas_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE programa_esquemas (
    programa_id   SMALLINT UNSIGNED NOT NULL,
    ins_30        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ins_60        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    vinheta       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    flash         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    testemunhal   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    entrevista    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (programa_id),
    CONSTRAINT fk_pe_programa FOREIGN KEY (programa_id) REFERENCES programas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-26: esquema de inserções por cota de patrocínio';

CREATE TABLE programa_audiencia (
    programa_id       SMALLINT UNSIGNED NOT NULL,
    tabela_preco_id   SMALLINT UNSIGNED NOT NULL,
    grp               DECIMAL(12,5) NOT NULL DEFAULT 0,
    impactos          INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (programa_id, tabela_preco_id),
    KEY ix_pa_tabela (tabela_preco_id),
    CONSTRAINT fk_pa_programa FOREIGN KEY (programa_id) REFERENCES programas (id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_tabela   FOREIGN KEY (tabela_preco_id) REFERENCES tabelas_precos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-25: GRP e impactos por praça';

CREATE TABLE formatos_midia (
    id                  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    veiculo_id          TINYINT UNSIGNED NOT NULL,
    sigla               VARCHAR(12)  NOT NULL,
    nome                VARCHAR(200) NOT NULL,
    modelo              ENUM('Mensal','Diária','Unidade','Pacote','CPM','cm/col') NOT NULL COMMENT 'RN-14',
    categoria           VARCHAR(60)  NULL,
    entrega_estimada    INT UNSIGNED NULL,
    visibilidade_pct    DECIMAL(5,2) NULL,
    dispositivo         VARCHAR(40)  NULL,
    local_pagina        VARCHAR(120) NULL,
    observacao          VARCHAR(255) NULL,
    publicidade_legal   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RN-31: somente Imirante',
    ativo               TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_formatos (veiculo_id, sigla),
    CONSTRAINT fk_formatos_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE precos_midia (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tabela_preco_id   SMALLINT UNSIGNED NOT NULL,
    programa_id       SMALLINT UNSIGNED NULL,
    formato_id        SMALLINT UNSIGNED NULL,
    tipo              ENUM('base_30s','duracao','cota_patrocinio','unitario') NOT NULL
                      COMMENT 'base_30s: TV (RN-12) · duracao: rádio (RN-13) · cota_patrocinio: rádio (RN-26) · unitario: digital (RN-14)',
    duracao_seg       SMALLINT UNSIGNED NULL,
    valor             DECIMAL(14,2) NOT NULL,
    chave             VARCHAR(80) AS (CONCAT_WS(':', tabela_preco_id, IFNULL(programa_id, 0), IFNULL(formato_id, 0), tipo, IFNULL(duracao_seg, 0))) STORED,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_precos (chave),
    KEY ix_precos_programa (programa_id),
    KEY ix_precos_formato (formato_id),
    CONSTRAINT fk_precos_tabela   FOREIGN KEY (tabela_preco_id) REFERENCES tabelas_precos (id),
    CONSTRAINT fk_precos_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
    CONSTRAINT fk_precos_formato  FOREIGN KEY (formato_id) REFERENCES formatos_midia (id),
    CONSTRAINT ck_precos_item CHECK ((programa_id IS NULL) <> (formato_id IS NULL)),
    CONSTRAINT ck_precos_valor CHECK (valor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Preços de tabela 2026';

CREATE TABLE listas_valores (
    id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lista     VARCHAR(40) NOT NULL,
    valor     VARCHAR(80) NOT NULL,
    ordem     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_lv (lista, valor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Listas de opções dos formulários';

CREATE TABLE clausulas (
    id              TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(20)  NOT NULL,
    titulo          VARCHAR(120) NOT NULL,
    texto           TEXT NOT NULL,
    ordem           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    versao          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    updated_by      INT UNSIGNED NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_clausulas_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-47: cláusulas fixas (só o administrador edita)';

CREATE TABLE feriados (
    data          DATE NOT NULL,
    descricao     VARCHAR(120) NOT NULL,
    abrangencia   ENUM('Nacional','Estadual','Municipal') NOT NULL DEFAULT 'Nacional',
    PRIMARY KEY (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Dias úteis para SLAs';

CREATE TABLE configuracoes (
    chave         VARCHAR(60)  NOT NULL,
    valor         TEXT NULL,
    tipo          ENUM('texto','inteiro','decimal','booleano','hora','email','texto_longo') NOT NULL DEFAULT 'texto',
    grupo         VARCHAR(40)  NOT NULL DEFAULT 'Geral',
    descricao     VARCHAR(255) NOT NULL,
    editavel      TINYINT(1) NOT NULL DEFAULT 1,
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 3. CLIENTES E AGÊNCIAS (RN-61 a RN-64, RN-79, RN-80)
-- ---------------------------------------------------------------------

CREATE TABLE clientes (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razao_social          VARCHAR(160) NOT NULL,
    nome_fantasia         VARCHAR(160) NULL,
    tipo_documento        ENUM('CNPJ','CPF') NOT NULL,
    documento             VARCHAR(14)  NOT NULL COMMENT 'RN-63: chave sem máscara (CNPJ alfanumérico aceito)',
    documento_formatado   VARCHAR(18)  NOT NULL,
    inscricao_estadual    VARCHAR(30)  NULL,
    endereco              VARCHAR(200) NULL,
    bairro                VARCHAR(80)  NULL,
    cidade                VARCHAR(80)  NULL,
    uf                    CHAR(2)      NULL,
    cep                   VARCHAR(9)   NULL,
    contato               VARCHAR(120) NULL,
    telefone              VARCHAR(60)  NULL,
    email_faturamento     VARCHAR(160) NULL,
    codigo_interno        VARCHAR(30)  NULL,
    situacao_cadastral    ENUM('Ativo','Em análise','Bloqueado','Inativo') NOT NULL DEFAULT 'Ativo',
    ativo                 TINYINT(1) NOT NULL DEFAULT 1,
    created_by            INT UNSIGNED NULL,
    updated_by            INT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_clientes_documento (documento),
    KEY ix_clientes_razao (razao_social),
    KEY ix_clientes_fantasia (nome_fantasia),
    KEY ix_clientes_codigo (codigo_interno),
    FULLTEXT KEY ft_clientes (razao_social, nome_fantasia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE agencias (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    razao_social      VARCHAR(160) NOT NULL,
    cnpj              VARCHAR(14)  NOT NULL COMMENT 'RN-79: único, sem máscara',
    cnpj_formatado    VARCHAR(18)  NOT NULL,
    comissao_padrao   DECIMAL(5,2) NOT NULL DEFAULT 0,
    carta_numero      VARCHAR(40)  NULL,
    carta_validade    DATE NULL COMMENT 'RN-80: vencida na emissão = D08',
    endereco          VARCHAR(200) NULL,
    cidade            VARCHAR(80)  NULL,
    uf                CHAR(2)      NULL,
    telefone          VARCHAR(60)  NULL,
    contato           VARCHAR(120) NULL,
    email             VARCHAR(160) NULL,
    ativo             TINYINT(1) NOT NULL DEFAULT 1,
    created_by        INT UNSIGNED NULL,
    updated_by        INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_agencias_cnpj (cnpj),
    KEY ix_agencias_razao (razao_social),
    CONSTRAINT ck_agencias_comissao CHECK (comissao_padrao >= 0 AND comissao_padrao <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 4. PROJETOS ESPECIAIS E RESERVAS (RN-32 a RN-39, RN-65 a RN-70)
-- ---------------------------------------------------------------------

CREATE TABLE projetos_especiais (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(20)  NOT NULL,
    nome                VARCHAR(120) NOT NULL,
    descricao           TEXT NULL,
    data_limite_venda   DATE NOT NULL COMMENT 'RN-33: comparada com a data de hoje',
    ativo               TINYINT(1) NOT NULL DEFAULT 1,
    excluido_em         DATETIME NULL COMMENT 'RN-39: exclusão não apaga PIs',
    created_by          INT UNSIGNED NULL,
    updated_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_projetos_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE projetos_empresas (
    projeto_id    INT UNSIGNED NOT NULL,
    veiculo_id    TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (projeto_id, veiculo_id),
    KEY ix_pje_veiculo (veiculo_id),
    CONSTRAINT fk_pje_projeto FOREIGN KEY (projeto_id) REFERENCES projetos_especiais (id) ON DELETE CASCADE,
    CONSTRAINT fk_pje_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-35: empresas em que o projeto vale';

CREATE TABLE projetos_cotas (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    projeto_id          INT UNSIGNED NOT NULL,
    nome                VARCHAR(40)  NOT NULL,
    valor               DECIMAL(14,2) NOT NULL,
    quantidade_total    INT UNSIGNED NOT NULL,
    ordem               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pc (projeto_id, nome),
    CONSTRAINT fk_pc_projeto FOREIGN KEY (projeto_id) REFERENCES projetos_especiais (id),
    CONSTRAINT ck_pc_valor CHECK (valor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Linha travada com SELECT … FOR UPDATE em toda venda e reserva (RN-34)';

CREATE TABLE reservas (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(20) NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL,
    veiculo_id          TINYINT UNSIGNED NOT NULL,
    cliente_id          INT UNSIGNED NULL,
    cliente_nome        VARCHAR(160) NOT NULL,
    data_inicio         DATE NULL,
    data_fim            DATE NULL,
    reservada_em        DATETIME NOT NULL,
    expira_em           DATETIME NOT NULL COMMENT 'RN-67: 24 h, não renova',
    situacao            ENUM('ativa','convertida','liberada','expirada') NOT NULL DEFAULT 'ativa',
    encerrada_em        DATETIME NULL,
    encerrada_por       INT UNSIGNED NULL,
    pi_id               INT UNSIGNED NULL,
    resumo_itens        VARCHAR(600) NULL,
    estado_formulario   JSON NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_reservas_codigo (codigo),
    KEY ix_reservas_ativas (situacao, expira_em),
    KEY ix_reservas_usuario (usuario_id),
    KEY ix_reservas_veiculo (veiculo_id),
    CONSTRAINT fk_reservas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_reservas_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id),
    CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reservas_itens (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reserva_id        INT UNSIGNED NOT NULL,
    tipo              ENUM('cota','insercao','patrocinio','digital') NOT NULL,
    projeto_cota_id   INT UNSIGNED NULL,
    programa_id       SMALLINT UNSIGNED NULL,
    formato_id        SMALLINT UNSIGNED NULL,
    sigla             VARCHAR(20) NULL,
    descricao         VARCHAR(200) NULL,
    quantidade        INT UNSIGNED NOT NULL DEFAULT 1,
    mes_ref           CHAR(7) NULL,
    data_inicio       DATE NULL,
    data_fim          DATE NULL,
    dias              JSON NULL COMMENT 'Inserções: {"2026-12":[1,2,3]}',
    PRIMARY KEY (id),
    KEY ix_ri_reserva (reserva_id),
    KEY ix_ri_cota (projeto_cota_id),
    KEY ix_ri_programa (programa_id),
    KEY ix_ri_formato (formato_id),
    CONSTRAINT fk_ri_reserva  FOREIGN KEY (reserva_id) REFERENCES reservas (id) ON DELETE CASCADE,
    CONSTRAINT fk_ri_cota     FOREIGN KEY (projeto_cota_id) REFERENCES projetos_cotas (id),
    CONSTRAINT fk_ri_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
    CONSTRAINT fk_ri_formato  FOREIGN KEY (formato_id) REFERENCES formatos_midia (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------
-- 5. PEDIDO DE INSERÇÃO (PI)
-- ---------------------------------------------------------------------

CREATE TABLE sequencias_pi (
    veiculo_id      TINYINT UNSIGNED NOT NULL,
    ano             SMALLINT UNSIGNED NOT NULL,
    ultimo_numero   INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (veiculo_id, ano),
    CONSTRAINT fk_seq_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-03/RN-04: sequência anual por veículo, travada com FOR UPDATE';

CREATE TABLE pi_grupos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    descricao   VARCHAR(255) NOT NULL COMMENT 'RN-17: ex. TV-2026-0001 + TV-2026-0002',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_rascunhos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED NOT NULL,
    pi_id           INT UNSIGNED NULL COMMENT 'Preenchido quando o rascunho é a edição de uma PI já gerada',
    veiculo_id      TINYINT UNSIGNED NULL,
    cliente_nome    VARCHAR(160) NULL,
    titulo          VARCHAR(160) NULL,
    modo            ENUM('nova','edicao','correcao','renovacao','substituicao') NOT NULL DEFAULT 'nova',
    estado          JSON NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_rasc_usuario (usuario_id, updated_at),
    KEY ix_rasc_pi (pi_id),
    CONSTRAINT fk_rasc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Etapa "Em negociação": PI em montagem, ainda sem número (salvamento automático)';

CREATE TABLE pis (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero                  VARCHAR(20) NOT NULL COMMENT 'RN-03: PREFIXO-ANO-SEQUÊNCIA',
    ano                     SMALLINT UNSIGNED NOT NULL,
    sequencial              INT UNSIGNED NOT NULL,
    veiculo_id              TINYINT UNSIGNED NOT NULL,
    empresa_id              SMALLINT UNSIGNED NOT NULL,
    tabela_preco_id         SMALLINT UNSIGNED NULL,
    tipo                    ENUM('Nova','Renovação','Alteração','Cancelamento','Cancela/substitui') NOT NULL COMMENT 'RN-06',
    pi_origem_id            INT UNSIGNED NULL,
    pi_origem_numero        VARCHAR(40) NULL,
    substituida_por_id      INT UNSIGNED NULL,
    grupo_id                INT UNSIGNED NULL COMMENT 'RN-17: PIs mensais da mesma campanha de TV',
    parte                   VARCHAR(12) NULL COMMENT 'Ex.: 1 de 2',
    mes_referencia          CHAR(7) NULL,
    numero_rp               VARCHAR(40) NULL,
    data_emissao            DATE NOT NULL,
    executivo_id            INT UNSIGNED NOT NULL COMMENT 'RN-76: dono da PI',
    gestor_id               INT UNSIGNED NULL,
    contato_comercial       VARCHAR(160) NULL,
    venda                   ENUM('Direta','Agência') NOT NULL DEFAULT 'Direta',
    cliente_id              INT UNSIGNED NULL,
    cliente_razao           VARCHAR(160) NULL,
    cliente_documento       VARCHAR(14)  NULL,
    agencia_id              INT UNSIGNED NULL,
    agencia_razao           VARCHAR(160) NULL,
    comissao_pct            DECIMAL(5,2) NOT NULL DEFAULT 0,
    titulo                  VARCHAR(160) NULL,
    produto                 VARCHAR(160) NULL,
    data_inicio             DATE NULL,
    data_fim                DATE NULL,
    prazo_pagamento_dias    SMALLINT UNSIGNED NULL,
    praca_cobranca          VARCHAR(40) NULL,
    forma_pagamento         VARCHAR(40) NULL,
    producao_taxas          DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_tabela            DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'RN-40',
    valor_bonificado        DECIMAL(14,2) NOT NULL DEFAULT 0,
    desconto_nominal        DECIMAL(14,2) NOT NULL DEFAULT 0,
    desconto_pct            DECIMAL(9,6)  NOT NULL DEFAULT 0,
    valor_negociado         DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_comissao          DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'RN-42',
    valor_liquido           DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_insercoes         INT UNSIGNED  NOT NULL DEFAULT 0,
    grp                     DECIMAL(14,5) NOT NULL DEFAULT 0,
    impactos                BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cpm                     DECIMAL(14,2) NOT NULL DEFAULT 0,
    qtd_pendencias          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    pendencias              JSON NULL,
    situacao                ENUM('Rascunho','Enviada','Devolvida','Corrigida','Aprovada','Em programação','Em veiculação',
                                 'Pronta para faturamento','Faturada','Encerrada','Cancelada','Substituída') NOT NULL,
    qtd_devolucoes          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fora_do_prazo           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enviada com menos antecedência que o SLA',
    urgente                 TINYINT(1) NOT NULL DEFAULT 0,
    urgencia_aprovada_por   INT UNSIGNED NULL,
    enviada_em              DATETIME NULL COMMENT 'Primeiro envio à Conferência',
    aprovada_em             DATETIME NULL,
    aprovada_por            INT UNSIGNED NULL,
    aprovada_primeira       TINYINT(1) NULL COMMENT 'Aprovada sem nenhuma devolução',
    programada_em           DATETIME NULL,
    veiculacao_iniciada_em  DATETIME NULL,
    data_fim_veiculacao     DATE NULL COMMENT 'RN-57',
    pronta_faturamento_em   DATETIME NULL,
    nf_numero               VARCHAR(40) NULL,
    nf_valor                DECIMAL(14,2) NULL,
    faturada_em             DATETIME NULL,
    encerrada_em            DATETIME NULL,
    cancelada_em            DATETIME NULL,
    cancelada_por           INT UNSIGNED NULL,
    motivo_cancelamento     VARCHAR(500) NULL,
    reserva_id              INT UNSIGNED NULL,
    versao_atual            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    estado_formulario       JSON NOT NULL COMMENT 'Dados digitados (para reabrir a PI, RN-05/RN-49)',
    snapshot                JSON NULL COMMENT 'Cliente, agência, executivo e emissora como saíram na PI',
    created_by              INT UNSIGNED NOT NULL,
    updated_by              INT UNSIGNED NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pis_numero (numero),
    UNIQUE KEY uk_pis_seq (veiculo_id, ano, sequencial),
    KEY ix_pis_situacao (situacao),
    KEY ix_pis_executivo (executivo_id, situacao),
    KEY ix_pis_gestor (gestor_id),
    KEY ix_pis_emissao (data_emissao),
    KEY ix_pis_veiculo (veiculo_id, data_emissao),
    KEY ix_pis_cliente (cliente_id),
    KEY ix_pis_cliente_doc (cliente_documento),
    KEY ix_pis_agencia (agencia_id),
    KEY ix_pis_grupo (grupo_id),
    KEY ix_pis_origem (pi_origem_id),
    KEY ix_pis_periodo (data_inicio, data_fim),
    CONSTRAINT fk_pis_veiculo   FOREIGN KEY (veiculo_id) REFERENCES veiculos (id),
    CONSTRAINT fk_pis_empresa   FOREIGN KEY (empresa_id) REFERENCES empresas (id),
    CONSTRAINT fk_pis_tabela    FOREIGN KEY (tabela_preco_id) REFERENCES tabelas_precos (id),
    CONSTRAINT fk_pis_origem    FOREIGN KEY (pi_origem_id) REFERENCES pis (id),
    CONSTRAINT fk_pis_subst     FOREIGN KEY (substituida_por_id) REFERENCES pis (id),
    CONSTRAINT fk_pis_grupo     FOREIGN KEY (grupo_id) REFERENCES pi_grupos (id),
    CONSTRAINT fk_pis_executivo FOREIGN KEY (executivo_id) REFERENCES usuarios (id),
    CONSTRAINT fk_pis_gestor    FOREIGN KEY (gestor_id) REFERENCES usuarios (id),
    CONSTRAINT fk_pis_cliente   FOREIGN KEY (cliente_id) REFERENCES clientes (id),
    CONSTRAINT fk_pis_agencia   FOREIGN KEY (agencia_id) REFERENCES agencias (id),
    CONSTRAINT fk_pis_reserva   FOREIGN KEY (reserva_id) REFERENCES reservas (id),
    CONSTRAINT fk_pis_criador   FOREIGN KEY (created_by) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE reservas ADD CONSTRAINT fk_reservas_pi FOREIGN KEY (pi_id) REFERENCES pis (id);
ALTER TABLE pi_rascunhos ADD CONSTRAINT fk_rasc_pi FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE SET NULL;

CREATE TABLE pi_itens (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id               INT UNSIGNED NOT NULL,
    bloco               ENUM('insercao','patrocinio','digital','publicidade_legal','projeto') NOT NULL,
    ordem               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    programa_id         SMALLINT UNSIGNED NULL,
    formato_id          SMALLINT UNSIGNED NULL,
    projeto_cota_id     INT UNSIGNED NULL,
    sigla               VARCHAR(20)  NULL,
    descricao           VARCHAR(255) NULL,
    mes_ref             CHAR(7) NULL COMMENT 'RN-18: linha do mês (rádio)',
    duracao_seg         SMALLINT UNSIGNED NULL,
    indice              DECIMAL(7,4) NULL,
    cobranca            VARCHAR(30) NULL COMMENT 'RN-24/RN-30',
    quantidade          DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Inserções, cotas, diárias, unidades ou publicações',
    impressoes          BIGINT UNSIGNED NULL COMMENT 'RN-29: CPM',
    colunas             DECIMAL(6,2) NULL COMMENT 'RN-31',
    centimetros         DECIMAL(6,2) NULL,
    data_inicio         DATE NULL,
    data_fim            DATE NULL,
    preco_unitario      DECIMAL(14,2) NULL,
    preco_manual        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'RN-15',
    desconto_pct        DECIMAL(5,2) NOT NULL DEFAULT 0,
    valor_tabela        DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_total         DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_bonificado    DECIMAL(14,2) NOT NULL DEFAULT 0,
    grp                 DECIMAL(14,5) NULL,
    impactos            BIGINT UNSIGNED NULL,
    detalhes            JSON NULL,
    observacao          VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY ix_pii_pi (pi_id, bloco, ordem),
    KEY ix_pii_cota (projeto_cota_id),
    KEY ix_pii_programa (programa_id),
    KEY ix_pii_formato (formato_id),
    CONSTRAINT fk_pii_pi       FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_pii_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
    CONSTRAINT fk_pii_formato  FOREIGN KEY (formato_id) REFERENCES formatos_midia (id),
    CONSTRAINT fk_pii_cota     FOREIGN KEY (projeto_cota_id) REFERENCES projetos_cotas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_insercoes_diarias (
    pi_item_id    INT UNSIGNED NOT NULL,
    data          DATE NOT NULL,
    quantidade    SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (pi_item_id, data),
    KEY ix_pid_data (data),
    CONSTRAINT fk_pid_item FOREIGN KEY (pi_item_id) REFERENCES pi_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-21: grade de inserções por dia';

CREATE TABLE pi_patrocinios (
    pi_item_id    INT UNSIGNED NOT NULL,
    ins_30        INT UNSIGNED NOT NULL DEFAULT 0,
    ins_60        INT UNSIGNED NOT NULL DEFAULT 0,
    vinheta       INT UNSIGNED NOT NULL DEFAULT 0,
    flash         INT UNSIGNED NOT NULL DEFAULT 0,
    testemunhal   INT UNSIGNED NOT NULL DEFAULT 0,
    entrevista    INT UNSIGNED NOT NULL DEFAULT 0,
    outras        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (pi_item_id),
    CONSTRAINT fk_pp_item FOREIGN KEY (pi_item_id) REFERENCES pi_itens (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-26';

CREATE TABLE anexos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entidade        VARCHAR(30)  NOT NULL,
    entidade_id     INT UNSIGNED NOT NULL,
    tipo            ENUM('aceite','briefing','comprovante','carta','juridico','outro') NOT NULL,
    nome_original   VARCHAR(200) NOT NULL,
    caminho         VARCHAR(255) NOT NULL COMMENT 'Relativo a storage/uploads (fora da pasta pública)',
    mime            VARCHAR(100) NOT NULL,
    tamanho         INT UNSIGNED NOT NULL,
    sha256          CHAR(64) NOT NULL,
    enviado_por     INT UNSIGNED NOT NULL,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    excluido_em     DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_anexos_entidade (entidade, entidade_id),
    CONSTRAINT fk_anexos_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_entregas_especiais (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id               INT UNSIGNED NOT NULL,
    ordem               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    tipo                VARCHAR(40)  NOT NULL,
    especificacao       VARCHAR(255) NULL,
    quantidade          INT UNSIGNED NULL,
    area_responsavel    VARCHAR(40)  NULL COMMENT 'RN-44',
    numero_briefing     VARCHAR(40)  NULL,
    aval_juridico       ENUM('Não se aplica','Pendente','Aprovado') NOT NULL DEFAULT 'Não se aplica',
    anexo_id            INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_pee_pi (pi_id),
    CONSTRAINT fk_pee_pi    FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_pee_anexo FOREIGN KEY (anexo_id) REFERENCES anexos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_parcelas (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id         INT UNSIGNED NOT NULL,
    numero        TINYINT UNSIGNED NOT NULL,
    vencimento    DATE NOT NULL,
    valor         DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_parc (pi_id, numero),
    KEY ix_parc_venc (vencimento),
    CONSTRAINT fk_parc_pi FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-43: soma = valor líquido';

CREATE TABLE pi_aprovacoes (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id               INT UNSIGNED NULL,
    rascunho_id         INT UNSIGNED NULL,
    tipo                ENUM('comercial','diretoria','urgencia') NOT NULL COMMENT 'comercial = desconto, bonificação e preço manual (RN-41)',
    situacao            ENUM('pendente','aprovada','recusada','cancelada') NOT NULL DEFAULT 'pendente',
    solicitante_id      INT UNSIGNED NOT NULL,
    aprovador_id        INT UNSIGNED NULL,
    aprovador_nome      VARCHAR(120) NULL,
    desconto_pct        DECIMAL(9,6)  NOT NULL DEFAULT 0,
    desconto_nominal    DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_bonificado    DECIMAL(14,2) NOT NULL DEFAULT 0,
    precos_manuais      JSON NULL,
    resumo              VARCHAR(500) NULL,
    justificativa       VARCHAR(500) NULL,
    resposta            VARCHAR(500) NULL,
    solicitada_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondida_em       DATETIME NULL,
    PRIMARY KEY (id),
    KEY ix_apr_pi (pi_id),
    KEY ix_apr_rasc (rascunho_id),
    KEY ix_apr_pendentes (situacao, tipo),
    CONSTRAINT fk_apr_pi          FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_apr_rasc        FOREIGN KEY (rascunho_id) REFERENCES pi_rascunhos (id) ON DELETE SET NULL,
    CONSTRAINT fk_apr_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios (id),
    CONSTRAINT fk_apr_aprovador   FOREIGN KEY (aprovador_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE motivos_devolucao (
    codigo          CHAR(3) NOT NULL,
    descricao       VARCHAR(160) NOT NULL,
    exemplo         VARCHAR(160) NULL,
    ordem           TINYINT UNSIGNED NOT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='D01 a D15';

CREATE TABLE checklist_conferencia (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem       TINYINT UNSIGNED NOT NULL,
    descricao   VARCHAR(200) NOT NULL,
    codigos     VARCHAR(40)  NOT NULL COMMENT 'Motivos de devolução ligados ao item',
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Checklist de 10 itens da Conferência';

CREATE TABLE pi_conferencias (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id           INT UNSIGNED NOT NULL,
    versao          SMALLINT UNSIGNED NOT NULL,
    conferente_id   INT UNSIGNED NOT NULL,
    resultado       ENUM('aprovada','devolvida') NOT NULL,
    checklist       JSON NOT NULL,
    observacao      VARCHAR(500) NULL,
    iniciada_em     DATETIME NULL,
    concluida_em    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_conf_pi (pi_id),
    KEY ix_conf_data (concluida_em),
    CONSTRAINT fk_conf_pi FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_conf_usuario FOREIGN KEY (conferente_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_devolucoes (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id               INT UNSIGNED NOT NULL,
    conferencia_id      INT UNSIGNED NULL,
    sequencia           SMALLINT UNSIGNED NOT NULL COMMENT '1ª, 2ª… devolução da PI',
    descricao           TEXT NOT NULL COMMENT 'RN-52: o que corrigir',
    devolvida_por       INT UNSIGNED NOT NULL,
    executivo_id        INT UNSIGNED NOT NULL COMMENT 'Responsável pela correção',
    devolvida_em        DATETIME NOT NULL,
    corrigida_em        DATETIME NULL,
    gestor_notificado   TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_dev_seq (pi_id, sequencia),
    KEY ix_dev_exec (executivo_id, devolvida_em),
    KEY ix_dev_data (devolvida_em),
    CONSTRAINT fk_dev_pi    FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_dev_conf  FOREIGN KEY (conferencia_id) REFERENCES pi_conferencias (id),
    CONSTRAINT fk_dev_por   FOREIGN KEY (devolvida_por) REFERENCES usuarios (id),
    CONSTRAINT fk_dev_exec  FOREIGN KEY (executivo_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_devolucao_codigos (
    devolucao_id    INT UNSIGNED NOT NULL,
    motivo_codigo   CHAR(3) NOT NULL,
    PRIMARY KEY (devolucao_id, motivo_codigo),
    KEY ix_dc_motivo (motivo_codigo),
    CONSTRAINT fk_dc_dev    FOREIGN KEY (devolucao_id) REFERENCES pi_devolucoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_dc_motivo FOREIGN KEY (motivo_codigo) REFERENCES motivos_devolucao (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pi_versoes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id           INT UNSIGNED NOT NULL,
    versao          SMALLINT UNSIGNED NOT NULL,
    estado          JSON NOT NULL,
    resultado       JSON NOT NULL COMMENT 'Totais e conferência automática no momento',
    hash            CHAR(64) NOT NULL,
    situacao        VARCHAR(30) NOT NULL,
    gerado_por      INT UNSIGNED NOT NULL,
    gerado_em       DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_versoes (pi_id, versao),
    CONSTRAINT fk_versoes_pi FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE,
    CONSTRAINT fk_versoes_usuario FOREIGN KEY (gerado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-53: histórico de correções preservado';

CREATE TABLE pi_historico (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pi_id               INT UNSIGNED NOT NULL,
    situacao_anterior   VARCHAR(30) NULL,
    situacao_nova       VARCHAR(30) NULL,
    evento              VARCHAR(255) NOT NULL,
    detalhes            JSON NULL,
    usuario_id          INT UNSIGNED NULL,
    usuario_nome        VARCHAR(120) NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_hist_pi (pi_id, criado_em),
    KEY ix_hist_data (criado_em),
    CONSTRAINT fk_hist_pi FOREIGN KEY (pi_id) REFERENCES pis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='RN-59: data, evento e responsável';

CREATE TABLE notificacoes (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id          INT UNSIGNED NOT NULL,
    tipo                VARCHAR(40)  NOT NULL,
    titulo              VARCHAR(160) NOT NULL,
    mensagem            VARCHAR(600) NULL,
    link                VARCHAR(255) NULL,
    lida_em             DATETIME NULL,
    email_enviado_em    DATETIME NULL,
    chave               VARCHAR(80) NULL COMMENT 'Evita aviso repetido (ex.: SLA da mesma PI no mesmo dia)',
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_notif_chave (usuario_id, chave),
    KEY ix_notif_usuario (usuario_id, lida_em),
    CONSTRAINT fk_notif_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE rotinas_execucoes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rotina        VARCHAR(40) NOT NULL,
    iniciada_em   DATETIME NOT NULL,
    concluida_em  DATETIME NULL,
    sucesso       TINYINT(1) NULL,
    resultado     VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY ix_rot (rotina, iniciada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Registro das rotinas agendadas (cron)';

SET FOREIGN_KEY_CHECKS = 1;

-- >>> 002_codigo_automatico_projetos.sql
-- =====================================================================
-- CRM Comercial · Grupo Mirante de Comunicação
-- Migration 002 — código automático dos projetos especiais (PROJ-ANO-SEQUÊNCIA)
-- MySQL 8.0+ · InnoDB · utf8mb4
--
-- O código passa a ser gerado pelo CRM no formato PROJ-2026-0001: ano de cadastro + sequência
-- de 4 dígitos que recomeça em 0001 a cada ano e nunca se repete (mesma técnica da numeração das PIs:
-- linha da sequência travada com SELECT … FOR UPDATE dentro da transação que grava o projeto).
--
-- Projetos já cadastrados recebem um código novo no mesmo padrão (ano de cadastro, ordem de cadastro);
-- o código digitado antes fica guardado em codigo_anterior, para consulta e busca.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE sequencias_projeto (
    ano             SMALLINT UNSIGNED NOT NULL,
    ultimo_numero   INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Código automático dos projetos: sequência anual, travada com FOR UPDATE';

ALTER TABLE projetos_especiais
    ADD COLUMN codigo_anterior VARCHAR(20) NULL COMMENT 'Código digitado antes do código automático (migration 002)' AFTER codigo;

-- Converte os projetos existentes (inclusive excluídos, para o código nunca se repetir).
-- Passo 1: guarda o código antigo e libera a chave única com um código provisório.
UPDATE projetos_especiais SET codigo_anterior = codigo, codigo = CONCAT('TMP-', id);

-- Passo 2: código definitivo por ano de cadastro, na ordem em que foram cadastrados.
UPDATE projetos_especiais p
  JOIN (SELECT id, YEAR(created_at) AS ano, ROW_NUMBER() OVER (PARTITION BY YEAR(created_at) ORDER BY created_at, id) AS n
          FROM projetos_especiais) x ON x.id = p.id
   SET p.codigo = CONCAT('PROJ-', x.ano, '-', LPAD(x.n, 4, '0'));

-- Quem já estava no padrão não precisa de "código anterior".
UPDATE projetos_especiais SET codigo_anterior = NULL WHERE codigo_anterior = codigo;

-- Passo 3: a sequência de cada ano continua de onde os projetos existentes pararam.
INSERT INTO sequencias_projeto (ano, ultimo_numero)
SELECT YEAR(created_at), COUNT(*) FROM projetos_especiais GROUP BY YEAR(created_at);

