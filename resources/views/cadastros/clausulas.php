<?php $admin = \App\Services\Auth::isAdmin(); ?>
<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Cláusulas da PI</nav>
<div class="page-head"><div><h1>Cláusulas da PI</h1><p>RN-47: saem em toda PI e o vendedor não pode editá-las. Só o administrador altera; as PIs já geradas mantêm o texto da época. As cláusulas 6 e 7 ficam no cadastro de cada veículo.</p></div></div>
<?php if (\App\Services\Configuracao::texto('multa_rescisao_texto') === ''): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i><div><b>Pendência do jurídico:</b> o texto da multa por rescisão antecipada ainda não foi definido. Enquanto isso, a PI sai com “[texto a definir pelo jurídico]”.</div></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/cadastros/clausulas')) ?>" data-confirm="Salvar as cláusulas? Elas valem para todas as próximas PIs." data-confirm-danger="0">
    <?= csrf_field() ?>
    <?php foreach ($clausulas as $c): ?>
        <div class="mb-3"><label class="form-label" for="cl<?= (int) $c['id'] ?>"><?= e($c['codigo'] . ' · ' . $c['titulo']) ?> <span class="small-muted">versão <?= (int) $c['versao'] ?></span></label>
            <textarea class="form-control" id="cl<?= (int) $c['id'] ?>" name="texto[<?= (int) $c['id'] ?>]" rows="2"<?= $admin ? '' : ' readonly' ?>><?= e($c['texto']) ?></textarea>
            <?php if (str_contains($c['texto'], '{MULTA_RESCISAO}')): ?><div class="form-text">{MULTA_RESCISAO} é trocado pelo texto abaixo.</div><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="mb-3"><label class="form-label" for="multa">Texto da multa por rescisão antecipada (jurídico)</label>
        <textarea class="form-control" id="multa" name="multa_rescisao_texto" rows="2" placeholder="[texto a definir pelo jurídico]"<?= $admin ? '' : ' readonly' ?>><?= e($multa) ?></textarea></div>
    <?php if ($admin): ?><div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar cláusulas</button></div><?php endif; ?>
</form>
