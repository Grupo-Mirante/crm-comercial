<?php
use App\Helpers\Form;

$novo = empty($pr['id']);
$tipo = $pr['veiculo_id'] ? (string) \App\Core\Database::value('SELECT tipo FROM veiculos WHERE id = ?', [(int) $pr['veiculo_id']]) : '';
?>
<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;<a href="<?= e(url('/cadastros/programas')) ?>">Programas</a>&nbsp;›&nbsp;<?= $novo ? 'Novo' : e($pr['sigla']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Novo programa' : e($pr['sigla'] . ' — ' . $pr['nome']) ?></h1><p>Depois de salvar, informe os preços do programa em cada tabela/praça.</p></div></div>
<form method="post" action="<?= e($novo ? url('/cadastros/programas') : url('/cadastros/programas/' . $pr['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section"><div class="form-section-head"><span class="step">1</span><h2>Programa</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::select('veiculo_id', 'Veículo', $veiculos, $pr['veiculo_id'] ?? '', ['req' => true, 'col' => 'col-md-3', 'disabled' => !$novo]) ?>
        <?php if (!$novo): ?><input type="hidden" name="veiculo_id" value="<?= (int) $pr['veiculo_id'] ?>"><?php endif; ?>
        <?= Form::input('sigla', 'Sigla', $pr['sigla'] ?? '', ['req' => true, 'col' => 'col-md-2', 'maxlength' => 12, 'help' => 'Letras e números']) ?>
        <?= Form::input('nome', 'Nome', $pr['nome'] ?? '', ['req' => true, 'col' => 'col-md-5', 'maxlength' => 120]) ?>
        <?= Form::check('ativo', 'Ativo', (bool) ($pr['ativo'] ?? 1), ['col' => 'col-md-2']) ?>
        <?= Form::input('dias_exibicao', 'Dias de exibição', $pr['dias_exibicao'] ?? '', ['col' => 'col-md-4', 'maxlength' => 40, 'help' => 'SEG/SEX, SEG/SÁB, SEG/DOM, SÁB/DOM, TER/QUI, QUA, DIÁRIO. Vazio ou "Variável" = sem restrição.']) ?>
        <?= Form::input('faixa_horaria', 'Faixa horária', $pr['faixa_horaria'] ?? '', ['col' => 'col-md-4', 'maxlength' => 40, 'placeholder' => 'ex.: 06:00 às 09:00']) ?>
        <?php if ($tipo === 'TV' || $novo): ?>
            <?= Form::input('indice_15s', 'Índice do 15" (TV)', isset($pr['indice_15s']) && $pr['indice_15s'] !== null ? rtrim(rtrim(number_format((float) $pr['indice_15s'], 4, ',', ''), '0'), ',') : '', ['col' => 'col-md-4', 'help' => 'Só TV. No Gerador original: 0,5 ou 0,75 conforme o programa. Vazio usa o índice padrão do 15".']) ?>
        <?php endif; ?>
    </div></div></section>
    <?php if ($tipo === 'Rádio' || $novo): ?>
    <section class="form-section"><div class="form-section-head"><span class="step">2</span><h2>Esquema de inserções por cota de patrocínio (rádio · RN-26)</h2><p>Inserções = esquema × número de cotas</p></div><div class="form-section-body"><div class="row g-3">
        <?php foreach (['ins_30' => '30"', 'ins_60' => '60"', 'vinheta' => 'Vinheta', 'flash' => 'Flash', 'testemunhal' => 'Testemunhal', 'entrevista' => 'Entrevista'] as $k => $rot): ?>
            <div class="col-6 col-md-2"><label class="form-label" for="esq_<?= $k ?>"><?= e($rot) ?></label><input class="form-control" type="number" min="0" id="esq_<?= $k ?>" name="esq_<?= $k ?>" value="<?= (int) ($esq[$k] ?? 0) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12 form-text">Deixe tudo zerado se o programa não é vendido como patrocínio.</div>
    </div></div></section>
    <?php endif; ?>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url('/cadastros/programas')) ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar programa</button></div>
</form>
