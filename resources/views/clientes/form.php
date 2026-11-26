<?php

use App\Helpers\Form;

$novo = empty($c['id']);
$acao = $novo ? url('/clientes') : url('/clientes/' . $c['id']);
?>
<nav class="breadcrumb"><a href="<?= e(url('/clientes')) ?>">Clientes</a>&nbsp;›&nbsp;<?= $novo ? 'Novo' : e($c['razao_social']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Novo cliente' : 'Editar cliente' ?></h1><p>Campos com <span class="req">*</span> são obrigatórios. CNPJ alfanumérico da Receita também é aceito.</p></div></div>
<form method="post" action="<?= e($acao) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <div class="form-section-head"><span class="step">1</span><h2>Identificação</h2><p>O documento não pode se repetir no cadastro</p></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('razao_social', 'Razão social (ou nome completo)', $c['razao_social'] ?? '', ['req' => true, 'col' => 'col-md-8', 'maxlength' => 160, 'attrs' => ['data-autofocus' => true]]) ?>
            <?= Form::input('documento', 'CNPJ / CPF', $c['documento_formatado'] ?? '', ['req' => true, 'col' => 'col-md-4', 'mask' => 'doc', 'placeholder' => '00.000.000/0000-00']) ?>
            <?= Form::input('nome_fantasia', 'Nome fantasia', $c['nome_fantasia'] ?? '', ['col' => 'col-md-5', 'maxlength' => 160]) ?>
            <?= Form::input('inscricao_estadual', 'Inscrição estadual', $c['inscricao_estadual'] ?? '', ['col' => 'col-md-3', 'maxlength' => 30]) ?>
            <?= Form::input('codigo_interno', 'Código interno', $c['codigo_interno'] ?? '', ['col' => 'col-md-2', 'maxlength' => 30]) ?>
            <?= Form::select('situacao_cadastral', 'Situação cadastral', ['Ativo', 'Em análise', 'Bloqueado', 'Inativo'], $c['situacao_cadastral'] ?? 'Ativo', ['col' => 'col-md-2', 'vazio' => false, 'help' => 'Definida pelo Financeiro']) ?>
        </div></div>
    </section>
    <section class="form-section">
        <div class="form-section-head"><span class="step">2</span><h2>Endereço</h2></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('endereco', 'Endereço', $c['endereco'] ?? '', ['col' => 'col-md-6', 'maxlength' => 200, 'help' => 'Obrigatório na PI (D01)']) ?>
            <?= Form::input('bairro', 'Bairro', $c['bairro'] ?? '', ['col' => 'col-md-3', 'maxlength' => 80]) ?>
            <?= Form::input('cep', 'CEP', $c['cep'] ?? '', ['col' => 'col-md-3', 'mask' => 'cep', 'placeholder' => '00000-000']) ?>
            <?= Form::input('cidade', 'Cidade', $c['cidade'] ?? '', ['col' => 'col-md-6', 'maxlength' => 80]) ?>
            <?= Form::select('uf', 'UF', Form::ufs(), $c['uf'] ?? 'MA', ['col' => 'col-md-2']) ?>
        </div></div>
    </section>
    <section class="form-section">
        <div class="form-section-head"><span class="step">3</span><h2>Contato e faturamento</h2><p>Use o e-mail do financeiro do cliente, não um e-mail pessoal</p></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('contato', 'Contato no cliente', $c['contato'] ?? '', ['col' => 'col-md-4', 'maxlength' => 120]) ?>
            <?= Form::input('telefone', 'Telefones', $c['telefone'] ?? '', ['col' => 'col-md-4', 'mask' => 'telefone', 'placeholder' => '(98) 0000-0000']) ?>
            <?= Form::input('email_faturamento', 'E-mail de faturamento', $c['email_faturamento'] ?? '', ['col' => 'col-md-4', 'type' => 'email', 'maxlength' => 160]) ?>
        </div></div>
    </section>
    <div class="form-actions">
        <a class="btn btn-light" href="<?= e(url($novo ? '/clientes' : '/clientes/' . $c['id'])) ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> <?= $novo ? 'Cadastrar cliente' : 'Salvar alterações' ?></button>
    </div>
</form>
