<h1>Recuperar senha</h1>
<?php if ($emailAtivo): ?>
    <p class="lead-sm">Informe o e-mail cadastrado. Enviaremos um link para você criar uma nova senha.</p>
    <form method="post" action="<?= e(url('/esqueci-senha')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-4">
            <label class="form-label" for="email">E-mail</label>
            <input class="form-control form-control-lg" id="email" name="email" type="email" autocomplete="email" required data-autofocus>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-envelope"></i> Enviar link</button>
    </form>
<?php else: ?>
    <div class="alert alert-info mt-3"><i class="bi bi-info-circle"></i>
        <div>Peça ao <b>administrador do sistema</b> para redefinir sua senha. Ele vai gerar uma senha provisória, e no próximo acesso você cria a sua senha pessoal.</div>
    </div>
<?php endif; ?>
<a class="btn btn-link px-0 mt-3" href="<?= e(url('/login')) ?>"><i class="bi bi-arrow-left"></i> Voltar para o login</a>
