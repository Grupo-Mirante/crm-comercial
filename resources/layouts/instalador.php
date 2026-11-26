<?php
/** Layout do assistente de instalação (sem banco, sem sessão). @var string $content */
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-url" content="<?= e(rtrim(url('/'), '/')) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo ?? 'Instalação') ?> · CRM Grupo Mirante</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="inst-body">
<header class="inst-top">
    <img src="<?= e(asset('img/logo-grupo-mirante-branca-web.png')) ?>" alt="Grupo Mirante">
    <span>Instalação do CRM Comercial</span>
</header>
<main class="inst-main">
    <?= $content ?>
</main>
<footer class="inst-foot">© <?= date('Y') ?> Grupo Mirante de Comunicação · CRM Comercial · versão <?= e(\App\Services\Instalacao\Instalador::VERSAO) ?></footer>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
