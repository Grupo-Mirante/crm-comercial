<?php
/** @var list<array{item:string,ok:bool,detalhe:string,obrigatorio:bool}> $requisitos @var string $arquivoCodigo */
$ok = \App\Services\Instalacao\Instalador::requisitosOk($requisitos);
?>
<div class="inst-card">
    <span class="inst-etapa">Etapa 1 de 3</span>
    <h1>Bem-vindo à instalação</h1>
    <p class="lead-sm">Este assistente prepara o banco de dados, grava a configuração e cria o primeiro administrador. Leva cerca de 2 minutos.</p>

    <h2 class="inst-h2">Conferência do servidor</h2>
    <?= \App\Core\View::partial('instalar/_requisitos', ['requisitos' => $requisitos]) ?>

    <?php if (!$ok): ?>
        <div class="alert alert-danger mt-3"><i class="bi bi-x-octagon"></i>
            <div>O servidor ainda não atende a todos os itens obrigatórios (em vermelho). Peça ao responsável pela hospedagem para corrigi-los e recarregue esta página. O <b>Manual de instalação</b>, etapa 2, explica cada item.</div>
        </div>
    <?php endif; ?>

    <h2 class="inst-h2">Código de instalação</h2>
    <p>Para provar que você tem acesso ao servidor, abra o arquivo <code><?= e($arquivoCodigo) ?></code> dentro da pasta do CRM (pelo gerenciador de arquivos da hospedagem, FTP ou terminal) e copie o código abaixo. Isso impede que outra pessoa instale o sistema antes de você.</p>
    <form method="post" action="<?= e(url('/instalar')) ?>" novalidate>
        <input type="hidden" name="acao" value="entrar">
        <div class="row g-2 align-items-start">
            <div class="col-sm-8">
                <label class="form-label" for="codigo">Código (ex.: ABCDE-FGHJK-LMNPQ)</label>
                <input class="form-control form-control-lg font-monospace<?= tem_erro('codigo') ?>" id="codigo" name="codigo" autocomplete="off" autocapitalize="characters" spellcheck="false" required data-autofocus>
                <?= erro('codigo') ?>
            </div>
            <div class="col-sm-4 d-grid inst-btn-alinhado">
                <button class="btn btn-primary btn-lg" type="submit" <?= $ok ? '' : 'disabled' ?>>Continuar <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>
    </form>
</div>
