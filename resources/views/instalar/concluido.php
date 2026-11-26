<?php
/** @var array{passos:list<string>,admin:string} $resultado */
$raiz = \App\Core\App::root();
?>
<div class="inst-card">
    <span class="inst-etapa">Etapa 3 de 3</span>
    <h1><i class="bi bi-check-circle-fill text-success"></i> CRM instalado</h1>
    <ul class="inst-req mt-3">
        <?php foreach ($resultado['passos'] as $p): ?>
            <li class="ok"><i class="bi bi-check-circle-fill"></i><span><?= e($p) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <h2 class="inst-h2">Falta só agendar as rotinas automáticas</h2>
    <p>Elas expiram as reservas de 24 h, enviam os avisos de prazo, montam a fila diária e fazem o backup. Peça ao responsável pelo servidor para incluir esta linha no agendador (cron), a cada 5 minutos:</p>
    <pre class="inst-pre" id="cmdCron">*/5 * * * * cd <?= e($raiz) ?> &amp;&amp; php bin/console.php rotinas &gt;&gt; storage/logs/cron.log 2&gt;&amp;1</pre>
    <button type="button" class="btn btn-sm btn-outline-primary mb-2" data-copy="#cmdCron"><i class="bi bi-clipboard"></i> Copiar comando</button>
    <p class="small-muted">Na hospedagem com cPanel: <b>Cron Jobs</b> → "A cada cinco minutos" → cole o comando. Detalhes no Manual de instalação, etapa 7.</p>

    <h2 class="inst-h2">Próximos passos</h2>
    <ol class="inst-passos">
        <li>Entre com o usuário <b><?= e($resultado['admin']) ?></b> e a senha provisória. O sistema vai pedir uma senha nova.</li>
        <li>Em <b>Gestão → Usuários</b>, cadastre os gerentes e depois os executivos (cada executivo com o seu gestor).</li>
        <li>Em <b>Gestão → Configurações</b>, confira os prazos e preencha as alçadas de desconto quando a diretoria definir.</li>
        <li>Cadastre clientes e agências, ou deixe os executivos cadastrarem ao montar a primeira PI.</li>
    </ol>
    <div class="alert alert-info"><i class="bi bi-shield-check"></i><div>Por segurança, este assistente <b>não abre mais</b>: o endereço <code>/instalar</code> passa a responder "página não encontrada". O código de instalação foi apagado.</div></div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('/login')) ?>"><i class="bi bi-box-arrow-in-right"></i> Ir para o login</a>
</div>
