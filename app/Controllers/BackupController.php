<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auditoria;
use App\Services\BackupService;
use App\Services\Configuracao;

/** Backup pelo navegador (gerar e baixar). A restauração é feita só pelo console do servidor, com confirmação. */
final class BackupController extends Controller
{
    public function index(Request $r): Response
    {
        return $this->view('backup/index', [
            'titulo' => 'Backup', 'lista' => BackupService::listar(), 'pasta' => BackupService::pasta(), 'ultimo' => Configuracao::texto('backup_ultima_execucao'),
            'rotinas' => Database::all('SELECT rotina, iniciada_em, concluida_em, sucesso, resultado FROM rotinas_execucoes ORDER BY id DESC LIMIT 10'),
        ]);
    }

    public function gerar(Request $r): Response
    {
        $arq = $r->str('tipo') === 'anexos' ? BackupService::gerarAnexos() : BackupService::gerar('manual');
        return $this->voltar('/backup', 'Backup gerado: ' . basename($arq) . '. Guarde uma cópia fora do servidor.');
    }

    public function baixar(Request $r): Response
    {
        $nome = $r->param('nome');
        $arq = BackupService::caminho($nome);
        Auditoria::registrar('backup_baixado', 'backup', $nome, 'Download do backup ' . $nome);
        return Response::download($arq, $nome, 'application/gzip');
    }
}
