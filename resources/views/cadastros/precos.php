<?php
use App\Helpers\Form;

$tipo = $t['tipo'];
$v = fn ($x) => $x === null || $x === '' ? '' : Form::moeda($x);
?>
<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;<a href="<?= e(url('/cadastros/tabelas?ano=' . $t['ano_vigencia'])) ?>">Tabelas de preços</a>&nbsp;›&nbsp;<?= e($t['veiculo'] . ' · ' . $t['codigo']) ?></nav>
<div class="page-head"><div><h1><?= e($t['veiculo']) ?> · <?= e($t['codigo']) ?> · <?= (int) $t['ano_vigencia'] ?></h1>
    <p><?= e($t['descricao']) ?> · <?= $tipo === 'TV' ? 'preço do 30" por programa; as demais durações usam o índice (RN-12). GRP e impactos alimentam o CPM (RN-25).' : ($tipo === 'Rádio' ? 'preço por duração e cota de patrocínio (RN-13, RN-26).' : 'preço do formato pelo modelo de cobrança (RN-14). Vazio = sob consulta.') ?> Deixe em branco o que não tem preço.</p></div></div>
<form method="post" action="<?= e(url('/cadastros/tabelas/' . $t['id'] . '/precos')) ?>" data-confirm="Salvar os preços desta tabela? As PIs já geradas não mudam." data-confirm-danger="0">
    <?= csrf_field() ?>
    <div class="filters py-2"><input class="form-control form-control-sm" type="search" placeholder="Filtrar por sigla ou nome" data-filter-target="#tabPrecos" aria-label="Filtrar"></div>
    <div class="table-card mb-3"><div class="table-responsive"><table class="table table-sm align-middle" id="tabPrecos">
        <?php if ($tipo === 'Digital'): ?>
            <thead><tr><th>Sigla</th><th>Formato</th><th>Modelo</th><th style="width:170px" class="num">Preço (R$)</th></tr></thead>
            <tbody><?php foreach ($linhas as $f): ?>
                <tr data-busca="<?= e(normaliza($f['sigla'] . ' ' . $f['nome'])) ?>"><td><code><?= e($f['sigla']) ?></code></td><td class="small"><?= e($f['nome']) ?></td><td class="small"><?= e($f['modelo']) ?><?= $f['modelo'] === 'cm/col' ? ' (por cm/col)' : '' ?></td>
                    <td><input class="form-control form-control-sm text-end" name="preco[f<?= (int) $f['id'] ?>|unitario|0]" value="<?= e($v($f['valor'])) ?>" data-mask="moeda" inputmode="decimal" placeholder="sob consulta"></td></tr>
            <?php endforeach; ?></tbody>
        <?php elseif ($tipo === 'TV'): ?>
            <thead><tr><th>Sigla</th><th>Programa</th><th>Dias · faixa</th><th style="width:150px" class="num">30" (R$)</th><th style="width:120px" class="num">GRP</th><th style="width:130px" class="num">Impactos</th></tr></thead>
            <tbody><?php foreach ($linhas as $p): ?>
                <tr data-busca="<?= e(normaliza($p['sigla'] . ' ' . $p['nome'])) ?>"><td><code><?= e($p['sigla']) ?></code></td><td class="small"><?= e($p['nome']) ?></td><td class="small"><?= e(trim($p['dias_exibicao'] . ' ' . $p['faixa_horaria'])) ?></td>
                    <td><input class="form-control form-control-sm text-end" name="preco[p<?= (int) $p['id'] ?>|base_30s|0]" value="<?= e($v($p['precos']['base_30s:0'] ?? null)) ?>" data-mask="moeda" inputmode="decimal"></td>
                    <td><input class="form-control form-control-sm text-end" name="aud[<?= (int) $p['id'] ?>][grp]" value="<?= e($p['aud'] ? rtrim(rtrim(number_format((float) $p['aud']['grp'], 5, ',', ''), '0'), ',') : '') ?>" inputmode="decimal"></td>
                    <td><input class="form-control form-control-sm text-end" name="aud[<?= (int) $p['id'] ?>][impactos]" value="<?= e($p['aud'] ? (string) $p['aud']['impactos'] : '') ?>" data-mask="inteiro"></td></tr>
            <?php endforeach; ?></tbody>
        <?php else: ?>
            <thead><tr><th>Sigla</th><th>Programa</th><?php foreach ($duracoes as $d): ?><th class="num" style="width:110px"><?= (int) $d ?>"</th><?php endforeach; ?><th class="num" style="width:130px">Cota patrocínio</th></tr></thead>
            <tbody><?php foreach ($linhas as $p): ?>
                <tr data-busca="<?= e(normaliza($p['sigla'] . ' ' . $p['nome'])) ?>"><td><code><?= e($p['sigla']) ?></code></td><td class="small"><?= e($p['nome']) ?><div class="small-muted"><?= e(trim($p['dias_exibicao'] . ' ' . $p['faixa_horaria'])) ?></div></td>
                    <?php foreach ($duracoes as $d): ?><td><input class="form-control form-control-sm text-end" name="preco[p<?= (int) $p['id'] ?>|duracao|<?= (int) $d ?>]" value="<?= e($v($p['precos']['duracao:' . $d] ?? null)) ?>" data-mask="moeda" inputmode="decimal" aria-label="<?= e($p['sigla'] . ' ' . $d) ?> segundos"></td><?php endforeach; ?>
                    <td><input class="form-control form-control-sm text-end" name="preco[p<?= (int) $p['id'] ?>|cota_patrocinio|0]" value="<?= e($v($p['precos']['cota_patrocinio:0'] ?? null)) ?>" data-mask="moeda" inputmode="decimal" aria-label="<?= e($p['sigla']) ?> cota de patrocínio"></td></tr>
            <?php endforeach; ?></tbody>
        <?php endif; ?>
    </table></div></div>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url('/cadastros/tabelas?ano=' . $t['ano_vigencia'])) ?>">Voltar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar preços</button></div>
</form>
