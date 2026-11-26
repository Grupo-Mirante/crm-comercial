<h1>Entrar</h1>
<p class="lead-sm">Acesso da equipe comercial do Grupo Mirante.</p>
<form method="post" action="<?= e(url('/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label class="form-label" for="login">Usuário</label>
        <input class="form-control form-control-lg" id="login" name="login" value="<?= e(old('login')) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required data-autofocus>
    </div>
    <div class="mb-2">
        <label class="form-label" for="senha">Senha</label>
        <div class="pw-wrap">
            <input class="form-control form-control-lg" id="senha" name="senha" type="password" autocomplete="current-password" required>
            <button type="button" class="pw-toggle" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="d-flex justify-content-end mb-4">
        <a class="small" href="<?= e(url('/esqueci-senha')) ?>">Esqueci minha senha</a>
    </div>
    <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
</form>
<p class="small-muted mt-4 mb-0"><i class="bi bi-shield-lock"></i> Por segurança, após 5 senhas erradas seguidas o acesso fica bloqueado por alguns segundos.</p>
