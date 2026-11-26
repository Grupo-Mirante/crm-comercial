<div class="page-head">
    <div><h1>Agências</h1><p>Cadastro único por CNPJ. Carta de credenciamento vencida na emissão deixa a PI como Rascunho (D08).</p></div>
    <div class="actions"><?php if (can('agencias.editar')): ?><a class="btn btn-primary" href="<?= e(url('/agencias/nova')) ?>"><i class="bi bi-plus-lg"></i> Nova agência</a><?php endif; ?></div>
</div>
<form class="filters" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label" for="q">Buscar</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Razão social ou CNPJ" data-autofocus></div>
        <div class="col-md-3"><label class="form-label" for="carta">Carta de credenciamento</label>
            <select class="form-select" id="carta" name="carta" data-autosubmit>
                <option value="">Todas</option><option value="vencida"<?= $carta === 'vencida' ? ' selected' : '' ?>>Vencidas</option><option value="vence30"<?= $carta === 'vence30' ? ' selected' : '' ?>>Vencem em 30 dias</option>
            </select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($q !== '' || $carta !== ''): ?><a class="btn btn-light" href="<?= e(url('/agencias')) ?>">Limpar</a><?php endif; ?></div>
    </div>
</form>
<div class="table-card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Agência</th><th>CNPJ</th><th class="num">Comissão</th><th>Carta de credenciamento</th><th>Contato</th><th class="num">PIs</th><th></th></tr></thead>
        <tbody>
        <?php if (!$p['itens']): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-briefcase"></i>Nenhuma agência encontrada.</div></td></tr><?php endif; ?>
        <?php foreach ($p['itens'] as $a): $venc = $a['carta_validade'] && $a['carta_validade'] < date('Y-m-d'); ?>
            <tr>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(url('/agencias/' . $a['id'])) ?>"><?= e($a['razao_social']) ?></a><?php if (!(int) $a['ativo']): ?> <span class="st st-inativo">Inativa</span><?php endif; ?></td>
                <td class="tabular text-nowrap"><?= e($a['cnpj_formatado']) ?></td>
                <td class="num"><?= e(number_format((float) $a['comissao_padrao'], 2, ',', '.')) ?>%</td>
                <td><?= e($a['carta_numero'] ?: '—') ?><?php if ($a['carta_validade']): ?><div class="small <?= $venc ? 'text-red fw-semibold' : 'small-muted' ?>"><?= $venc ? '<i class="bi bi-exclamation-triangle"></i> vencida em ' : 'válida até ' ?><?= e(data_br($a['carta_validade'])) ?></div><?php endif; ?></td>
                <td><?= e($a['contato']) ?><div class="small-muted"><?= e($a['email']) ?></div></td>
                <td class="num"><?= (int) $a['qtd_pis'] ?></td>
                <td><div class="table-actions">
                    <a class="btn btn-light btn-sm" href="<?= e(url('/agencias/' . $a['id'])) ?>" title="Ver"><i class="bi bi-eye"></i></a>
                    <?php if (can('agencias.editar')): ?><a class="btn btn-light btn-sm" href="<?= e(url('/agencias/' . $a['id'] . '/editar')) ?>" title="Editar"><i class="bi bi-pencil"></i></a><?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?php $query = array_filter(['q' => $q, 'carta' => $carta]); include __DIR__ . '/../partials/paginacao.php'; ?>
