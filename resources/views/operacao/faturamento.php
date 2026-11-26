<?php
$etapas = [
    'Aprovada' => ['Aprovadas: reservar grade e montar mapa (OPEC)', 'calendar2-week', 'Prazo da OPEC: ' . \App\Services\Configuracao::inteiro('sla_opec_grade_dias_uteis', 2) . ' dias úteis após a aprovação'],
    'Em programação' => ['Em programação: material e início da veiculação', 'broadcast', 'Material pronto até 2 dias úteis antes da 1ª veiculação'],
    'Em veiculação' => ['Em veiculação: comprovar ao final', 'play-circle', 'Comprovante de veiculação até 3 dias úteis após o fim'],
    'Pronta para faturamento' => ['Prontas para faturamento (Financeiro)', 'receipt', 'Nota até o 5º dia útil do mês seguinte à veiculação'],
    'Faturada' => ['Faturadas: aguardando pagamento', 'cash-coin', 'Após o pagamento, encerre a PI'],
];
?>
<div class="page-head"><div><h1>Operação e faturamento</h1><p>Etapas 5 a 9 do fluxo: liberação para as áreas, grade, veiculação, comprovação e faturamento. Abra a PI para registrar cada passo.</p></div></div>
<div class="row g-3 mb-3">
    <?php foreach ($etapas as $s => [$t, $ic]): ?>
        <div class="col-6 col-lg"><a class="kpi" href="#<?= e(trim((string) preg_replace('/[^a-z0-9]+/', '-', normaliza($s)), '-')) ?>"><span class="kpi-icon"><i class="bi bi-<?= e($ic) ?>"></i></span><div class="kpi-label"><?= e($s) ?></div><div class="kpi-value"><?= count($listas[$s]) ?></div>
            <div class="kpi-sub"><?= e(moeda(array_sum(array_column($listas[$s], 'valor_liquido')))) ?></div></a></div>
    <?php endforeach; ?>
</div>
<?php foreach ($etapas as $s => [$t, $ic, $sla]): $l = $listas[$s]; ?>
<h2 class="mb-1" id="<?= e(trim((string) preg_replace('/[^a-z0-9]+/', '-', normaliza($s)), '-')) ?>"><?= e($t) ?> <span class="pill-count"><?= count($l) ?></span></h2>
<p class="small-muted mb-2"><?= e($sla) ?></p>
<div class="table-card mb-4"><div class="table-responsive"><table class="table table-sm table-hover align-middle">
    <thead><tr><th>Nº da PI</th><th>Veículo</th><th>Cliente</th><th>Executivo</th><th>Período</th><?= $s === 'Pronta para faturamento' ? '<th>Fim da veiculação</th><th>1º vencimento</th>' : '' ?><?= $s === 'Faturada' ? '<th>Nota</th>' : '' ?><th class="num">Líquido</th><th></th></tr></thead>
    <tbody>
    <?php if (!$l): ?><tr><td colspan="9" class="small-muted p-3">Nenhuma PI nesta etapa.</td></tr><?php endif; ?>
    <?php foreach ($l as $p): ?>
        <tr><td class="fw-semibold"><a href="<?= e(url('/pis/' . $p['id'])) ?>"><?= e($p['numero']) ?></a></td><td class="small"><?= e($p['veiculo']) ?></td><td class="small"><?= e($p['cliente_razao']) ?></td><td class="small"><?= e($p['executivo']) ?></td>
            <td class="small text-nowrap"><?= e(data_br($p['data_inicio'])) ?> a <?= e(data_br($p['data_fim'])) ?></td>
            <?= $s === 'Pronta para faturamento' ? '<td class="small">' . e(data_br($p['data_fim_veiculacao'])) . '</td><td class="small">' . e(data_br($p['venc'])) . '</td>' : '' ?>
            <?= $s === 'Faturada' ? '<td class="small">' . e($p['nf_numero']) . ' · ' . e(moeda($p['nf_valor'])) . '</td>' : '' ?>
            <td class="num"><?= e(moeda($p['valor_liquido'])) ?></td><td><a class="btn btn-light btn-sm" href="<?= e(url('/pis/' . $p['id'])) ?>">Abrir</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endforeach; ?>
<?php if ($divergencias): ?>
<h2 class="mb-2 text-red">PI × faturamento: notas com valor diferente da PI aprovada (meta: zero)</h2>
<div class="table-card"><table class="table table-sm"><thead><tr><th>PI</th><th>Cliente</th><th class="num">Líquido da PI</th><th class="num">Nota</th><th class="num">Diferença</th></tr></thead><tbody>
<?php foreach ($divergencias as $d): ?><tr><td><a href="<?= e(url('/pis/' . $d['id'])) ?>"><?= e($d['numero']) ?></a></td><td class="small"><?= e($d['cliente_razao']) ?></td><td class="num"><?= e(moeda($d['valor_liquido'])) ?></td><td class="num"><?= e($d['nf_numero']) ?> · <?= e(moeda($d['nf_valor'])) ?></td><td class="num text-red"><?= e(moeda((float) $d['nf_valor'] - (float) $d['valor_liquido'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
