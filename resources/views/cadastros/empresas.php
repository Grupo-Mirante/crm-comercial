<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Empresas emissoras</nav>
<div class="page-head"><div><h1>Empresas emissoras</h1><p>Dados que saem no cabeçalho da PI. Campos vazios não aparecem na PI.</p></div></div>
<?php foreach ($empresas as $em): $pend = !$em['razao_social'] || !$em['cnpj']; $pfx = 'empresa_' . $em['id'] . '_'; ?>
<form method="post" action="<?= e(url('/cadastros/empresas/' . $em['id'])) ?>" class="form-section" novalidate>
    <?= csrf_field() ?>
    <div class="form-section-head"><i class="bi bi-building text-navy"></i><h2><?= e($em['nome_curto']) ?></h2>
        <p><?= e($em['veiculos'] ?: 'sem veículo') ?><?= $pend ? ' · <span class="text-red fw-semibold">dados a preencher</span>' : '' ?></p></div>
    <div class="form-section-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Nome curto <span class="req">*</span></label><input class="form-control<?= tem_erro($pfx . 'nome_curto') ?>" name="nome_curto" value="<?= e($em['nome_curto']) ?>" maxlength="60"><?= erro($pfx . 'nome_curto') ?></div>
        <div class="col-md-5"><label class="form-label">Razão social</label><input class="form-control" name="razao_social" value="<?= e($em['razao_social']) ?>" maxlength="160" placeholder="a preencher"></div>
        <div class="col-md-4"><label class="form-label">CNPJ</label><input class="form-control<?= tem_erro($pfx . 'cnpj') ?>" name="cnpj" value="<?= e($em['cnpj']) ?>" data-mask="cnpj" placeholder="a preencher"><?= erro($pfx . 'cnpj') ?></div>
        <div class="col-md-6"><label class="form-label">Endereço</label><input class="form-control" name="endereco" value="<?= e($em['endereco']) ?>" maxlength="255"></div>
        <div class="col-md-3"><label class="form-label">Telefone</label><input class="form-control" name="telefone" value="<?= e($em['telefone']) ?>" maxlength="60"></div>
        <div class="col-md-3"><label class="form-label">Inscrição municipal</label><input class="form-control" name="inscricao_municipal" value="<?= e($em['inscricao_municipal']) ?>" maxlength="40"></div>
        <div class="col-md-9"><label class="form-label">Dados bancários</label><input class="form-control" name="dados_bancarios" value="<?= e($em['dados_bancarios']) ?>" maxlength="255" placeholder="Banco · Agência · Conta · PIX"></div>
        <div class="col-md-3 d-flex align-items-end gap-3"><input type="hidden" name="ativo" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ativo" value="1" id="at<?= (int) $em['id'] ?>"<?= (int) $em['ativo'] ? ' checked' : '' ?>><label class="form-check-label" for="at<?= (int) $em['id'] ?>">Ativa</label></div>
            <button class="btn btn-primary ms-auto" type="submit"><i class="bi bi-check2"></i> Salvar</button></div>
    </div></div>
</form>
<?php endforeach; ?>
