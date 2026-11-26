<nav class="breadcrumb"><a href="<?= e(url('/projetos')) ?>">Projetos especiais</a>&nbsp;›&nbsp;<?= e($P['nome']) ?></nav>
<div class="page-head">
    <div><h1><?= e($P['nome']) ?> <span class="st <?= $st['aberto'] ? 'st-aberto' : ($st['esgotado'] ? 'st-esgotado' : 'st-encerrado') ?>"><?= e($st['situacao']) ?></span></h1>
        <p><?= e($P['codigo']) ?><?= !empty($P['codigo_anterior']) ? ' (antes: ' . e($P['codigo_anterior']) . ')' : '' ?> · venda até <?= e(data_br($P['data_limite_venda'])) ?> · válido para <?= e(implode(', ', array_map(fn ($i) => $veiculos[$i] ?? $i, $P['veiculos']))) ?></p></div>
    <?php if (can('projetos.gerenciar') && !$P['excluido_em']): ?><div class="actions">
        <a class="btn btn-outline-primary" href="<?= e(url('/projetos/' . $P['id'] . '/editar')) ?>"><i class="bi bi-pencil"></i> Editar</a>
        <form method="post" action="<?= e(url('/projetos/' . $P['id'] . '/excluir')) ?>" data-confirm="Excluir o projeto? Ele some das listas de venda; as PIs já geradas continuam iguais (RN-39)." data-confirm-button="Excluir"><?= csrf_field() ?><button class="btn btn-light text-danger" type="submit"><i class="bi bi-trash"></i> Excluir</button></form>
    </div><?php endif; ?>
</div>
<?php if ($P['descricao']): ?><p><?= nl2br(e($P['descricao'])) ?></p><?php endif; ?>
<div class="row g-3 mb-3">
    <?php foreach ($st['cotas'] as $c): ?>
        <div class="col-6 col-lg-3"><div class="kpi<?= $c['disponiveis'] ? '' : ' kpi-alert' ?>"><div class="kpi-label">Cota <?= e($c['nome']) ?> · <?= e(moeda($c['valor'])) ?></div><div class="kpi-value"><?= $c['disponiveis'] ?> <small class="small-muted" style="font-size:.8rem">de <?= $c['total'] ?> disponíveis</small></div>
            <div class="kpi-sub"><?= $c['vendidas'] ?> vendida(s) · <?= $c['reservadas'] ?> reservada(s)</div></div></div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-lg-8"><h2 class="mb-2">Vendas</h2><div class="table-card"><div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>PI</th><th>Cliente</th><th>Executivo</th><th>Cota</th><th class="num">Qtd</th><th>Cobrança</th><th class="num">Valor</th><th>Situação</th></tr></thead><tbody>
        <?php if (!$vendas): ?><tr><td colspan="8" class="small-muted p-3">Nenhuma venda (entre as PIs que você pode ver).</td></tr><?php endif; ?>
        <?php foreach ($vendas as $v): ?><tr><td><a href="<?= e(url('/pis/' . $v['id'])) ?>"><?= e($v['numero']) ?></a></td><td class="small"><?= e($v['cliente_razao']) ?></td><td class="small"><?= e($v['executivo']) ?></td><td><?= e($v['cota']) ?></td>
            <td class="num"><?= (int) $v['quantidade'] ?></td><td class="small"><?= e($v['cobranca']) ?></td><td class="num"><?= e(moeda($v['valor_total'])) ?></td><td><?= selo($v['situacao']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <div class="col-lg-4"><h2 class="mb-2">Reservas ativas</h2><div class="table-card"><table class="table table-sm mb-0"><tbody>
        <?php if (!$reservas): ?><tr><td class="small-muted p-3">Nenhuma.</td></tr><?php endif; ?>
        <?php foreach ($reservas as $rr): ?><tr><td class="small"><b><?= e($rr['cota']) ?> × <?= (int) $rr['quantidade'] ?></b> · <?= e($rr['cliente_nome']) ?><div class="small-muted"><?= e($rr['executivo']) ?> · até <?= e(data_hora_br($rr['expira_em'])) ?></div></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
