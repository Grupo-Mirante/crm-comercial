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
