<div class="page-head"><div><h1>Auditoria</h1><p>Registro permanente de acessos e alterações (PIs, valores, aprovações, cadastros, permissões). Não pode ser editado nem apagado pelo sistema.</p></div></div>
<form class="filters" method="get"><div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label" for="de">De</label><input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="ate">Até</label><input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>"></div>
    <div class="col-md-2"><label class="form-label" for="usuario">Usuário</label><select class="form-select form-select-sm" id="usuario" name="usuario"><option value="">Todos</option>
        <?php foreach ($usuarios as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $f['usuario'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="entidade">Registro</label><select class="form-select form-select-sm" id="entidade" name="entidade"><option value="">Todos</option>
        <?php foreach ($entidades as $en): ?><option<?= $f['entidade'] === $en ? ' selected' : '' ?>><?= e($en) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="acao">Ação</label><select class="form-select form-select-sm" id="acao" name="acao"><option value="">Todas</option>
        <?php foreach ($acoes as $a): ?><option<?= $f['acao'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label" for="q">Texto ou nº</label><input class="form-control form-control-sm" id="q" name="q" value="<?= e($f['q']) ?>"></div>
    <div class="col-12 d-flex gap-2 justify-content-end"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-light btn-sm" href="<?= e(url('/auditoria')) ?>">Limpar</a></div>
</div></form>
<div class="table-card"><div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><th>Quando</th><th>Usuário</th><th>Ação</th><th>Registro</th><th>Descrição</th><th>IP</th><th></th></tr></thead>
    <tbody>
    <?php if (!$p['itens']): ?><tr><td colspan="7"><div class="empty-state">Nenhum registro no período.</div></td></tr><?php endif; ?>
    <?php foreach ($p['itens'] as $l): ?>
        <tr><td class="small text-nowrap"><?= e(data_hora_br($l['criado_em'])) ?></td><td class="small"><?= e($l['usuario_nome'] ?: '—') ?></td><td class="small"><code><?= e($l['acao']) ?></code></td>
            <td class="small"><?= e(trim(($l['entidade'] ?? '') . ' ' . ($l['entidade_id'] ?? ''))) ?></td><td class="small"><?= e($l['descricao']) ?></td><td class="small"><?= e($l['ip']) ?></td>
            <td><a class="btn btn-light btn-sm" href="<?= e(url('/auditoria/' . $l['id'])) ?>" aria-label="Detalhes"><i class="bi bi-search"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php $query = array_filter($f); include __DIR__ . '/../partials/paginacao.php'; ?>
