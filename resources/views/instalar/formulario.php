<?php
/** @var string $codigo @var ?string $mensagem @var string $tipo */
$campo = function (string $nome, string $rotulo, string $tipo = 'text', string $ajuda = '', array $attr = []) {
    $extra = '';
    foreach ($attr as $k => $v) {
        $extra .= ' ' . $k . '="' . e((string) $v) . '"';
    }
    $valor = $tipo === 'password' && !in_array($nome, ['db_password'], true) ? '' : (string) old($nome);
    $input = '<input class="form-control' . tem_erro($nome) . '" id="' . $nome . '" name="' . $nome . '" type="' . $tipo . '" value="' . e($valor) . '"' . $extra . '>';
    if ($tipo === 'password') {
        $input = '<div class="pw-wrap">' . $input . '<button type="button" class="pw-toggle" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button></div>';
    }
    return '<label class="form-label" for="' . $nome . '">' . e($rotulo) . '</label>' . $input . erro($nome) . ($ajuda !== '' ? '<div class="form-text">' . $ajuda . '</div>' : '');
};
?>
<div class="inst-card">
    <span class="inst-etapa">Etapa 2 de 3</span>
    <h1>Dados da instalação</h1>
    <p class="lead-sm">Preencha os três blocos e clique em <b>Instalar</b>. Se algo estiver errado, nada é perdido: corrija e clique de novo.</p>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?= e($tipo) ?>" role="<?= $tipo === 'danger' ? 'alert' : 'status' ?>"><i class="bi bi-<?= $tipo === 'success' ? 'check-circle' : 'exclamation-octagon' ?>"></i><div><?= e($mensagem) ?></div></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/instalar')) ?>" novalidate id="fInstalar">
        <input type="hidden" name="codigo" value="<?= e($codigo) ?>">

        <h2 class="inst-h2"><span class="inst-num">1</span> Banco de dados MySQL</h2>
        <p class="small-muted">Use os dados do banco criado no painel da hospedagem (cPanel, Plesk etc.) ou pelo responsável técnico. O banco deve estar <b>vazio</b> e ser só do CRM.</p>
        <div class="row g-3">
            <div class="col-sm-8"><?= $campo('db_host', 'Servidor do MySQL', 'text', 'Quase sempre <code>127.0.0.1</code> ou <code>localhost</code>.', ['autocomplete' => 'off', 'spellcheck' => 'false']) ?></div>
            <div class="col-sm-4"><?= $campo('db_port', 'Porta', 'text', 'Padrão: 3306', ['inputmode' => 'numeric']) ?></div>
            <div class="col-sm-6"><?= $campo('db_database', 'Nome do banco', 'text', 'Recomendado: <code>crm_grupo_mirante</code>', ['spellcheck' => 'false']) ?></div>
            <div class="col-sm-6"><?= $campo('db_username', 'Usuário do MySQL', 'text', 'Um usuário só do CRM (não use o root).', ['autocomplete' => 'off', 'spellcheck' => 'false']) ?></div>
            <div class="col-sm-6"><?= $campo('db_password', 'Senha do usuário do MySQL', 'password', '', ['autocomplete' => 'new-password']) ?></div>
            <div class="col-sm-6 d-flex align-items-end pb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="db_criar" name="db_criar" value="1" <?= old('db_criar') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="db_criar">Criar o banco se não existir</label>
                </div>
            </div>
            <div class="col-12">
                <details class="inst-avancado"<?= old('db_socket') ? ' open' : '' ?>>
                    <summary>Avançado: conexão por socket</summary>
                    <div class="mt-2"><?= $campo('db_socket', 'Caminho do socket do MySQL (opcional)', 'text', 'Deixe vazio para usar servidor e porta. Ex.: <code>/var/run/mysqld/mysqld.sock</code>', ['spellcheck' => 'false']) ?></div>
                </details>
            </div>
            <div class="col-12">
                <button class="btn btn-outline-primary" type="submit" name="acao" value="testar"><i class="bi bi-plug"></i> Testar conexão</button>
            </div>
        </div>

        <h2 class="inst-h2"><span class="inst-num">2</span> Endereço do sistema</h2>
        <div class="row g-3">
            <div class="col-sm-8"><?= $campo('app_url', 'Endereço (URL) de acesso', 'url', 'Como a equipe vai digitar no navegador, sem barra no final. Ex.: <code>https://crm.grupomirante.com.br</code>', ['spellcheck' => 'false']) ?></div>
            <div class="col-sm-4">
                <label class="form-label" for="app_env">Tipo de instalação</label>
                <select class="form-select" id="app_env" name="app_env">
                    <option value="production" <?= old('app_env', 'production') === 'production' ? 'selected' : '' ?>>Produção (uso real)</option>
                    <option value="staging" <?= old('app_env') === 'staging' ? 'selected' : '' ?>>Homologação (testes da equipe)</option>
                </select>
                <div class="form-text">Os dois começam vazios: só cadastros reais.</div>
            </div>
        </div>

        <h2 class="inst-h2"><span class="inst-num">3</span> Primeiro administrador</h2>
        <p class="small-muted">Não existe usuário ou senha padrão. Esta pessoa cadastra os demais usuários. No primeiro acesso, o sistema pede para ela <b>trocar a senha</b>, assim quem instalou não conhece a senha definitiva.</p>
        <div class="row g-3">
            <div class="col-sm-6"><?= $campo('adm_nome', 'Nome completo', 'text', '', ['autocomplete' => 'name']) ?></div>
            <div class="col-sm-6"><?= $campo('adm_email', 'E-mail', 'email', '', ['autocomplete' => 'email']) ?></div>
            <div class="col-sm-6"><?= $campo('adm_login', 'Usuário (login)', 'text', 'Pessoal, em minúsculas. Ex.: <code>maria.silva</code>. Não use "admin".', ['autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']) ?></div>
            <div class="col-sm-6"></div>
            <div class="col-sm-6"><?= $campo('adm_senha', 'Senha provisória', 'password', 'Mínimo de 12 caracteres, com letras e números, sem o nome de usuário.', ['autocomplete' => 'new-password']) ?></div>
            <div class="col-sm-6"><?= $campo('adm_confirmacao', 'Repita a senha', 'password', '', ['autocomplete' => 'new-password']) ?></div>
        </div>

        <div class="inst-acoes">
            <button class="btn btn-danger btn-lg" type="submit" name="acao" value="instalar"><i class="bi bi-download"></i> Instalar</button>
            <span class="small-muted">Cria as tabelas, grava o <code>.env</code>, cria o administrador e trava o instalador.</span>
        </div>
    </form>
</div>
