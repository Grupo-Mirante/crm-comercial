<?php $gerencia = can('usuarios.gerenciar'); ?>
<nav class="breadcrumb"><a href="<?= e(url('/usuarios')) ?>">Usuários</a>&nbsp;›&nbsp;<?= e($u['nome']) ?></nav>
<div class="page-head">
    <div><h1><?= e($u['nome']) ?></h1><p><code><?= e($u['login']) ?></code> · <?= e($u['perfil']) ?> · <?= (int) $u['ativo'] ? selo('Ativo') : selo('Inativo') ?></p></div>
    <?php if ($gerencia): ?>
    <div class="actions">
        <a class="btn btn-outline-primary" href="<?= e(url('/usuarios/' . $u['id'] . '/editar')) ?>"><i class="bi bi-pencil"></i> Editar</a>
        <form method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/redefinir-senha')) ?>" data-confirm="Gerar uma senha provisória para <?= e($u['nome']) ?>? A senha atual deixa de valer e as sessões abertas são encerradas." data-confirm-button="Gerar senha" data-confirm-danger="0">
            <?= csrf_field() ?><button class="btn btn-light" type="submit"><i class="bi bi-key"></i> Redefinir senha</button></form>
        <form method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/ativo')) ?>" data-confirm="<?= (int) $u['ativo'] ? 'Desativar ' . e($u['nome']) . '? A pessoa não entra mais no sistema e as sessões abertas são encerradas. As PIs dela continuam no registro.' : 'Ativar ' . e($u['nome']) . '?' ?>" data-confirm-button="<?= (int) $u['ativo'] ? 'Desativar' : 'Ativar' ?>" data-confirm-danger="<?= (int) $u['ativo'] ? '1' : '0' ?>">
            <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= (int) $u['ativo'] ? '0' : '1' ?>">
            <button class="btn btn-light<?= (int) $u['ativo'] ? ' text-danger' : '' ?>" type="submit"><i class="bi bi-<?= (int) $u['ativo'] ? 'person-x' : 'person-check' ?>"></i> <?= (int) $u['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
    </div>
    <?php endif; ?>
</div>

<?php if ($senhaUnica): ?>
<div class="alert alert-warning align-items-center"><i class="bi bi-key-fill"></i>
    <div class="flex-grow-1"><b>Senha provisória de <?= e($u['login']) ?>:</b> <span class="once-secret ms-2" id="senha-unica"><?= e($senhaUnica) ?></span>
        <div class="small mt-2">Ela aparece <b>só agora</b>. Entregue à pessoa por um canal seguro; no primeiro acesso ela cria a senha pessoal.</div></div>
    <button class="btn btn-light btn-sm" type="button" data-copy="#senha-unica"><i class="bi bi-clipboard"></i> Copiar</button>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-person-vcard"></i> Cadastro</div><div class="card-body"><dl class="dl-grid mb-0" style="grid-template-columns:1fr 1fr">
            <div><dt>Matrícula</dt><dd><?= e($u['matricula'] ?: '—') ?></dd></div>
            <div><dt>Gestor</dt><dd><?= e($u['gestor'] ?: '—') ?></dd></div>
            <div><dt>E-mail</dt><dd><?= e($u['email'] ?: '—') ?></dd></div>
            <div><dt>Telefone</dt><dd><?= e($u['telefone'] ?: '—') ?></dd></div>
            <div><dt>Último acesso</dt><dd><?= e($u['ultimo_acesso_em'] ? data_hora_br($u['ultimo_acesso_em']) : 'nunca') ?></dd></div>
            <div><dt>Senha</dt><dd><?= (int) $u['trocar_senha'] ? 'provisória (troca no próximo acesso)' : 'pessoal' ?><?= $u['senha_alterada_em'] ? '<div class="small-muted">desde ' . e(data_hora_br($u['senha_alterada_em'])) . '</div>' : '' ?></dd></div>
            <div><dt>Cadastrado em</dt><dd><?= e(data_hora_br($u['created_at'])) ?></dd></div>
        </dl></div></div>
        <?php if ($equipe): ?>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-people"></i> Equipe (<?= count($equipe) ?>)</div>
            <ul class="list-group list-group-flush"><?php foreach ($equipe as $m): ?><li class="list-group-item d-flex"><a href="<?= e(url('/usuarios/' . $m['id'])) ?>"><?= e($m['nome']) ?></a><span class="ms-auto"><?= (int) $m['ativo'] ? '' : selo('Inativo') ?></span></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-pc-display"></i> Sessões abertas <?php if ($gerencia && $sessoes): ?>
            <form class="ms-auto" method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/encerrar-sessoes')) ?>" data-confirm="Encerrar todas as sessões abertas desta pessoa?" data-confirm-button="Encerrar"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit">Encerrar todas</button></form><?php endif; ?></div>
            <ul class="list-group list-group-flush">
                <?php if (!$sessoes): ?><li class="list-group-item small-muted">Nenhuma sessão aberta.</li><?php endif; ?>
                <?php foreach ($sessoes as $s): ?><li class="list-group-item small"><b><?= e($s['ip']) ?></b> · último uso <?= e(data_hora_br($s['uso'])) ?><div class="small-muted text-truncate"><?= e($s['user_agent']) ?></div></li><?php endforeach; ?>
            </ul></div>
        <div class="card"><div class="card-header"><i class="bi bi-door-open"></i> Últimas tentativas de acesso</div>
            <ul class="list-group list-group-flush">
                <?php if (!$tentativas): ?><li class="list-group-item small-muted">Nenhuma tentativa registrada.</li><?php endif; ?>
                <?php foreach ($tentativas as $t): ?><li class="list-group-item small d-flex gap-2"><i class="bi bi-<?= (int) $t['sucesso'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i><span><?= e(data_hora_br($t['criado_em'])) ?> · <?= e($t['ip']) ?></span><span class="ms-auto small-muted"><?= e((int) $t['sucesso'] ? 'entrou' : ['senha' => 'senha errada', 'bloqueado' => 'bloqueado', 'inativo' => 'usuário desativado', 'ip_bloqueado' => 'IP bloqueado'][$t['motivo']] ?? (string) $t['motivo']) ?></span></li><?php endforeach; ?>
            </ul></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header"><i class="bi bi-clock-history"></i> Histórico de atividades</div>
            <div class="table-responsive"><table class="table">
                <thead><tr><th>Quando</th><th>Ação</th><th>Detalhe</th></tr></thead>
                <tbody>
                <?php if (!$atividades): ?><tr><td colspan="3"><div class="empty-state">Sem atividades registradas.</div></td></tr><?php endif; ?>
                <?php foreach ($atividades as $a): ?>
                    <tr><td class="small text-nowrap"><?= e(data_hora_br($a['criado_em'])) ?></td><td class="small"><code><?= e($a['acao']) ?></code></td><td class="small"><?= e($a['descricao']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
