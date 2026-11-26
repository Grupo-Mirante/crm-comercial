<div class="page-head"><div><h1>Configurações</h1><p>Parâmetros do processo comercial. Valores vazios seguem a regra padrão indicada em cada campo. Toda alteração fica na auditoria.</p></div></div>
<?php if (!$smtp): ?>
<div class="alert alert-info"><i class="bi bi-envelope-slash"></i><div><b>E-mail não configurado.</b> Os avisos aparecem só dentro do CRM e a recuperação de senha é feita pelo administrador. Para enviar e-mails, preencha MAIL_HOST e MAIL_FROM_ADDRESS no arquivo .env do servidor.</div></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/configuracoes')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php $n = 0; foreach ($grupos as $grupo => $itens): $n++; ?>
    <section class="form-section">
        <div class="form-section-head"><span class="step"><?= $n ?></span><h2><?= e($grupo) ?></h2>
            <?php if ($grupo === 'Alçadas'): ?><p>Pendência: a diretoria define na Fase 1 do projeto</p><?php endif; ?></div>
        <div class="form-section-body"><div class="row g-3">
        <?php foreach ($itens as $c): $nome = 'cfg[' . $c['chave'] . ']'; $id = 'cfg_' . $c['chave']; $val = old($id, $c['valor'] ?? ''); ?>
            <?php if ($c['tipo'] === 'booleano'): ?>
                <div class="col-md-6"><input type="hidden" name="<?= e($nome) ?>" value="0"><div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($nome) ?>" value="1"<?= (string) $val === '1' ? ' checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($id) ?>"><?= e($c['descricao']) ?></label></div></div>
            <?php elseif ($c['tipo'] === 'texto_longo'): ?>
                <div class="col-12"><label class="form-label" for="<?= e($id) ?>"><?= e($c['descricao']) ?></label>
                    <textarea class="form-control<?= tem_erro($id) ?>" id="<?= e($id) ?>" name="<?= e($nome) ?>" rows="2"><?= e($val) ?></textarea><?= erro($id) ?></div>
            <?php else: ?>
                <div class="col-md-6"><label class="form-label" for="<?= e($id) ?>"><?= e($c['descricao']) ?></label>
                    <input class="form-control<?= tem_erro($id) ?>" id="<?= e($id) ?>" name="<?= e($nome) ?>" value="<?= e($val) ?>"
                        <?= $c['tipo'] === 'hora' ? 'type="time"' : ($c['tipo'] === 'inteiro' ? 'type="number" min="0"' : ($c['tipo'] === 'email' ? 'type="email"' : '')) ?>
                        <?= $c['tipo'] === 'decimal' ? 'inputmode="decimal" placeholder="vazio = sem limite"' : '' ?>><?= erro($id) ?>
                    <div class="form-text"><code><?= e($c['chave']) ?></code></div></div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div></div>
    </section>
    <?php endforeach; ?>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar configurações</button></div>
</form>
