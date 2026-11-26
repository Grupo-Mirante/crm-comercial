<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Tabelas de preços</nav>
<div class="page-head">
    <div><h1>Tabelas de preços <?= (int) $ano ?></h1><p>Praças da TV, verbas local/nacional das rádios e tabela do digital. A PI usa a tabela do ano da emissão.</p></div>
    <div class="actions">
        <form method="get" class="d-flex gap-2"><select class="form-select" name="ano" data-autosubmit aria-label="Ano"><?php foreach ($anos as $a): ?><option<?= $a === $ano ? ' selected' : '' ?>><?= (int) $a ?></option><?php endforeach; ?></select></form>
        <button class="btn btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#copiar"><i class="bi bi-copy"></i> Criar tabelas de outro ano</button>
        <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#nova"><i class="bi bi-plus-lg"></i> Nova tabela/praça</button>
    </div>
</div>
<div class="collapse mb-3" id="copiar"><div class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/cadastros/tabelas/copiar-ano')) ?>" class="row g-2 align-items-end" data-confirm="Copiar todas as tabelas e preços para o novo ano? Depois atualize os preços antes de vender com eles." data-confirm-danger="0">
        <?= csrf_field() ?>
        <div class="col-md-3"><label class="form-label">Copiar de</label><select class="form-select" name="de"><?php foreach ($anos as $a): ?><option<?= $a === $ano ? ' selected' : '' ?>><?= (int) $a ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Para o ano</label><input class="form-control" type="number" name="para" value="<?= (int) max($anos ?: [(int) date('Y')]) + 1 ?>" min="2000" max="2100"></div>
        <div class="col-md-6"><button class="btn btn-primary" type="submit">Copiar tabelas e preços</button> <span class="small-muted ms-2">Use quando sair a tabela do ano seguinte.</span></div>
    </form>
</div></div></div>
<div class="collapse mb-3" id="nova"><div class="card"><div class="card-body">
    <form method="post" action="<?= e(url('/cadastros/tabelas')) ?>" class="row g-2 align-items-end" novalidate>
        <?= csrf_field() ?><input type="hidden" name="ano_vigencia" value="<?= (int) $ano ?>">
        <div class="col-md-3"><label class="form-label">Veículo <span class="req">*</span></label><select class="form-select" name="veiculo_id" required><option value="">—</option><?php foreach ($veiculos as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Código <span class="req">*</span></label><input class="form-control" name="codigo" maxlength="10" placeholder="ex.: MA1"></div>
        <div class="col-md-3"><label class="form-label">Descrição <span class="req">*</span></label><input class="form-control" name="descricao" maxlength="80" placeholder="ex.: São Luís"></div>
        <div class="col-md-2"><label class="form-label">Cidade da assinatura</label><input class="form-control" name="cidade" value="São Luís - MA"></div>
        <div class="col-md-2"><input type="hidden" name="sem_tabela" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="sem_tabela" value="1" id="semtab"><label class="form-check-label small" for="semtab">Sem tabela (preço manual)</label></div>
            <button class="btn btn-primary w-100 mt-1" type="submit">Incluir</button></div>
    </form>
</div></div></div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Veículo</th><th>Código</th><th>Descrição</th><th>Cidade da assinatura</th><th class="num">Preços</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php if (!$tabelas): ?><tr><td colspan="7"><div class="empty-state">Nenhuma tabela em <?= (int) $ano ?>.</div></td></tr><?php endif; ?>
    <?php foreach ($tabelas as $t): ?>
        <tr>
            <td class="fw-semibold"><?= e($t['veiculo']) ?></td><td><code><?= e($t['codigo']) ?></code></td>
            <td><?= e($t['descricao']) ?><?= (int) $t['sem_tabela'] ? ' <span class="st st-rascunho">sem tabela · preço manual</span>' : '' ?></td>
            <td class="small"><?= e($t['cidade']) ?></td><td class="num"><?= numero($t['qtd_precos']) ?></td>
            <td><?= (int) $t['ativo'] ? selo('Ativo') : selo('Inativo') ?></td>
            <td><div class="table-actions"><?php if (!(int) $t['sem_tabela']): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('/cadastros/tabelas/' . $t['id'] . '/precos')) ?>"><i class="bi bi-currency-dollar"></i> Preços</a><?php endif; ?></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
