<?php
/** Layout das telas sem menu (login, primeiro acesso, recuperação de senha, erros). @var string $content */
$titulo = $titulo ?? 'Entrar';
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(rtrim(url('/'), '/')) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo) ?> · CRM Grupo Mirante</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-page">
    <section class="auth-side" aria-hidden="true">
        <img src="<?= e(asset('img/logo-grupo-mirante-branca-web.png')) ?>" alt="">
        <div>
            <span class="accent"></span>
            <h2>Toda venda vira uma PI numerada, conferida e com dono.</h2>
            <p>CRM Comercial do Grupo Mirante: Rede Mirante TV, Mirante FM, Mirante News, G1 Maranhão e Imirante em um só lugar.</p>
        </div>
        <small class="text-white-50">© <?= date('Y') ?> Grupo Mirante de Comunicação</small>
    </section>
    <main class="auth-main">
        <div class="auth-box">
            <img class="logo-mobile" src="<?= e(asset('img/logo-grupo-mirante-azul.png')) ?>" alt="Grupo Mirante">
            <?= $content ?>
        </div>
    </main>
</div>
<?php foreach (($flash ?? []) as $tipo => $msg): ?>
    <span hidden data-flash="<?= e($msg) ?>" data-tipo="<?= e($tipo) ?>"></span>
<?php endforeach; ?>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
