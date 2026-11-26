-- =====================================================================
-- Configurações padrão. Valores do Projeto de Processos Comerciais e do Gerador de PI.
-- Itens que a diretoria/jurídico ainda vão definir ficam VAZIOS (sem valor inventado).
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO configuracoes (chave, valor, tipo, grupo, descricao, editavel) VALUES
 ('empresa_grupo',                  'Grupo Mirante de Comunicação', 'texto', 'Geral', 'Nome do grupo no cabeçalho da PI e dos relatórios', 1),
 ('praca_padrao_cidade',            'São Luís - MA', 'texto', 'Geral', 'Cidade usada na assinatura da PI quando a praça não define outra', 1),

 -- Alçadas (pendência: "a definir pela diretoria na Fase 1")
 ('alcada_gestor_desconto_pct',     '', 'decimal', 'Alçadas', 'Desconto máximo (%) que o gestor aprova sozinho. Vazio = sem limite (todo desconto vai só ao gestor)', 1),
 ('alcada_gestor_bonificacao_valor','', 'decimal', 'Alçadas', 'Valor máximo bonificado (R$) que o gestor aprova sozinho. Vazio = sem limite', 1),

 -- Prazos (SLAs) — proposta inicial do documento
 ('sla_envio_dias_uteis',           '5',  'inteiro', 'Prazos', 'PI de mídia comum: dias úteis mínimos entre o envio à Conferência e a 1ª veiculação', 1),
 ('sla_envio_entregas_dias_uteis',  '10', 'inteiro', 'Prazos', 'PI com entregas especiais (redes, app, evento): dias úteis mínimos antes da 1ª veiculação', 1),
 ('sla_envio_sorteio_dias_uteis',   '',   'inteiro', 'Prazos', 'PI com promoção ou sorteio: prazo definido pelo jurídico (vazio = usa o prazo de entregas especiais)', 1),
 ('sla_conferencia_hora_corte',     '15:00', 'hora', 'Prazos', 'PIs recebidas até este horário são conferidas no mesmo dia; as demais até 12h do dia útil seguinte', 1),
 ('sla_conferencia_hora_limite',    '12:00', 'hora', 'Prazos', 'Horário limite no dia útil seguinte para PIs recebidas após o corte', 1),
 ('sla_correcao_dias_uteis',        '1',  'inteiro', 'Prazos', 'Prazo do executivo para corrigir PI devolvida', 1),
 ('sla_aprovacao_gestor_dias_uteis','1',  'inteiro', 'Prazos', 'Prazo do gestor para aprovar desconto ou bonificação', 1),
 ('sla_opec_grade_dias_uteis',      '2',  'inteiro', 'Prazos', 'Reserva de grade e mapa de inserção após a aprovação', 1),
 ('sla_alteracao_horas_uteis',      '72', 'inteiro', 'Prazos', 'Antecedência mínima para PI de alteração ou suspensão (cláusula 2)', 1),
 ('sla_alerta_inicio_dias',         '5',  'inteiro', 'Prazos', 'Fila diária: alertar PIs com início nos próximos N dias sem aprovação', 1),

 -- Reservas e projetos
 ('reserva_validade_horas',         '24', 'inteiro', 'Reservas', 'RN-67: validade da reserva (não é renovada ao atualizar)', 1),

 -- Pagamento
 ('parcelas_maximo',                '12', 'inteiro', 'Financeiro', 'RN-43: número máximo de parcelas do atalho Gerar parcelas', 1),
 ('rn20_considerar_feriados',       '0',  'booleano', 'Financeiro', 'RN-20: último dia útil considera feriados cadastrados (0 = só fim de semana, como no Gerador original)', 1),

 -- Jurídico
 ('multa_rescisao_texto',           '',   'texto_longo', 'Jurídico', 'Texto da multa por rescisão antecipada (cláusula 5). Vazio = "[texto a definir pelo jurídico]"', 1),

 -- Segurança
 ('login_max_tentativas',           '5',  'inteiro', 'Segurança', 'RN-77: senhas erradas seguidas antes do bloqueio temporário', 1),
 ('login_bloqueio_segundos',        '30', 'inteiro', 'Segurança', 'RN-77: duração do bloqueio após as tentativas', 1),
 ('login_max_tentativas_ip_15min',  '30', 'inteiro', 'Segurança', 'Tentativas inválidas por endereço IP em 15 minutos antes de bloquear o IP', 1),
 ('senha_tamanho_minimo',           '8',  'inteiro', 'Segurança', 'RN-75: tamanho mínimo da senha pessoal', 1),
 ('senha_token_minutos',            '60', 'inteiro', 'Segurança', 'Validade do link de recuperação de senha', 1),

 -- Notificações e rotinas
 ('notificar_por_email',            '1',  'booleano', 'Notificações', 'Enviar por e-mail as notificações (quando o SMTP estiver configurado)', 1),
 ('fila_diaria_hora',               '09:00', 'hora', 'Notificações', 'Horário da fila diária de PIs (relatório diário)', 1),
 ('backup_ultima_execucao',         '',   'texto', 'Sistema', 'Data do último backup automático', 0),
 ('instalado_em',                   '',   'texto', 'Sistema', 'Data da instalação', 0),
 ('versao_sistema',                 '1.0.0', 'texto', 'Sistema', 'Versão do CRM', 0);

-- Feriados nacionais (Lei 662/1949, Lei 6.802/1980, Lei 14.759/2023 e datas móveis).
-- Feriados estaduais e municipais devem ser cadastrados pelo administrador.
INSERT INTO feriados (data, descricao, abrangencia) VALUES
 ('2026-01-01', 'Confraternização Universal', 'Nacional'),
 ('2026-04-03', 'Sexta-feira Santa', 'Nacional'),
 ('2026-04-21', 'Tiradentes', 'Nacional'),
 ('2026-05-01', 'Dia do Trabalho', 'Nacional'),
 ('2026-09-07', 'Independência do Brasil', 'Nacional'),
 ('2026-10-12', 'Nossa Senhora Aparecida', 'Nacional'),
 ('2026-11-02', 'Finados', 'Nacional'),
 ('2026-11-15', 'Proclamação da República', 'Nacional'),
 ('2026-11-20', 'Dia Nacional de Zumbi e da Consciência Negra', 'Nacional'),
 ('2026-12-25', 'Natal', 'Nacional'),
 ('2027-01-01', 'Confraternização Universal', 'Nacional'),
 ('2027-03-26', 'Sexta-feira Santa', 'Nacional'),
 ('2027-04-21', 'Tiradentes', 'Nacional'),
 ('2027-05-01', 'Dia do Trabalho', 'Nacional'),
 ('2027-09-07', 'Independência do Brasil', 'Nacional'),
 ('2027-10-12', 'Nossa Senhora Aparecida', 'Nacional'),
 ('2027-11-02', 'Finados', 'Nacional'),
 ('2027-11-15', 'Proclamação da República', 'Nacional'),
 ('2027-11-20', 'Dia Nacional de Zumbi e da Consciência Negra', 'Nacional'),
 ('2027-12-25', 'Natal', 'Nacional');
