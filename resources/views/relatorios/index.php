<div class="page-head"><div><h1>Relatórios</h1><p>Todos os números saem do registro de PIs, sem planilha paralela. Cada relatório tem visão diária, semanal ou mensal, filtros e exportação para Excel.
    <?php if (!can('relatorios.ranking_nominal')): ?> Nos relatórios por executivo você vê só os próprios números.<?php endif; ?></p></div></div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><a class="kpi" href="<?= e(url('/relatorios/pis_devolvidas', ['visao' => 'diaria'])) ?>"><span class="kpi-icon"><i class="bi bi-sunrise"></i></span><div class="kpi-label">Diário · 9h</div><div class="kpi-value" style="font-size:1.05rem">Fila de PIs</div><div class="kpi-sub">Conferência: aguardando, devolvidas, início próximo (veja também a tela Conferência)</div></a></div>
    <div class="col-md-4"><a class="kpi" href="<?= e(url('/relatorios/ranking_devolucoes', ['visao' => 'semanal'])) ?>"><span class="kpi-icon"><i class="bi bi-calendar-week"></i></span><div class="kpi-label">Semanal · segunda-feira</div><div class="kpi-value" style="font-size:1.05rem">Painel da semana</div><div class="kpi-sub">Vendas, taxa de devolução, ranking e 3 motivos mais frequentes</div></a></div>
    <div class="col-md-4"><a class="kpi" href="<?= e(url('/relatorios/vendas_empresa', ['visao' => 'mensal'])) ?>"><span class="kpi-icon"><i class="bi bi-calendar-month"></i></span><div class="kpi-label">Mensal · até o 3º dia útil</div><div class="kpi-value" style="font-size:1.05rem">Relatório mensal</div><div class="kpi-sub">Indicadores por empresa, evolução, faturamento</div></a></div>
</div>
<?php foreach ($grupos as $g => $lista): ?>
    <h2 class="mb-2 mt-3"><?= e($g) ?></h2>
    <div class="row g-3">
        <?php foreach ($lista as $k => $c): ?>
            <div class="col-md-6 col-xl-4"><a class="kpi" href="<?= e(url('/relatorios/' . $k)) ?>"><span class="kpi-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
                <div class="kpi-value" style="font-size:1rem"><?= e($c['titulo']) ?></div><div class="kpi-sub"><?= e($c['descricao']) ?></div></a></div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
