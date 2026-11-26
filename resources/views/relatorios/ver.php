<?php
$fmt = function ($v, string $tipo): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return match ($tipo) {
        'money' => moeda($v),
        'int' => numero($v),
        'dec' => numero($v, 1),
        'pct' => number_format((float) $v * 100, 1, ',', '.') . '%',
        'date' => data_br($v),
        'datetime' => data_hora_br($v),
        default => (string) $v,
    };
};
$comPeriodo = $info['periodo'] ?? true;
$q = array_filter(['visao' => $f['visao'], 'de' => $f['de'], 'ate' => $f['ate'], 'empresa' => $f['empresa'], 'veiculo' => $f['veiculo'], 'executivo' => $f['executivo'], 'gestor' => $f['gestor']]);
?>
<nav class="breadcrumb"><a href="<?= e(url('/relatorios')) ?>">Relatórios</a>&nbsp;›&nbsp;<?= e($info['titulo']) ?></nav>
<div class="page-head">
    <div><h1><?= e($info['titulo']) ?></h1><p><?= e($info['descricao']) ?></p></div>
    <div class="actions">
        <?php if (can('relatorios.exportar')): ?><a class="btn btn-outline-primary" href="<?= e(url('/relatorios/' . $chave . '/excel', $q)) ?>"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</a><?php endif; ?>
        <button class="btn btn-light" type="button" data-print><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>
<form class="filters" method="get">
    <div class="row g-2 align-items-end">
        <?php if ($comPeriodo): ?>
        <div class="col-12 col-md-auto"><label class="form-label d-block">Visão</label><div class="btn-group btn-group-sm" role="group" aria-label="Visão">
            <?php foreach (['diaria' => 'Diária', 'semanal' => 'Semanal', 'mensal' => 'Mensal'] as $k => $t): ?>
                <a class="btn <?= $f['visao'] === $k ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/relatorios/' . $chave, ['visao' => $k])) ?>"><?= e($t) ?></a>
            <?php endforeach; ?></div><input type="hidden" name="visao" value="<?= e($f['visao']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="de">De</label><input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="ate">Até</label><input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>"></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label" for="empresa">Empresa</label><select class="form-select form-select-sm" id="empresa" name="empresa"><option value="">Todas</option><?php foreach ($opcoes['empresas'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['empresa'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="veiculo">Veículo</label><select class="form-select form-select-sm" id="veiculo" name="veiculo"><option value="">Todos</option><?php foreach ($opcoes['veiculos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['veiculo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
        <?php if ($opcoes['executivos']): ?><div class="col-6 col-md-2"><label class="form-label" for="executivo">Executivo</label><select class="form-select form-select-sm" id="executivo" name="executivo"><option value="">Todos</option><?php foreach ($opcoes['executivos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['executivo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <?php if ($opcoes['gestores']): ?><div class="col-6 col-md-2"><label class="form-label" for="gestor">Gestor</label><select class="form-select form-select-sm" id="gestor" name="gestor"><option value="">Todos</option><?php foreach ($opcoes['gestores'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['gestor'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-12 col-md-auto ms-auto"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Atualizar</button></div>
    </div>
</form>
<?php if ($res['nota']): ?><div class="alert alert-info"><i class="bi bi-info-circle"></i><div><?= e($res['nota']) ?></div></div><?php endif; ?>
<div class="small-muted mb-2"><?= $comPeriodo ? 'Período de ' . e(data_br($f['de'])) . ' a ' . e(data_br($f['ate'])) : 'Situação atual' ?> · <?= count($res['linhas']) ?> linha(s)</div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><?php foreach ($res['colunas'] as [$k, $rot, $tipo]): ?><th class="<?= in_array($tipo, ['money', 'int', 'pct', 'dec'], true) ? 'num' : '' ?>"><?= e($rot) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php if (!$res['linhas']): ?><tr><td colspan="<?= count($res['colunas']) ?>"><div class="empty-state"><i class="bi bi-inbox"></i>Sem dados para os filtros escolhidos.</div></td></tr><?php endif; ?>
    <?php foreach ($res['linhas'] as $l): ?><tr><?php foreach ($res['colunas'] as [$k, , $tipo]): ?><td class="<?= in_array($tipo, ['money', 'int', 'pct', 'dec'], true) ? 'num' : 'small' ?>"><?= e($fmt($l[$k] ?? null, $tipo)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody>
    <?php if ($res['totais']): ?><tfoot><tr class="fw-semibold bg-soft"><?php foreach ($res['colunas'] as $i => [$k, , $tipo]): ?><td class="<?= in_array($tipo, ['money', 'int', 'pct', 'dec'], true) ? 'num' : '' ?>"><?= $i === 0 ? 'Total' : (array_key_exists($k, $res['totais']) ? e($fmt($res['totais'][$k], $tipo)) : '') ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table></div></div>
