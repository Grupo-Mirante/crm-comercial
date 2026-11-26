<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\DashboardService;

/** Módulo 1 — Painel inicial personalizado por perfil, com indicadores e gráficos do MySQL. */
final class DashboardController extends Controller
{
    public function index(Request $r): Response
    {
        $f = DashboardService::filtros($r->query);
        return $this->view('dashboard/index', [
            'titulo' => 'Painel',
            'f' => $f,
            'k' => DashboardService::indicadores($f),
            'g' => DashboardService::graficos($f),
            'tarefas' => DashboardService::tarefas(),
            'opcoes' => DashboardService::opcoesFiltros(),
            'js' => ['vendor/chartjs/chart.umd.min.js', 'js/dashboard.js'],
        ]);
    }

    /** Mesmos dados em JSON (atualização sem recarregar). */
    public function dados(Request $r): Response
    {
        $f = DashboardService::filtros($r->query);
        return $this->json(['ok' => true, 'indicadores' => DashboardService::indicadores($f), 'graficos' => DashboardService::graficos($f)]);
    }
}
