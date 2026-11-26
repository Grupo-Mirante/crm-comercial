-- =====================================================================
-- Perfis e permissões iniciais (RN-72, RN-73, RN-74)
-- O administrador pode alterar a matriz em Usuários › Permissões.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO perfis (id, codigo, nome, descricao, ordem) VALUES
 (1, 'admin',      'Administrador',       'Acesso total, cadastros, usuários, permissões e configurações.', 1),
 (2, 'diretor',    'Diretor',             'Visualização e aprovação geral; aprova o que passa da alçada do gestor.', 2),
 (3, 'gerente',    'Gerente comercial',   'PIs da equipe, aprovações de desconto e bonificação, devolução e cancelamento.', 3),
 (4, 'conf',       'Conferência / OPEC',  'Confere, aprova ou devolve PIs e acompanha a operação até a veiculação.', 4),
 (5, 'exec',       'Executivo de contas', 'Cria e acompanha as próprias PIs.', 5),
 (6, 'vend',       'Vendedor',            'Opera as próprias vendas conforme as permissões configuradas.', 6),
 (7, 'financeiro', 'Financeiro',          'Cadastro e crédito de clientes, faturamento e encerramento das PIs.', 7);

INSERT INTO permissoes (modulo, acao, descricao, ordem) VALUES
 ('dashboard',     'ver',               'Ver o painel inicial', 10),
 ('pis',           'criar',             'Criar, gerar e reservar PIs (RN-74: a Conferência não gera PI)', 20),
 ('pis',           'ver_todas',         'Ver PIs de toda a equipe (RN-73)', 21),
 ('pis',           'ver_equipe',        'Ver PIs da própria equipe (RN-73)', 22),
 ('pis',           'editar_outras',     'Abrir e alterar PIs de outros executivos dentro do que pode ver', 23),
 ('pis',           'cancelar',          'Cancelar qualquer PI que pode ver (o executivo cancela só o próprio Rascunho)', 24),
 ('pis',           'substituir',        'Cancela/substitui e renovar as PIs que pode alterar', 25),
 ('pis',           'exportar',          'Baixar a PI em Excel e imprimir', 26),
 ('conferencia',   'conferir',          'Aprovar e devolver PIs na Conferência', 30),
 ('operacao',      'programar',         'Liberar para programação e marcar início da veiculação', 31),
 ('operacao',      'finalizar',         'Marcar veiculação finalizada (pronta para faturamento)', 32),
 ('financeiro',    'faturar',           'Registrar faturamento e encerramento da PI', 33),
 ('aprovacoes',    'aprovar',           'Aprovar desconto, bonificação, preço manual e urgência da equipe', 40),
 ('aprovacoes',    'aprovar_diretoria', 'Aprovar o que passa da alçada do gestor', 41),
 ('clientes',      'ver',               'Consultar clientes', 50),
 ('clientes',      'editar',            'Cadastrar e editar clientes', 51),
 ('clientes',      'excluir',           'Desativar ou excluir clientes', 52),
 ('agencias',      'ver',               'Consultar agências', 53),
 ('agencias',      'editar',            'Cadastrar e editar agências', 54),
 ('agencias',      'excluir',           'Desativar ou excluir agências', 55),
 ('projetos',      'ver',               'Consultar projetos especiais e o acompanhamento de cotas', 60),
 ('projetos',      'gerenciar',         'Cadastrar e alterar projetos especiais', 61),
 ('reservas',      'ver',               'Ver as reservas ativas', 62),
 ('reservas',      'criar',             'Reservar por 24 horas', 63),
 ('relatorios',    'ver',               'Ver relatórios (executivo e vendedor veem só os próprios números)', 70),
 ('relatorios',    'ranking_nominal',   'Ver o ranking nominal completo de devoluções', 71),
 ('relatorios',    'exportar',          'Exportar relatórios em Excel', 72),
 ('usuarios',      'ver',               'Ver usuários', 80),
 ('usuarios',      'gerenciar',         'Cadastrar usuários, redefinir senhas e ativar/desativar (RN-75)', 81),
 ('permissoes',    'gerenciar',         'Alterar a matriz de permissões por perfil', 82),
 ('cadastros',     'gerenciar',         'Empresas, veículos, tabelas de preços, programas, formatos, cláusulas, feriados e listas', 83),
 ('configuracoes', 'gerenciar',         'Configurações do sistema (alçadas, prazos, segurança)', 84),
 ('auditoria',     'ver',               'Consultar o log de auditoria', 85),
 ('backup',        'gerenciar',         'Gerar e baixar backups do banco', 86);

-- Administrador: tudo
INSERT INTO perfil_permissoes (perfil_id, permissao_id) SELECT 1, id FROM permissoes;

-- Matriz por perfil (RN-73, RN-74 e permissões do Gerador original)
INSERT INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, m.id FROM perfis p JOIN permissoes m ON CONCAT(m.modulo, '.', m.acao) IN (
    'dashboard.ver','pis.criar','pis.ver_todas','pis.editar_outras','pis.cancelar','pis.substituir','pis.exportar',
    'conferencia.conferir','operacao.programar','operacao.finalizar','financeiro.faturar',
    'aprovacoes.aprovar','aprovacoes.aprovar_diretoria',
    'clientes.ver','clientes.editar','clientes.excluir','agencias.ver','agencias.editar','agencias.excluir',
    'projetos.ver','projetos.gerenciar','reservas.ver','reservas.criar',
    'relatorios.ver','relatorios.ranking_nominal','relatorios.exportar','usuarios.ver','auditoria.ver')
WHERE p.codigo = 'diretor';

INSERT INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, m.id FROM perfis p JOIN permissoes m ON CONCAT(m.modulo, '.', m.acao) IN (
    'dashboard.ver','pis.criar','pis.ver_equipe','pis.editar_outras','pis.cancelar','pis.substituir','pis.exportar',
    'conferencia.conferir','aprovacoes.aprovar',
    'clientes.ver','clientes.editar','clientes.excluir','agencias.ver','agencias.editar','agencias.excluir',
    'projetos.ver','reservas.ver','reservas.criar',
    'relatorios.ver','relatorios.ranking_nominal','relatorios.exportar')
WHERE p.codigo = 'gerente';

INSERT INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, m.id FROM perfis p JOIN permissoes m ON CONCAT(m.modulo, '.', m.acao) IN (
    'dashboard.ver','pis.ver_todas','pis.exportar',
    'conferencia.conferir','operacao.programar','operacao.finalizar',
    'clientes.ver','agencias.ver','projetos.ver','reservas.ver',
    'relatorios.ver','relatorios.ranking_nominal','relatorios.exportar')
WHERE p.codigo = 'conf';

INSERT INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, m.id FROM perfis p JOIN permissoes m ON CONCAT(m.modulo, '.', m.acao) IN (
    'dashboard.ver','pis.criar','pis.substituir','pis.exportar',
    'clientes.ver','clientes.editar','agencias.ver','agencias.editar',
    'projetos.ver','reservas.ver','reservas.criar','relatorios.ver')
WHERE p.codigo IN ('exec', 'vend');

INSERT INTO perfil_permissoes (perfil_id, permissao_id)
SELECT p.id, m.id FROM perfis p JOIN permissoes m ON CONCAT(m.modulo, '.', m.acao) IN (
    'dashboard.ver','pis.ver_todas','pis.exportar','financeiro.faturar',
    'clientes.ver','clientes.editar','agencias.ver','agencias.editar',
    'projetos.ver','relatorios.ver','relatorios.exportar')
WHERE p.codigo = 'financeiro';
