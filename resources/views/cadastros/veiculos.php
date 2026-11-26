<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Veículos</nav>
<div class="page-head"><div><h1>Veículos</h1><p>O veículo define a empresa emissora, as tabelas, os blocos de mídia e as cláusulas 6 e 7 da PI (RN-02, RN-09).</p></div></div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Veículo</th><th>Prefixo</th><th>Empresa emissora</th><th>Blocos de mídia</th><th class="num">Programas</th><th class="num">Formatos</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($veiculos as $v):
        $blocos = array_filter([$v['bloco_insercoes'] ? 'Inserções' : null, $v['bloco_patrocinio'] ? 'Patrocínio' : null, $v['bloco_digital'] ? 'Digital' : null, $v['bloco_publicidade_legal'] ? 'Publicidade legal' : null]); ?>
        <tr>
            <td class="fw-semibold"><?= e($v['nome']) ?><div class="small-muted"><?= e($v['tipo']) ?><?= (int) $v['divide_pi_por_mes'] ? ' · uma PI por mês' : '' ?></div></td>
            <td><code><?= e($v['prefixo']) ?></code></td>
            <td><?= e($v['empresa']) ?></td>
            <td class="small"><?= e(implode(' · ', $blocos)) ?> · Projetos · Entregas</td>
            <td class="num"><?= (int) $v['programas'] ?></td><td class="num"><?= (int) $v['formatos'] ?></td>
            <td><?= (int) $v['ativo'] ? selo('Ativo') : selo('Inativo') ?></td>
            <td><a class="btn btn-light btn-sm" href="<?= e(url('/cadastros/veiculos/' . $v['id'])) ?>"><i class="bi bi-pencil"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
