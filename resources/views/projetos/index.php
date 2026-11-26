<?php
$tot = ['potencial' => 0.0, 'vendido' => 0.0, 'falta' => 0.0];
foreach ($projetos as $P) {
    $tot['vendido'] += $P['valor_vendido'];
    $tot['falta'] += $P['st']['falta_vender'];
}
?>
<div class="page-head">
    <div><h1>Projetos especiais</h1><p>Só é vendido se estiver cadastrado, no prazo, na empresa certa e com cota disponível (RN-32 a RN-35). A venda e a reserva travam a cota no banco, para duas pessoas não venderem a mesma última cota.</p></div>
    <?php if (can('projetos.gerenciar')): ?><div class="actions"><a class="btn btn-primary" href="<?= e(url('/projetos/novo')) ?>"><i class="bi bi-plus-lg"></i> Novo projeto</a></div><?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="kpi"><span class="kpi-icon"><i class="bi bi-stars"></i></span><div class="kpi-label">Projetos abertos</div><div class="kpi-value"><?= count(array_filter($projetos, fn ($P) => $P['st']['aberto'])) ?></div><div class="kpi-sub">de <?= count($projetos) ?> cadastrados</div></div></div>
    <div class="col-md-4"><div class="kpi kpi-ok"><span class="kpi-icon"><i class="bi bi-cash-stack"></i></span><div class="kpi-label">Vendido</div><div class="kpi-value"><?= e(moeda($tot['vendido'])) ?></div><div class="kpi-sub">PIs ativas (sem canceladas e substituídas)</div></div></div>
    <div class="col-md-4"><div class="kpi"><span class="kpi-icon"><i class="bi bi-hourglass-top"></i></span><div class="kpi-label">Falta vender</div><div class="kpi-value"><?= e(moeda($tot['falta'])) ?></div><div class="kpi-sub">cotas disponíveis em projetos no prazo</div></div></div>
</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Projeto</th><th>Válido para</th><th>Venda até</th><th>Situação</th><th>Cotas por tipo (vendidas / total)</th><th class="num">Vendidas</th><th class="num">Reservadas</th><th class="num">Faltam</th><th class="num">Vendido (R$)</th><th class="num">Falta vender (R$)</th></tr></thead>
    <tbody>
    <?php if (!$projetos): ?><tr><td colspan="10"><div class="empty-state"><i class="bi bi-stars"></i>Nenhum projeto cadastrado.</div></td></tr><?php endif; ?>
    <?php foreach ($projetos as $P): $st = $P['st']; $todas = count($P['veiculos']) === count($veiculos); ?>
        <tr><td><a class="fw-semibold" href="<?= e(url('/projetos/' . $P['id'])) ?>"><?= e($P['nome']) ?></a><div class="small-muted"><?= e($P['codigo']) ?></div></td>
            <td class="small"><?= $todas ? 'todas as empresas' : e(implode(', ', array_map(fn ($i) => $veiculos[$i] ?? $i, $P['veiculos']))) ?></td>
            <td class="small text-nowrap"><?= e(data_br($P['data_limite_venda'])) ?></td>
            <td><span class="st <?= $st['aberto'] ? 'st-aberto' : ($st['esgotado'] ? 'st-esgotado' : 'st-encerrado') ?>"><?= e($st['situacao']) ?></span><?= (int) $P['ativo'] ? '' : ' <span class="st st-inativo">inativo</span>' ?></td>
            <td class="small"><?= implode(' · ', array_map(fn ($c) => e($c['nome']) . ' <b>' . $c['vendidas'] . '</b>/' . $c['total'], $st['cotas'])) ?></td>
            <td class="num"><?= (int) $st['vendidas'] ?></td><td class="num"><?= (int) $st['reservadas'] ?></td><td class="num"><?= (int) $st['disponiveis'] ?></td>
            <td class="num"><?= e(moeda($P['valor_vendido'], false)) ?></td>
            <td class="num"><?= e(moeda($st['falta_vender'], false)) ?><?= $st['vencido'] && $st['nao_vendido'] ? '<div class="small-muted">não vendido: ' . e(moeda($st['nao_vendido'], false)) . '</div>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
