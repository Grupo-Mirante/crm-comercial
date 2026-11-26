<?php

declare(strict_types=1);

/*
 * Menu lateral. Cada item aparece só para quem tem a permissão indicada e só quando a tela existe no sistema.
 * 'perm' aceita alternativas separadas por "|".
 */
return [
    ['grupo' => null, 'itens' => [
        ['rotulo' => 'Painel', 'icone' => 'speedometer2', 'url' => '/', 'perm' => 'dashboard.ver'],
    ]],
    ['grupo' => 'Vendas', 'itens' => [
        ['rotulo' => 'Nova PI', 'icone' => 'file-earmark-plus', 'url' => '/pis/nova', 'perm' => 'pis.criar', 'destaque' => true],
        ['rotulo' => 'PIs', 'icone' => 'files', 'url' => '/pis', 'perm' => 'pis.ver_todas|pis.ver_equipe|pis.criar'],
        ['rotulo' => 'Reservas 24h', 'icone' => 'hourglass-split', 'url' => '/reservas', 'perm' => 'reservas.ver'],
        ['rotulo' => 'Projetos especiais', 'icone' => 'stars', 'url' => '/projetos', 'perm' => 'projetos.ver'],
        ['rotulo' => 'Aprovações', 'icone' => 'check2-square', 'url' => '/aprovacoes', 'perm' => 'aprovacoes.aprovar|aprovacoes.aprovar_diretoria|pis.criar', 'contador' => 'aprovacoes'],
    ]],
    ['grupo' => 'Operação', 'itens' => [
        ['rotulo' => 'Conferência', 'icone' => 'clipboard-check', 'url' => '/conferencia', 'perm' => 'conferencia.conferir', 'contador' => 'conferencia'],
        ['rotulo' => 'Faturamento', 'icone' => 'receipt', 'url' => '/faturamento', 'perm' => 'financeiro.faturar|operacao.finalizar'],
    ]],
    ['grupo' => 'Cadastros', 'itens' => [
        ['rotulo' => 'Clientes', 'icone' => 'building', 'url' => '/clientes', 'perm' => 'clientes.ver'],
        ['rotulo' => 'Agências', 'icone' => 'briefcase', 'url' => '/agencias', 'perm' => 'agencias.ver'],
    ]],
    ['grupo' => 'Gestão', 'itens' => [
        ['rotulo' => 'Relatórios', 'icone' => 'bar-chart-line', 'url' => '/relatorios', 'perm' => 'relatorios.ver'],
        ['rotulo' => 'Usuários', 'icone' => 'people', 'url' => '/usuarios', 'perm' => 'usuarios.ver'],
        ['rotulo' => 'Permissões', 'icone' => 'shield-lock', 'url' => '/permissoes', 'perm' => 'permissoes.gerenciar'],
        ['rotulo' => 'Tabelas e veículos', 'icone' => 'sliders', 'url' => '/cadastros', 'perm' => 'cadastros.gerenciar'],
        ['rotulo' => 'Configurações', 'icone' => 'gear', 'url' => '/configuracoes', 'perm' => 'configuracoes.gerenciar'],
        ['rotulo' => 'Auditoria', 'icone' => 'journal-text', 'url' => '/auditoria', 'perm' => 'auditoria.ver'],
        ['rotulo' => 'Backup', 'icone' => 'database-down', 'url' => '/backup', 'perm' => 'backup.gerenciar'],
    ]],
];
