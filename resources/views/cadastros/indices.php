<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Índices de duração</nav>
<div class="page-head"><div><h1>Índices de duração (TV)</h1><p>RN-12: preço da TV = preço do 30" na praça × índice da duração. O 15" usa o índice próprio do programa, quando cadastrado.</p></div></div>
<form method="post" action="<?= e(url('/cadastros/indices')) ?>" data-confirm="Alterar os índices muda o preço de todas as próximas PIs de TV. Confirmar?" data-confirm-danger="0" class="row">
    <?= csrf_field() ?>
    <div class="col-lg-6">
        <?= erro('indice') ?>
        <div class="table-card mb-3"><table class="table">
            <thead><tr><th>Duração</th><th style="width:180px">Índice</th></tr></thead>
            <tbody>
            <?php foreach ($indices as $i): ?>
                <tr><td><?= (int) $i['duracao_seg'] ?> segundos</td>
                    <td><input class="form-control form-control-sm text-end" name="indice[<?= (int) $i['duracao_seg'] ?>]" value="<?= e(rtrim(rtrim(number_format((float) $i['indice'], 4, ',', ''), '0'), ',')) ?>" inputmode="decimal"<?= (int) $i['duracao_seg'] === 30 ? ' readonly' : '' ?>></td></tr>
            <?php endforeach; ?>
                <tr class="bg-soft"><td><div class="input-group input-group-sm"><input class="form-control" type="number" name="nova_duracao" min="1" max="600" placeholder="nova duração"><span class="input-group-text">s</span></div></td>
                    <td><input class="form-control form-control-sm text-end" name="novo_indice" placeholder="índice" inputmode="decimal"></td></tr>
            </tbody>
        </table></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar índices</button></div>
    </div>
</form>
