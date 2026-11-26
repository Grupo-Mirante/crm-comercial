<?php use App\Helpers\Form; ?>
<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;<a href="<?= e(url('/cadastros/veiculos')) ?>">Veículos</a>&nbsp;›&nbsp;<?= e($v['nome']) ?></nav>
<div class="page-head"><div><h1><?= e($v['nome']) ?></h1><p>Prefixo da numeração: <code><?= e($v['prefixo']) ?></code> (fixo, para não repetir números) · <?= e($v['tipo']) ?><?= (int) $v['divide_pi_por_mes'] ? ' · gera uma PI por mês (RN-17)' : '' ?></p></div></div>
<form method="post" action="<?= e(url('/cadastros/veiculos/' . $v['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section"><div class="form-section-head"><span class="step">1</span><h2>Identificação</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::input('nome', 'Nome do veículo', $v['nome'], ['req' => true, 'col' => 'col-md-4', 'maxlength' => 60]) ?>
        <?= Form::select('empresa_id', 'Empresa emissora', $empresas, $v['empresa_id'], ['req' => true, 'col' => 'col-md-4']) ?>
        <?= Form::input('titulo_pi', 'Título no cabeçalho da PI', $v['titulo_pi'], ['req' => true, 'col' => 'col-md-4', 'maxlength' => 120]) ?>
        <?= Form::check('ativo', 'Veículo ativo (aparece na Nova PI)', (bool) $v['ativo'], ['col' => 'col-md-6']) ?>
    </div></div></section>
    <section class="form-section"><div class="form-section-head"><span class="step">2</span><h2>Blocos de mídia (RN-09)</h2><p>Projetos especiais e entregas especiais valem para todos</p></div><div class="form-section-body"><div class="row g-2">
        <?= Form::check('bloco_insercoes', 'Inserções (grade por dia)', (bool) $v['bloco_insercoes'], ['col' => 'col-md-3']) ?>
        <?= Form::check('bloco_patrocinio', 'Patrocínio (esquema × cotas)', (bool) $v['bloco_patrocinio'], ['col' => 'col-md-3']) ?>
        <?= Form::check('bloco_digital', 'Formatos digitais', (bool) $v['bloco_digital'], ['col' => 'col-md-3']) ?>
        <?= Form::check('bloco_publicidade_legal', 'Publicidade legal (só Imirante)', (bool) $v['bloco_publicidade_legal'], ['col' => 'col-md-3']) ?>
        <?= erro('bloco_insercoes') ?><?= erro('bloco_publicidade_legal') ?>
    </div></div></section>
    <section class="form-section"><div class="form-section-head"><span class="step">3</span><h2>Textos próprios do veículo na PI</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::textarea('clausula_6', 'Cláusula 6', $v['clausula_6'], ['col' => 'col-12', 'rows' => 2]) ?>
        <?= Form::textarea('clausula_7', 'Cláusula 7', $v['clausula_7'], ['col' => 'col-12', 'rows' => 2]) ?>
        <?= Form::textarea('texto_atendimento', 'Atendimento comercial (rodapé)', $v['texto_atendimento'], ['col' => 'col-12', 'rows' => 2, 'maxlength' => 400]) ?>
    </div></div></section>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url('/cadastros/veiculos')) ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar veículo</button></div>
</form>
