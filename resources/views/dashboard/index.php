<?php
/** @var array $f @var array $k @var array $g @var array $tarefas @var array $opcoes */
$pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 1, ',', '.') . '%';
$hora = (int) date('G');
$saud = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$primeiro = explode(' ', trim((string) ($usuario['nome'] ?? '')))[0] ?? '';
$q = fn (array $extra) => url('/pis', array_filter(array_merge(['de' => $f['de'], 'ate' => $f['ate']], $extra)));
?>
<div class="page-head">
    <div>
        <h1><?= e($saud) ?>, <?= e($primeiro) ?>.</h1>
        <p><?= e($usuario['perfil_nome'] ?? '') ?> · período de <?= e(data_br($f['de'])) ?> a <?= e(data_br($f['ate'])) ?><?= ($escopo = \App\Services\Auth::escopoPis()) === 'proprias' ? ' · suas PIs' : ($escopo === 'equipe' ? ' · você e sua equipe' : ' · todo o grupo') ?></p>
    </div>
</div>

<form class="filters" method="get" action="<?= e(url('/')) ?>">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label" for="de">De</label><input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="ate">Até</label><input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="empresa">Empresa</label>
            <select class="form-select form-select-sm" id="empresa" name="empresa"><option value="">Todas</option>
                <?php foreach ($opcoes['empresas'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['empresa'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="veiculo">Veículo</label>
            <select class="form-select form-select-sm" id="veiculo" name="veiculo"><option value="">Todos</option>
                <?php foreach ($opcoes['veiculos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['veiculo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <?php if ($opcoes['executivos']): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="executivo">Executivo</label>
            <select class="form-select form-select-sm" id="executivo" name="executivo"><option value="">Todos</option>
                <?php foreach ($opcoes['executivos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['executivo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if ($opcoes['gestores']): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="gestor">Gestor</label>
            <select class="form-select form-select-sm" id="gestor" name="gestor"><option value="">Todos</option>
                <?php foreach ($opcoes['gestores'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['gestor'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label" for="situacao">Situação</label>
            <select class="form-select form-select-sm" id="situacao" name="situacao"><option value="">Todas</option>
                <?php foreach (\App\Services\DashboardService::SITUACOES as $s): ?><option<?= $f['situacao'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-auto ms-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
            <a class="btn btn-light btn-sm" href="<?= e(url('/')) ?>">Mês atual</a>
        </div>
    </div>
</form>

<?php
// Com pendências: "O que precisa da sua atenção" numa coluna estreita à esquerda e os indicadores à direita
// (abaixo de 1200px as duas partes se empilham). Sem pendências, os indicadores ocupam a largura toda.
$colKpis = $tarefas ? 'col-xl-9' : 'col-12';
$colsGrandes = $tarefas ? 'row-cols-2' : 'row-cols-2 row-cols-xl-4';
$colsPequenos = $tarefas ? 'row-cols-2 row-cols-md-3 row-cols-xl-4' : 'row-cols-2 row-cols-md-3 row-cols-xl-6';
?>
<div class="row g-3 mb-3">
    <?php if ($tarefas): ?>
    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-list-check"></i> O que precisa da sua atenção</div>
            <div class="list-group list-group-flush">
                <?php foreach ($tarefas as $t): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 small" href="<?= e(url($t['link'])) ?>">
                        <i class="bi bi-<?= e($t['icone']) ?> fs-6 <?= $t['alerta'] ? 'text-red' : 'text-navy' ?>"></i>
                        <span class="flex-grow-1"><?= e($t['texto']) ?></span>
                        <span class="badge rounded-pill <?= $t['alerta'] ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= (int) $t['n'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $colKpis ?>">
        <div class="row g-3 mb-3 <?= $colsGrandes ?>">
            <div class="col"><a class="kpi" href="<?= e($q([])) ?>"><span class="kpi-icon"><i class="bi bi-trophy"></i></span><div class="kpi-label">Vendas no período</div><div class="kpi-value"><?= numero($k['vendas']) ?></div><div class="kpi-sub">PIs novas e renovações aprovadas · <?= e(moeda($k['vendas_valor'])) ?></div></a></div>
            <div class="col"><div class="kpi"><span class="kpi-icon"><i class="bi bi-cash-stack"></i></span><div class="kpi-label">Valor bruto (tabela)</div><div class="kpi-value"><?= e(moeda($k['bruto'])) ?></div><div class="kpi-sub">PIs ativas emitidas no período</div></div></div>
            <div class="col"><div class="kpi"><span class="kpi-icon"><i class="bi bi-currency-exchange"></i></span><div class="kpi-label">Valor negociado</div><div class="kpi-value"><?= e(moeda($k['negociado'])) ?></div><div class="kpi-sub">Ticket médio <?= e(moeda($k['ticket'])) ?></div></div></div>
            <div class="col"><div class="kpi kpi-ok"><span class="kpi-icon"><i class="bi bi-wallet2"></i></span><div class="kpi-label">Valor líquido</div><div class="kpi-value"><?= e(moeda($k['liquido'])) ?></div><div class="kpi-sub">Negociado menos comissão de agência</div></div></div>
        </div>
        <div class="row g-3 <?= $colsPequenos ?>">
            <div class="col"><a class="kpi" href="<?= e($q([])) ?>"><span class="kpi-icon"><i class="bi bi-files"></i></span><div class="kpi-label">PIs geradas</div><div class="kpi-value"><?= numero($k['geradas']) ?></div><div class="kpi-sub"><?= numero($k['rascunhos']) ?> rascunho(s)</div></a></div>
            <div class="col"><a class="kpi<?= $k['aguardando'] ? ' kpi-alert' : '' ?>" href="<?= e(url('/pis', ['situacao' => 'Enviada'])) ?>"><span class="kpi-icon"><i class="bi bi-hourglass"></i></span><div class="kpi-label">Aguardando conferência</div><div class="kpi-value"><?= numero($k['aguardando']) ?></div><div class="kpi-sub">agora</div></a></div>
            <div class="col"><a class="kpi<?= $k['devolvidas'] ? ' kpi-alert' : '' ?>" href="<?= e(url('/pis', ['situacao' => 'Devolvida'])) ?>"><span class="kpi-icon"><i class="bi bi-arrow-return-left"></i></span><div class="kpi-label">Devolvidas</div><div class="kpi-value"><?= numero($k['devolvidas']) ?></div><div class="kpi-sub"><?= numero($k['devolucoes_periodo']) ?> devolução(ões) no período</div></a></div>
            <div class="col"><a class="kpi kpi-ok" href="<?= e($q(['situacao' => 'Aprovada'])) ?>"><span class="kpi-icon"><i class="bi bi-check2-circle"></i></span><div class="kpi-label">Aprovadas</div><div class="kpi-value"><?= numero($k['aprovadas']) ?></div><div class="kpi-sub">na conferência, no período</div></a></div>
            <div class="col"><a class="kpi" href="<?= e(url('/pis', ['situacao' => 'Pronta para faturamento'])) ?>"><span class="kpi-icon"><i class="bi bi-receipt"></i></span><div class="kpi-label">Prontas p/ faturar</div><div class="kpi-value"><?= numero($k['prontas']) ?></div><div class="kpi-sub"><?= e(moeda($k['prontas_valor'])) ?> líquido</div></a></div>
            <div class="col"><a class="kpi" href="<?= e($q(['situacao' => 'Cancelada'])) ?>"><span class="kpi-icon"><i class="bi bi-x-octagon"></i></span><div class="kpi-label">Canceladas</div><div class="kpi-value"><?= numero($k['canceladas']) ?></div><div class="kpi-sub">no período</div></a></div>
            <div class="col"><div class="kpi<?= $k['taxa_devolucao'] !== null && $k['taxa_devolucao'] > 0.10 ? ' kpi-alert' : '' ?>"><span class="kpi-icon"><i class="bi bi-graph-down-arrow"></i></span><div class="kpi-label">Taxa de devolução</div><div class="kpi-value"><?= e($pct($k['taxa_devolucao'])) ?></div><div class="kpi-sub">meta: abaixo de 10% · <?= numero($k['conferidas']) ?> conferida(s)</div></div></div>
            <div class="col"><div class="kpi<?= $k['primeira'] !== null && $k['primeira'] >= 0.9 ? ' kpi-ok' : '' ?>"><span class="kpi-icon"><i class="bi bi-bullseye"></i></span><div class="kpi-label">Certa de primeira</div><div class="kpi-value"><?= e($pct($k['primeira'])) ?></div><div class="kpi-sub">meta: acima de 90%</div></div></div>
            <div class="col"><div class="kpi<?= $k['fora_prazo'] ? ' kpi-alert' : '' ?>"><span class="kpi-icon"><i class="bi bi-alarm"></i></span><div class="kpi-label">Fora do prazo</div><div class="kpi-value"><?= numero($k['fora_prazo']) ?></div><div class="kpi-sub">enviadas com menos antecedência</div></div></div>
            <div class="col"><a class="kpi" href="<?= e(url('/projetos')) ?>"><span class="kpi-icon"><i class="bi bi-stars"></i></span><div class="kpi-label">Projetos vendidos</div><div class="kpi-value"><?= e(moeda($k['projetos_valor'])) ?></div><div class="kpi-sub"><?= numero($k['projetos_cotas']) ?> cota(s) no período</div></a></div>
            <div class="col"><a class="kpi" href="<?= e(url('/projetos')) ?>"><span class="kpi-icon"><i class="bi bi-grid-3x3-gap"></i></span><div class="kpi-label">Cotas disponíveis</div><div class="kpi-value"><?= numero($k['cotas_disponiveis']) ?></div><div class="kpi-sub">em projetos abertos</div></a></div>
            <div class="col"><a class="kpi" href="<?= e(url('/reservas')) ?>"><span class="kpi-icon"><i class="bi bi-hourglass-split"></i></span><div class="kpi-label">Reservas ativas</div><div class="kpi-value"><?= numero($k['reservas_ativas']) ?></div><div class="kpi-sub">valem 24 horas</div></a></div>
        </div>
    </div>
</div>

<script type="application/json" id="dados-graficos"><?= json_encode($g, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<div class="row g-3">
    <div class="col-lg-7"><div class="card h-100"><div class="card-header"><i class="bi bi-bar-chart"></i> Valor negociado por veículo</div><div class="card-body"><div class="chart-box"><canvas id="gVeiculos" aria-label="Gráfico de valor negociado por veículo" role="img"></canvas></div></div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-header"><i class="bi bi-pie-chart"></i> PIs por situação</div><div class="card-body"><div class="chart-box"><canvas id="gSituacoes" aria-label="Gráfico de PIs por situação" role="img"></canvas></div></div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header"><i class="bi bi-graph-up"></i> Evolução dos últimos 6 meses</div><div class="card-body"><div class="chart-box"><canvas id="gEvolucao" aria-label="Gráfico de evolução mensal" role="img"></canvas></div></div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-header"><i class="bi bi-exclamation-diamond"></i> Motivos de devolução</div><div class="card-body"><div class="chart-box"><canvas id="gMotivos" aria-label="Gráfico de motivos de devolução" role="img"></canvas></div></div></div></div>
    <?php if ($g['mostrarNomes']): ?>
    <div class="col-12"><div class="card"><div class="card-header"><i class="bi bi-people"></i> Vendas por executivo <span class="ms-auto small-muted">visível só para gestores, diretoria e Conferência</span></div><div class="card-body"><div class="chart-box"><canvas id="gExecutivos" aria-label="Gráfico de vendas por executivo" role="img"></canvas></div></div></div></div>
    <?php endif; ?>
</div>
