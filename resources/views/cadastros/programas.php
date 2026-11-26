<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Programas</nav>
<div class="page-head">
    <div><h1>Programas (TV e rádio)</h1><p>A sigla do programa preenche nome, dias de exibição e preço na PI (RN-11). A grade só aceita inserções nos dias em que o programa vai ao ar (RN-23).</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?= e(url('/cadastros/programas/novo', $vid ? ['veiculo' => $vid] : [])) ?>"><i class="bi bi-plus-lg"></i> Novo programa</a></div>
</div>
<form class="filters" method="get"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label" for="veiculo">Veículo</label><select class="form-select" id="veiculo" name="veiculo" data-autosubmit><option value="">Todos</option>
        <?php foreach ($veiculos as $v): ?><option value="<?= (int) $v['id'] ?>"<?= $vid === (int) $v['id'] ? ' selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-5"><label class="form-label" for="q">Sigla ou nome</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>"></div>
    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Buscar</button></div>
</div></form>
<div class="table-card"><div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><th>Sigla</th><th>Programa</th><th>Veículo</th><th>Dias</th><th>Faixa</th><th class="num">Índice 15"</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php if (!$p['itens']): ?><tr><td colspan="8"><div class="empty-state">Nenhum programa encontrado.</div></td></tr><?php endif; ?>
    <?php foreach ($p['itens'] as $pr): ?>
        <tr><td><code><?= e($pr['sigla']) ?></code></td><td><?= e($pr['nome']) ?></td><td class="small"><?= e($pr['veiculo']) ?></td>
            <td class="small"><?= e($pr['dias_exibicao'] ?: '—') ?><?= $pr['dias_mascara'] === null && $pr['dias_exibicao'] ? ' <span class="small-muted">(sem restrição)</span>' : '' ?></td>
            <td class="small"><?= e($pr['faixa_horaria'] ?: '—') ?></td>
            <td class="num"><?= $pr['indice_15s'] !== null ? e(rtrim(rtrim(number_format((float) $pr['indice_15s'], 4, ',', ''), '0'), ',')) : '—' ?></td>
            <td><?= (int) $pr['ativo'] ? selo('Ativo') : selo('Inativo') ?></td>
            <td><a class="btn btn-light btn-sm" href="<?= e(url('/cadastros/programas/' . $pr['id'])) ?>"><i class="bi bi-pencil"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php $query = array_filter(['veiculo' => $vid ?: null, 'q' => $q]); include __DIR__ . '/../partials/paginacao.php'; ?>
