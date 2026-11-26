<?php

use App\Helpers\Menu;
use App\Core\Database;

/** @var string $content */
$titulo = $titulo ?? 'Painel';
$rota = $rotaAtual ?? '/';
$menu = Menu::montar($rota);
$u = $usuario ?? null;
$iniciais = $u ? mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim((string) $u['nome'])) ?: [], 0, 2)))) : '';
$notifs = $u ? Database::all('SELECT id, titulo, mensagem, link, criado_em, lida_em FROM notificacoes WHERE usuario_id = ? ORDER BY lida_em IS NULL DESC, id DESC LIMIT 6', [(int) $u['id']]) : [];
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
    <?php foreach (($css ?? []) as $c): ?><link rel="stylesheet" href="<?= e(asset($c)) ?>"><?php endforeach; ?>
</head>
<body>
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#conteudo">Ir para o conteúdo</a>
<div class="app">
    <aside class="sidebar" id="menu-lateral" aria-label="Menu principal">
        <div class="brand">
            <a href="<?= e(url('/')) ?>" aria-label="Início"><img src="<?= e(asset('img/logo-grupo-mirante-branca-web.png')) ?>" alt="Grupo Mirante"></a>
            <small>CRM Comercial</small>
        </div>
        <nav>
            <?php foreach ($menu as $g): ?>
                <?php if ($g['grupo']): ?><div class="nav-group"><?= e($g['grupo']) ?></div><?php endif; ?>
                <?php foreach ($g['itens'] as $it): ?>
                    <a class="nav-link<?= $it['ativo'] ? ' active' : '' ?>" href="<?= e(url($it['url'])) ?>"<?= $it['ativo'] ? ' aria-current="page"' : '' ?>>
                        <i class="bi bi-<?= e($it['icone']) ?>"></i><span><?= e($it['rotulo']) ?></span>
                        <?php if ($it['n'] > 0): ?><span class="badge rounded-pill"><?= (int) $it['n'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">Grupo Mirante de Comunicação<br>Versão <?= e(\App\Services\Configuracao::texto('versao_sistema', '1.0.0')) ?></div>
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-light btn-icon menu-toggle" type="button" aria-label="Abrir menu" aria-controls="menu-lateral" aria-expanded="false"><i class="bi bi-list fs-5"></i></button>
            <p class="page-title"><?= e($titulo) ?></p>
            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if (can('pis.criar') && in_array('/pis/nova', \App\Core\Router::$caminhos, true) && !str_starts_with($rota, '/pis/nova')): ?>
                    <a class="btn btn-danger btn-sm d-none d-md-inline-flex" href="<?= e(url('/pis/nova')) ?>"><i class="bi bi-plus-lg"></i> Nova PI</a>
                <?php endif; ?>
                <div class="dropdown bell">
                    <button class="btn btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Avisos (<?= (int) ($naoLidas ?? 0) ?> não lidos)">
                        <i class="bi bi-bell"></i><?php if (($naoLidas ?? 0) > 0): ?><span class="dot"><?= (int) $naoLidas > 99 ? '99+' : (int) $naoLidas ?></span><?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px">
                        <div class="px-3 py-2 border-bottom d-flex align-items-center"><b class="text-navy">Avisos</b><a class="ms-auto small" href="<?= e(url('/notificacoes')) ?>">ver todos</a></div>
                        <?php if (!$notifs): ?><div class="px-3 py-4 text-center small-muted">Nenhum aviso.</div><?php endif; ?>
                        <?php foreach ($notifs as $n): ?>
                            <a class="dropdown-item py-2 border-bottom<?= $n['lida_em'] ? '' : ' fw-semibold' ?>" style="white-space:normal" data-notif="<?= (int) $n['id'] ?>" href="<?= e(url($n['link'] ?: '/notificacoes')) ?>">
                                <div class="small"><?= e($n['titulo']) ?></div>
                                <div class="small-muted"><?= e(data_hora_br($n['criado_em'])) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="dropdown user">
                    <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e($iniciais) ?></span>
                        <span class="user-name text-start text-body"><?= e($u['nome'] ?? '') ?><small><?= e($u['perfil_nome'] ?? '') ?></small></span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small-muted">Último acesso: <?= e(data_hora_br($u['ultimo_acesso_em'] ?? '')) ?></span></li>
                        <li><a class="dropdown-item" href="<?= e(url('/minha-senha')) ?>"><i class="bi bi-key me-2"></i>Alterar senha</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sair</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="content" id="conteudo">
            <?= $content ?>
        </main>
        <footer class="footer d-flex flex-wrap gap-2">
            <span>© <?= date('Y') ?> Grupo Mirante de Comunicação · CRM Comercial</span>
            <span class="ms-auto">Horário de Brasília · <?= e(date('d/m/Y H:i')) ?></span>
        </footer>
    </div>
</div>
<?php foreach (($flash ?? []) as $tipo => $msg): ?>
    <span hidden data-flash="<?= e($msg) ?>" data-tipo="<?= e($tipo) ?>"></span>
<?php endforeach; ?>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach (($js ?? []) as $j): ?><script src="<?= e(asset($j)) ?>"></script><?php endforeach; ?>
</body>
</html>
