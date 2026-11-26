<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auditoria;
use App\Services\DashboardService;
use App\Services\RelatorioService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Módulo 10 — Relatórios com filtros, visões diária/semanal/mensal e exportação Excel. */
final class RelatoriosController extends Controller
{
    public function index(Request $r): Response
    {
        $grupos = [];
        foreach (RelatorioService::catalogo() as $k => $c) {
            $grupos[$c['grupo']][$k] = $c;
        }
        return $this->view('relatorios/index', ['titulo' => 'Relatórios', 'grupos' => $grupos]);
    }

    public function ver(Request $r): Response
    {
        $chave = $r->param('chave');
        $cat = RelatorioService::catalogo();
        $f = RelatorioService::filtros($r->query);
        $res = RelatorioService::executar($chave, $f);
        return $this->view('relatorios/ver', ['titulo' => $cat[$chave]['titulo'], 'chave' => $chave, 'info' => $cat[$chave], 'f' => $f, 'res' => $res, 'opcoes' => DashboardService::opcoesFiltros()]);
    }

    public function exportar(Request $r): Response
    {
        $this->exigir('relatorios.exportar');
        $chave = $r->param('chave');
        $cat = RelatorioService::catalogo();
        $f = RelatorioService::filtros($r->query);
        $res = RelatorioService::executar($chave, $f);
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', '', $cat[$chave]['titulo']), 0, 31));
        $ws->setShowGridlines(false);
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $cols = $res['colunas'];
        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($cols)));
        $ws->setCellValue('A1', 'Grupo Mirante de Comunicação · ' . $cat[$chave]['titulo']);
        $ws->mergeCells("A1:{$ultima}1");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1F3A5F');
        $periodo = (($cat[$chave]['periodo'] ?? true) ? 'Período: ' . data_br($f['de']) . ' a ' . data_br($f['ate']) . ' · visão ' . $f['visao'] : 'Situação atual');
        $ws->setCellValueExplicit('A2', $periodo . ' · gerado em ' . date('d/m/Y H:i') . ' por ' . (\App\Services\Auth::user()['nome'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->mergeCells("A2:{$ultima}2");
        $ws->getStyle('A2')->getFont()->setSize(8)->getColor()->setRGB('595959');
        $lin = 4;
        foreach ($cols as $i => [, $rot]) {
            $ws->setCellValue([$i + 1, $lin], $rot);
        }
        $ws->getStyle("A{$lin}:{$ultima}{$lin}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ws->getStyle("A{$lin}:{$ultima}{$lin}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');
        $ini = $lin + 1;
        foreach ($res['linhas'] as $l) {
            $lin++;
            foreach ($cols as $i => [$k, , $tipo]) {
                $v = $l[$k] ?? null;
                $v = match ($tipo) {
                    'money', 'dec', 'pct' => $v === null || $v === '' ? null : (float) $v,
                    'int' => $v === null || $v === '' ? null : (int) $v,
                    'date' => data_br($v),
                    'datetime' => data_hora_br($v),
                    default => (string) ($v ?? ''),
                };
                if (is_string($v)) {
                    // texto digitado (cliente, agência…) nunca vira fórmula no Excel
                    $ws->setCellValueExplicit([$i + 1, $lin], $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $ws->setCellValue([$i + 1, $lin], $v);
                }
            }
        }
        if ($res['totais'] && $res['linhas']) {
            $lin++;
            $ws->setCellValue([1, $lin], 'Total');
            foreach ($cols as $i => [$k, , $tipo]) {
                if (array_key_exists($k, $res['totais']) && $i > 0) {
                    $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $ws->setCellValue([$i + 1, $lin], $k === 'ticket' ? $res['totais'][$k] : "=SUM({$c}{$ini}:{$c}" . ($lin - 1) . ')');
                }
            }
            $ws->getStyle("A{$lin}:{$ultima}{$lin}")->getFont()->setBold(true);
            $ws->getStyle("A{$lin}:{$ultima}{$lin}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EBF1');
        }
        foreach ($cols as $i => [, , $tipo]) {
            $c = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $fmt = match ($tipo) { 'money' => '"R$" #,##0.00', 'pct' => '0.0%', 'int' => '#,##0', 'dec' => '#,##0.0', default => null };
            if ($fmt) {
                $ws->getStyle("{$c}{$ini}:{$c}{$lin}")->getNumberFormat()->setFormatCode($fmt);
            }
            $ws->getColumnDimension($c)->setAutoSize(true);
        }
        if ($res['nota']) {
            $ws->setCellValue('A' . ($lin + 2), $res['nota']);
        }
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        $ws->freezePane('A5');
        $arq = App::root() . '/storage/exports/' . bin2hex(random_bytes(8)) . '.xlsx';
        (new Xlsx($ss))->save($arq);
        $nome = 'Relatorio_' . slug_arquivo($cat[$chave]['titulo'], 40) . '_' . str_replace('-', '', $f['de']) . '_' . str_replace('-', '', $f['ate']) . '.xlsx';
        Auditoria::registrar('relatorio_exportado', 'relatorio', $chave, 'Exportou ' . $cat[$chave]['titulo'] . ' (' . data_br($f['de']) . ' a ' . data_br($f['ate']) . ')');
        return Response::download($arq, $nome, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
    }
}
