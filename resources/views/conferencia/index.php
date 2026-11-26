<div class="page-head">
    <div><h1>Conferência de PI</h1><p>Prazo: mesmo dia para PIs recebidas até <?= e(\App\Services\Configuracao::texto('sla_conferencia_hora_corte', '15:00')) ?>; até <?= e(\App\Services\Configuracao::texto('sla_conferencia_hora_limite', '12:00')) ?> do dia útil seguinte para as demais. A Conferência aprova ou devolve com código — nunca corrige a PI pelo vendedor.</p></div>
    <?php if ($fila): ?><div class="actions"><a class="btn btn-primary" href="<?= e(url('/conferencia/' . $fila[0]['id'])) ?>"><i class="bi bi-play-fill"></i> Conferir a primeira da fila</a></div><?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="kpi<?= $fila ? ' kpi-alert' : ' kpi-ok' ?>"><span class="kpi-icon"><i class="bi bi-hourglass"></i></span><div class="kpi-label">Aguardando conferência</div><div class="kpi-value"><?= count($fila) ?></div><div class="kpi-sub"><?= count(array_filter($fila, fn ($f) => $f['prazo']['atrasado'])) ?> atrasada(s)</div></div></div>
    <div class="col-md-4"><div class="kpi"><span class="kpi-icon"><i class="bi bi-arrow-return-left"></i></span><div class="kpi-label">Devolvidas aguardando correção</div><div class="kpi-value"><?= count($devolvidas) ?></div><div class="kpi-sub"><?= count(array_filter($devolvidas, fn ($f) => $f['prazo']['atrasado'])) ?> fora do prazo de 1 dia útil</div></div></div>
    <div class="col-md-4"><div class="kpi<?= $proximas ? ' kpi-alert' : '' ?>"><span class="kpi-icon"><i class="bi bi-alarm"></i></span><div class="kpi-label">Início em breve sem aprovação</div><div class="kpi-value"><?= count($proximas) ?></div><div class="kpi-sub">próximos <?= \App\Services\Configuracao::inteiro('sla_alerta_inicio_dias', 5) ?> dias</div></div></div>
</div>

<h2 class="mb-2">Fila de PIs</h2>
<div class="table-card mb-4"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Nº da PI</th><th>Recebida</th><th>Prazo da conferência</th><th>Veículo</th><th>Cliente</th><th>Executivo</th><th>Início</th><th class="num">Líquido</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php if (!$fila): ?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-check2-all"></i>Nenhuma PI aguardando conferência.</div></td></tr><?php endif; ?>
    <?php foreach ($fila as $f): ?>
        <tr><td class="fw-semibold text-nowrap"><a href="<?= e(url('/conferencia/' . $f['id'])) ?>"><?= e($f['numero']) ?></a><?= $f['parte'] ? '<div class="small-muted">PI ' . e($f['parte']) . '</div>' : '' ?></td>
            <td class="small text-nowrap"><?= e(data_hora_br($f['recebida_em'] ?? $f['enviada_em'])) ?></td>
            <td class="small <?= $f['prazo']['atrasado'] ? 'text-red fw-semibold' : '' ?>"><?= e($f['prazo']['texto']) ?></td>
            <td class="small"><?= e($f['veiculo']) ?></td><td class="small"><?= e($f['cliente_razao']) ?></td><td class="small"><?= e($f['executivo']) ?></td>
            <td class="small text-nowrap"><?= e(data_br($f['data_inicio'])) ?><?= (int) $f['fora_do_prazo'] ? '<div style="color:var(--gm-orange)">fora do prazo de envio</div>' : '' ?></td>
            <td class="num"><?= e(moeda($f['valor_liquido'])) ?></td>
            <td><?= selo($f['situacao']) ?><?= (int) $f['qtd_devolucoes'] ? '<div class="small-muted">' . (int) $f['qtd_devolucoes'] . 'ª correção</div>' : '' ?></td>
            <td><a class="btn btn-primary btn-sm" href="<?= e(url('/conferencia/' . $f['id'])) ?>">Conferir</a></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>

<div class="row g-3">
    <div class="col-lg-7"><h2 class="mb-2">Devolvidas aguardando correção</h2>
        <div class="table-card"><div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>PI</th><th>Executivo</th><th>Motivo</th><th>Prazo de correção</th></tr></thead>
            <tbody><?php if (!$devolvidas): ?><tr><td colspan="4" class="small-muted p-3">Nenhuma.</td></tr><?php endif; ?>
            <?php foreach ($devolvidas as $d): ?><tr><td><a href="<?= e(url('/pis/' . $d['id'])) ?>"><?= e($d['numero']) ?></a><div class="small-muted"><?= e($d['cliente_razao']) ?></div></td><td class="small"><?= e($d['executivo']) ?><?= (int) $d['sequencia'] >= 2 ? '<div class="small text-red">' . (int) $d['sequencia'] . 'ª devolução · gestor avisado</div>' : '' ?></td>
                <td class="small"><b><?= e($d['codigos']) ?></b> <?= e($d['descricao']) ?></td><td class="small <?= $d['prazo']['atrasado'] ? 'text-red fw-semibold' : '' ?>"><?= e($d['prazo']['texto']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div></div>
    <div class="col-lg-5"><h2 class="mb-2">Início nos próximos dias sem aprovação</h2>
        <div class="table-card"><div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>PI</th><th>Início</th><th>Situação</th></tr></thead>
            <tbody><?php if (!$proximas): ?><tr><td colspan="3" class="small-muted p-3">Nenhuma.</td></tr><?php endif; ?>
            <?php foreach ($proximas as $x): ?><tr><td><a href="<?= e(url('/pis/' . $x['id'])) ?>"><?= e($x['numero']) ?></a><div class="small-muted"><?= e($x['cliente_razao']) ?> · <?= e($x['executivo']) ?></div></td><td class="small"><?= e(data_br($x['data_inicio'])) ?></td><td><?= selo($x['situacao']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div></div>
</div>
