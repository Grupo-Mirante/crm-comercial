<?php use App\Services\UsuarioService; ?>
<div class="page-head">
    <div><h1>Usuários</h1><p>Cada pessoa entra com usuário e senha próprios; o perfil define o que ela vê e faz.</p></div>
    <div class="actions">
        <?php if (can('permissoes.gerenciar')): ?><a class="btn btn-light" href="<?= e(url('/permissoes')) ?>"><i class="bi bi-shield-lock"></i> Permissões por perfil</a><?php endif; ?>
        <?php if (can('usuarios.gerenciar')): ?><a class="btn btn-primary" href="<?= e(url('/usuarios/novo')) ?>"><i class="bi bi-person-plus"></i> Novo usuário</a><?php endif; ?>
    </div>
</div>
<form class="filters" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-5"><label class="form-label" for="q">Buscar</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome, usuário, e-mail ou matrícula"></div>
        <div class="col-md-3"><label class="form-label" for="perfil">Perfil</label><select class="form-select" id="perfil" name="perfil" data-autosubmit><option value="">Todos</option>
            <?php foreach ($perfis as $pf): ?><option value="<?= (int) $pf['id'] ?>"<?= $perfil === (int) $pf['id'] ? ' selected' : '' ?>><?= e($pf['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label" for="situacao">Situação</label><select class="form-select" id="situacao" name="situacao" data-autosubmit>
            <option value="ativos"<?= $sit === 'ativos' ? ' selected' : '' ?>>Ativos</option><option value="inativos"<?= $sit === 'inativos' ? ' selected' : '' ?>>Desativados</option><option value="todos"<?= $sit === 'todos' ? ' selected' : '' ?>>Todos</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Buscar</button></div>
    </div>
</form>
<div class="table-card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Nome</th><th>Usuário</th><th>Perfil</th><th>Gestor</th><th>Contato</th><th>Situação</th><th>Último acesso</th><th></th></tr></thead>
        <tbody>
        <?php if (!$p['itens']): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>Nenhum usuário encontrado.</div></td></tr><?php endif; ?>
        <?php foreach ($p['itens'] as $u): ?>
            <tr>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(url('/usuarios/' . $u['id'])) ?>"><?= e($u['nome']) ?></a><?php if ($u['matricula']): ?><div class="small-muted">Matrícula <?= e($u['matricula']) ?></div><?php endif; ?></td>
                <td><code><?= e($u['login']) ?></code></td>
                <td><?= e($u['perfil']) ?></td>
                <td><?= e($u['gestor'] ?: '—') ?></td>
                <td class="small"><?= e($u['email']) ?><div class="small-muted"><?= e($u['telefone']) ?></div></td>
                <td><?= (int) $u['ativo'] ? selo('Ativo') : selo('Inativo') ?>
                    <?php if ((int) $u['trocar_senha']): ?><div class="small-muted">senha provisória</div><?php endif; ?>
                    <?php if ($u['bloqueado_ate'] && strtotime($u['bloqueado_ate']) > time()): ?><div class="small text-red">bloqueado por tentativas</div><?php endif; ?></td>
                <td class="small text-nowrap"><?= e($u['ultimo_acesso_em'] ? data_hora_br($u['ultimo_acesso_em']) : 'nunca') ?></td>
                <td><div class="table-actions"><a class="btn btn-light btn-sm" href="<?= e(url('/usuarios/' . $u['id'])) ?>" title="Ver"><i class="bi bi-eye"></i></a>
                    <?php if (can('usuarios.gerenciar')): ?><a class="btn btn-light btn-sm" href="<?= e(url('/usuarios/' . $u['id'] . '/editar')) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
                    <?php $semRedefinir = UsuarioService::motivoSemRedefinirSenha($u); $compacto = true; include __DIR__ . '/_redefinir-senha.php'; ?><?php endif; ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<?php $query = array_filter(['q' => $q, 'perfil' => $perfil ?: null, 'situacao' => $sit]); include __DIR__ . '/../partials/paginacao.php'; ?>
