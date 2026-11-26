<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Formatos digitais</nav>
<div class="page-head">
    <div><h1>Formatos digitais</h1><p>Mídia kit do G1 Maranhão e do Imirante: mensal, diária, unidade, pacote, CPM e publicidade legal (cm/col).</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('/cadastros/formatos/novo', $vid ? ['veiculo' => $vid] : [])) ?>"><i class="bi bi-plus-lg"></i> Novo formato</a></div>
</div>
<form class="filters" method="get"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="veiculo">Veículo</label><select class="form-select" id="veiculo" name="veiculo" data-autosubmit><option value="">Todos</option>
        <?php foreach ($veiculos as $v): ?><option value="<?= (int) $v['id'] ?>"<?= $vid === (int) $v['id'] ? ' selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-5"><label class="form-label" for="q">Sigla ou nome</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Buscar</button></div>
</div></form>
<div class="table-card"><div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><th>Sigla</th><th>Formato</th><th>Veículo</th><th>Modelo</th><th>Categoria</th><th>Dispositivo · local</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php if (!$p['itens']): ?><tr><td colspan="8"><div class="empty-state">Nenhum formato encontrado.</div></td></tr><?php endif; ?>
    <?php foreach ($p['itens'] as $f): ?>
        <tr><td><code><?= e($f['sigla']) ?></code></td><td class="small"><?= e($f['nome']) ?><?php if ($f['observacao']): ?><div class="small-muted"><?= e($f['observacao']) ?></div><?php endif; ?></td>
            <td class="small"><?= e($f['veiculo']) ?></td><td class="small"><?= e($f['modelo']) ?></td><td class="small"><?= e($f['categoria'] ?: '—') ?></td>
            <td class="small"><?= e(trim(($f['dispositivo'] ?? '') . ' · ' . ($f['local_pagina'] ?? ''), ' ·')) ?></td>
            <td><?= (int) $f['ativo'] ? selo('Ativo') : selo('Inativo') ?></td>
            <td><a class="btn btn-light btn-sm" href="<?= e(url('/cadastros/formatos/' . $f['id'])) ?>"><i class="bi bi-pencil"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php $query = array_filter(['veiculo' => $vid ?: null, 'q' => $q]); include __DIR__ . '/../partials/paginacao.php'; ?>
