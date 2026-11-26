<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Feriados</nav>
<div class="page-head">
    <div><h1>Feriados de <?= (int) $ano ?></h1><p>Usados nos prazos em dias úteis (envio à Conferência, correção, 72 horas úteis). Os nacionais já vêm cadastrados; inclua os estaduais e de São Luís.</p></div>
    <div class="actions"><a class="btn btn-light" href="?ano=<?= (int) $ano - 1 ?>">‹ <?= (int) $ano - 1 ?></a><a class="btn btn-light" href="?ano=<?= (int) $ano + 1 ?>"><?= (int) $ano + 1 ?> ›</a></div>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="table-card"><table class="table">
        <thead><tr><th>Data</th><th>Feriado</th><th>Abrangência</th><th></th></tr></thead>
        <tbody>
        <?php if (!$feriados): ?><tr><td colspan="4"><div class="empty-state">Nenhum feriado cadastrado em <?= (int) $ano ?>.</div></td></tr><?php endif; ?>
        <?php foreach ($feriados as $f): ?>
            <tr><td class="text-nowrap"><?= e(data_br($f['data'])) ?> <span class="small-muted"><?= e(['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][(int) date('w', strtotime($f['data']))]) ?></span></td><td><?= e($f['descricao']) ?></td><td><?= e($f['abrangencia']) ?></td>
                <td><form method="post" action="<?= e(url('/cadastros/feriados/excluir')) ?>" data-confirm="Excluir o feriado de <?= e(data_br($f['data'])) ?>?" data-confirm-button="Excluir"><?= csrf_field() ?><input type="hidden" name="data" value="<?= e($f['data']) ?>"><button class="btn btn-light btn-sm text-danger" type="submit" aria-label="Excluir"><i class="bi bi-trash"></i></button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-header"><i class="bi bi-plus-circle"></i> Incluir feriado</div><div class="card-body">
        <form method="post" action="<?= e(url('/cadastros/feriados')) ?>" class="row g-3" novalidate>
            <?= csrf_field() ?>
            <?= \App\Helpers\Form::input('data', 'Data', '', ['req' => true, 'type' => 'date', 'col' => 'col-md-6']) ?>
            <?= \App\Helpers\Form::select('abrangencia', 'Abrangência', ['Nacional', 'Estadual', 'Municipal'], 'Municipal', ['req' => true, 'col' => 'col-md-6', 'vazio' => false]) ?>
            <?= \App\Helpers\Form::input('descricao', 'Descrição', '', ['req' => true, 'col' => 'col-12', 'maxlength' => 120, 'placeholder' => 'ex.: Aniversário de São Luís']) ?>
            <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Incluir</button></div>
        </form>
    </div></div></div>
</div>
