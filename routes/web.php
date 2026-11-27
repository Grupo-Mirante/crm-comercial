<?php

declare(strict_types=1);

/*
 * Rotas do CRM. Toda rota declara a proteção exigida e é conferida no servidor:
 *   auth = usuário autenticado (com senha pessoal criada) · guest = só para quem não entrou · perm:modulo.acao = permissão
 * Requisições que alteram dados (POST) passam sempre pela verificação de CSRF.
 */

use App\Controllers\AgenciasController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\ConferenciaController;
use App\Controllers\OperacaoController;
use App\Controllers\RelatoriosController;
use App\Controllers\ReservasProjetosController;
use App\Controllers\CadastrosController;
use App\Controllers\ClientesController;
use App\Controllers\ConfiguracoesController;
use App\Controllers\DashboardController;
use App\Controllers\NotificacoesController;
use App\Controllers\PermissoesController;
use App\Controllers\PiController;
use App\Controllers\UsuariosController;
use App\Core\Router;

return function (Router $r): void {
    // ---------------------------------------------------------------- acesso
    $r->get('/login', [AuthController::class, 'loginForm'], ['guest']);
    $r->post('/login', [AuthController::class, 'login'], ['guest']);
    $r->get('/esqueci-senha', [AuthController::class, 'esqueciForm'], ['guest']);
    $r->post('/esqueci-senha', [AuthController::class, 'esqueci'], ['guest']);
    $r->get('/redefinir-senha/{token}', [AuthController::class, 'redefinirForm'], ['guest']);
    $r->post('/redefinir-senha/{token}', [AuthController::class, 'redefinir'], ['guest']);
    $r->post('/sair', [AuthController::class, 'sair'], ['auth']);
    $r->get('/primeiro-acesso', [AuthController::class, 'primeiroAcessoForm'], ['auth']);
    $r->post('/primeiro-acesso', [AuthController::class, 'primeiroAcesso'], ['auth']);
    $r->get('/minha-senha', [AuthController::class, 'minhaSenhaForm'], ['auth']);
    $r->post('/minha-senha', [AuthController::class, 'minhaSenha'], ['auth']);

    $r->group('', ['auth'], function (Router $r) {
        // ------------------------------------------------------------ painel e avisos
        $r->get('/', [DashboardController::class, 'index'], ['perm:dashboard.ver']);
        $r->get('/api/painel', [DashboardController::class, 'dados'], ['perm:dashboard.ver']);
        $r->get('/notificacoes', [NotificacoesController::class, 'index']);
        $r->post('/notificacoes/{id}/lida', [NotificacoesController::class, 'lida']);
        $r->post('/notificacoes/todas-lidas', [NotificacoesController::class, 'todasLidas']);

        // ------------------------------------------------------------ Nova PI / Gerador (Módulo 2) e Gestão de PIs (Módulo 3)
        $r->get('/pis/nova', [PiController::class, 'nova'], ['perm:pis.criar']);
        $r->get('/pis', [PiController::class, 'index']);
        $r->get('/pis/{id}', [PiController::class, 'ver']);
        $r->get('/pis/{id}/excel', [PiController::class, 'excel'], ['perm:pis.exportar']);
        $r->get('/pis/{id}/pdf', [PiController::class, 'pdf'], ['perm:pis.exportar']);
        $r->get('/pis/{id}/imprimir', [PiController::class, 'imprimir']);
        $r->post('/pis/{id}/abrir/{modo}', [PiController::class, 'abrir'], ['perm:pis.criar']);
        $r->post('/pis/{id}/acao/{acao}', [PiController::class, 'acao']);
        $r->post('/pis/rascunhos/{id}/excluir', [PiController::class, 'excluirRascunho'], ['perm:pis.criar']);
        $r->get('/api/catalogo', [PiController::class, 'catalogo'], ['perm:pis.criar']);
        $r->post('/api/pis/calcular', [PiController::class, 'calcular'], ['perm:pis.criar']);
        $r->post('/api/pis/parcelas', [PiController::class, 'parcelas'], ['perm:pis.criar']);
        $r->post('/api/pis/gerar', [PiController::class, 'gerar'], ['perm:pis.criar']);
        $r->post('/api/pis/reservar', [PiController::class, 'reservar'], ['perm:reservas.criar']);
        $r->post('/api/pis/aprovacao', [PiController::class, 'solicitarAprovacao'], ['perm:pis.criar']);
        $r->post('/api/rascunhos', [PiController::class, 'criarRascunho'], ['perm:pis.criar']);
        $r->post('/api/rascunhos/{id}', [PiController::class, 'salvarRascunho'], ['perm:pis.criar']);
        $r->post('/api/rascunhos/{id}/excluir', [PiController::class, 'excluirRascunho'], ['perm:pis.criar']);

        // ------------------------------------------------------------ anexos da PI
        $r->post('/pis/{id}/anexos', [OperacaoController::class, 'enviarAnexo']);
        $r->get('/anexos/{id}', [OperacaoController::class, 'baixarAnexo']);
        $r->post('/anexos/{id}/excluir', [OperacaoController::class, 'excluirAnexo']);

        // ------------------------------------------------------------ Conferência e fluxo (Módulo 4)
        $r->get('/conferencia', [ConferenciaController::class, 'index'], ['perm:conferencia.conferir']);
        $r->get('/conferencia/{id}', [ConferenciaController::class, 'ver'], ['perm:conferencia.conferir']);
        $r->post('/conferencia/{id}/aprovar', [ConferenciaController::class, 'aprovar'], ['perm:conferencia.conferir']);
        $r->post('/conferencia/{id}/devolver', [ConferenciaController::class, 'devolver'], ['perm:conferencia.conferir']);
        $r->get('/aprovacoes', [OperacaoController::class, 'aprovacoes'], ['perm:aprovacoes.aprovar|aprovacoes.aprovar_diretoria|pis.criar']);
        $r->post('/aprovacoes/{id}/responder', [OperacaoController::class, 'responder'], ['perm:aprovacoes.aprovar|aprovacoes.aprovar_diretoria']);
        $r->get('/faturamento', [OperacaoController::class, 'faturamento'], ['perm:financeiro.faturar|operacao.finalizar|operacao.programar']);

        // ------------------------------------------------------------ reservas (Módulo 8) e projetos especiais (Módulo 7)
        $r->get('/reservas', [ReservasProjetosController::class, 'reservas'], ['perm:reservas.ver']);
        $r->post('/reservas/{id}/liberar', [ReservasProjetosController::class, 'liberar'], ['perm:reservas.criar']);
        $r->post('/reservas/{id}/abrir', [ReservasProjetosController::class, 'abrir'], ['perm:reservas.criar']);
        $r->get('/projetos', [ReservasProjetosController::class, 'projetos'], ['perm:projetos.ver']);
        $r->get('/projetos/novo', [ReservasProjetosController::class, 'formProjeto'], ['perm:projetos.gerenciar']);
        $r->post('/projetos', [ReservasProjetosController::class, 'salvarProjeto'], ['perm:projetos.gerenciar']);
        $r->get('/projetos/{id}', [ReservasProjetosController::class, 'projeto'], ['perm:projetos.ver']);
        $r->get('/projetos/{id}/editar', [ReservasProjetosController::class, 'formProjeto'], ['perm:projetos.gerenciar']);
        $r->post('/projetos/{id}', [ReservasProjetosController::class, 'salvarProjeto'], ['perm:projetos.gerenciar']);
        $r->post('/projetos/{id}/excluir', [ReservasProjetosController::class, 'excluirProjeto'], ['perm:projetos.gerenciar']);

        // ------------------------------------------------------------ relatórios (Módulo 10) e backup
        $r->get('/relatorios', [RelatoriosController::class, 'index'], ['perm:relatorios.ver']);
        $r->get('/relatorios/{chave}', [RelatoriosController::class, 'ver'], ['perm:relatorios.ver']);
        $r->get('/relatorios/{chave}/excel', [RelatoriosController::class, 'exportar'], ['perm:relatorios.exportar']);
        $r->get('/backup', [BackupController::class, 'index'], ['perm:backup.gerenciar']);
        $r->post('/backup', [BackupController::class, 'gerar'], ['perm:backup.gerenciar']);
        $r->get('/backup/{nome}', [BackupController::class, 'baixar'], ['perm:backup.gerenciar']);

        // ------------------------------------------------------------ clientes (Módulo 5)
        $r->get('/clientes', [ClientesController::class, 'index'], ['perm:clientes.ver']);
        $r->get('/clientes/novo', [ClientesController::class, 'novo'], ['perm:clientes.editar']);
        $r->post('/clientes', [ClientesController::class, 'criar'], ['perm:clientes.editar']);
        $r->get('/clientes/{id}', [ClientesController::class, 'ver'], ['perm:clientes.ver']);
        $r->get('/clientes/{id}/editar', [ClientesController::class, 'editar'], ['perm:clientes.editar']);
        $r->post('/clientes/{id}', [ClientesController::class, 'atualizar'], ['perm:clientes.editar']);
        $r->post('/clientes/{id}/excluir', [ClientesController::class, 'excluir'], ['perm:clientes.excluir']);
        $r->get('/api/clientes', [ClientesController::class, 'buscar'], ['perm:clientes.ver|pis.criar']);

        // ------------------------------------------------------------ agências (Módulo 6)
        $r->get('/agencias', [AgenciasController::class, 'index'], ['perm:agencias.ver']);
        $r->get('/agencias/nova', [AgenciasController::class, 'nova'], ['perm:agencias.editar']);
        $r->post('/agencias', [AgenciasController::class, 'criar'], ['perm:agencias.editar']);
        $r->get('/agencias/{id}', [AgenciasController::class, 'ver'], ['perm:agencias.ver']);
        $r->get('/agencias/{id}/editar', [AgenciasController::class, 'editar'], ['perm:agencias.editar']);
        $r->post('/agencias/{id}', [AgenciasController::class, 'atualizar'], ['perm:agencias.editar']);
        $r->post('/agencias/{id}/excluir', [AgenciasController::class, 'excluir'], ['perm:agencias.excluir']);
        $r->get('/api/agencias', [AgenciasController::class, 'buscar'], ['perm:agencias.ver|pis.criar']);

        // ------------------------------------------------------------ usuários e permissões (Módulo 9)
        $r->get('/usuarios', [UsuariosController::class, 'index'], ['perm:usuarios.ver']);
        $r->get('/usuarios/novo', [UsuariosController::class, 'novo'], ['perm:usuarios.gerenciar']);
        $r->post('/usuarios', [UsuariosController::class, 'criar'], ['perm:usuarios.gerenciar']);
        $r->get('/usuarios/{id}', [UsuariosController::class, 'ver'], ['perm:usuarios.ver']);
        $r->get('/usuarios/{id}/editar', [UsuariosController::class, 'editar'], ['perm:usuarios.gerenciar']);
        $r->post('/usuarios/{id}', [UsuariosController::class, 'atualizar'], ['perm:usuarios.gerenciar']);
        $r->post('/usuarios/{id}/ativo', [UsuariosController::class, 'ativo'], ['perm:usuarios.gerenciar']);
        $r->post('/usuarios/{id}/redefinir-senha', [UsuariosController::class, 'redefinirSenha'], ['perm:usuarios.gerenciar']);
        $r->post('/usuarios/{id}/encerrar-sessoes', [UsuariosController::class, 'encerrarSessoes'], ['perm:usuarios.gerenciar']);
        $r->get('/permissoes', [PermissoesController::class, 'index'], ['perm:permissoes.gerenciar']);
        $r->post('/permissoes', [PermissoesController::class, 'salvar'], ['perm:permissoes.gerenciar']);

        // ------------------------------------------------------------ tabelas comerciais e veículos
        $r->get('/cadastros', [CadastrosController::class, 'index'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/empresas', [CadastrosController::class, 'empresas'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/empresas/{id}', [CadastrosController::class, 'salvarEmpresa'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/veiculos', [CadastrosController::class, 'veiculos'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/veiculos/{id}', [CadastrosController::class, 'veiculo'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/veiculos/{id}', [CadastrosController::class, 'salvarVeiculo'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/tabelas', [CadastrosController::class, 'tabelas'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/tabelas', [CadastrosController::class, 'salvarTabela'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/tabelas/copiar-ano', [CadastrosController::class, 'copiarAno'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/tabelas/{id}/precos', [CadastrosController::class, 'precos'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/tabelas/{id}/precos', [CadastrosController::class, 'salvarPrecos'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/programas', [CadastrosController::class, 'programas'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/programas/novo', [CadastrosController::class, 'programa'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/programas/{id}', [CadastrosController::class, 'programa'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/programas', [CadastrosController::class, 'salvarPrograma'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/programas/{id}', [CadastrosController::class, 'salvarPrograma'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/formatos', [CadastrosController::class, 'formatos'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/formatos/novo', [CadastrosController::class, 'formato'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/formatos/{id}', [CadastrosController::class, 'formato'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/formatos', [CadastrosController::class, 'salvarFormato'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/formatos/{id}', [CadastrosController::class, 'salvarFormato'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/indices', [CadastrosController::class, 'indices'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/indices', [CadastrosController::class, 'salvarIndices'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/clausulas', [CadastrosController::class, 'clausulas'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/clausulas', [CadastrosController::class, 'salvarClausulas'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/feriados', [CadastrosController::class, 'feriados'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/feriados', [CadastrosController::class, 'salvarFeriado'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/feriados/excluir', [CadastrosController::class, 'excluirFeriado'], ['perm:cadastros.gerenciar']);
        $r->get('/cadastros/listas', [CadastrosController::class, 'listas'], ['perm:cadastros.gerenciar']);
        $r->post('/cadastros/listas', [CadastrosController::class, 'salvarLista'], ['perm:cadastros.gerenciar']);

        // ------------------------------------------------------------ configurações e auditoria
        $r->get('/configuracoes', [ConfiguracoesController::class, 'index'], ['perm:configuracoes.gerenciar']);
        $r->post('/configuracoes', [ConfiguracoesController::class, 'salvar'], ['perm:configuracoes.gerenciar']);
        $r->get('/auditoria', [AuditoriaController::class, 'index'], ['perm:auditoria.ver']);
        $r->get('/auditoria/{id}', [AuditoriaController::class, 'ver'], ['perm:auditoria.ver']);
    });
};
