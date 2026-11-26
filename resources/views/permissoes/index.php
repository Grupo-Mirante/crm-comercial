<?php use App\Controllers\PermissoesController; ?>
<div class="page-head">
    <div><h1>Permissões por perfil</h1><p>Marque o que cada perfil pode fazer. O <b>Administrador</b> tem sempre acesso total. Toda alteração fica na auditoria.</p></div>
</div>
<div class="alert alert-info"><i class="bi bi-info-circle"></i><div>
    Regras do processo que valem independentemente da matriz: o executivo e o vendedor só veem as próprias PIs; o gerente vê as suas e as da equipe;
    quem não tem “Cancelar qualquer PI” só cancela o próprio Rascunho; a Conferência nunca altera os dados comerciais da PI.
</div></div>
<form method="post" action="<?= e(url('/permissoes')) ?>" data-confirm="Salvar a matriz de permissões? As mudanças valem na hora para todos os usuários dos perfis alterados." data-confirm-button="Salvar" data-confirm-danger="0">
    <?= csrf_field() ?>
    <div class="table-card mb-3"><div class="table-responsive">
        <table class="table perm-table align-middle">
            <thead><tr><th style="min-width:300px">Módulo / ação</th>
                <?php foreach ($perfis as $pf): ?><th title="<?= e($pf['descricao']) ?>"><?= e($pf['nome']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($porModulo as $mod => $lista): ?>
                <tr><td class="mod" colspan="<?= count($perfis) + 1 ?>"><?= e(PermissoesController::MODULOS[$mod] ?? $mod) ?></td></tr>
                <?php foreach ($lista as $pm): ?>
                    <tr>
                        <td><?= e($pm['descricao']) ?><div class="small-muted"><code><?= e($pm['modulo'] . '.' . $pm['acao']) ?></code></div></td>
                        <?php foreach ($perfis as $pf): $id = 'p' . $pf['id'] . '_' . $pm['id']; ?>
                            <td><input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="perm[<?= (int) $pf['id'] ?>][]" value="<?= (int) $pm['id'] ?>"<?= isset($marcadas[(int) $pf['id']][(int) $pm['id']]) ? ' checked' : '' ?> aria-label="<?= e($pf['nome'] . ': ' . $pm['descricao']) ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url('/usuarios')) ?>">Voltar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar permissões</button></div>
</form>
