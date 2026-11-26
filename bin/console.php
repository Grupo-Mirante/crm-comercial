<?php

declare(strict_types=1);

/*
 * Console do CRM (rodar no terminal do servidor, dentro da pasta do projeto):
 *
 *   php bin/console.php migrar              aplica estrutura e dados iniciais pendentes
 *   php bin/console.php schema              gera database/schema.sql a partir das migrations
 *   php bin/console.php seed-teste          dados fictícios (só APP_ENV≠production e banco terminado em _teste)
 *   php bin/console.php expirar-reservas    expira reservas de 24 h vencidas
 *   php bin/console.php rotinas             TODAS as rotinas (reservas, avisos de prazo, fila diária, backup diário) — agendar no cron a cada 5 min
 *   php bin/console.php backup [--anexos]   gera backup do banco (e dos anexos) em storage/backups
 *   php bin/console.php restaurar ARQUIVO   restaura um backup do banco (pede confirmação; faz backup de segurança antes)
 *   php bin/console.php instalar            instalação pelo terminal (o mesmo que: php instalar.php)
 *   php bin/console.php criar-admin         cria outro administrador (senha forte; troca obrigatória no 1º acesso)
 *   php bin/console.php ajuda               mostra esta lista
 */

use App\Core\App;
use App\Core\Database;
use App\Services\Auditoria;
use App\Services\Instalacao\Instalador;
use App\Services\Instalacao\Migrador;
use App\Services\Instalacao\SeedTeste;
use App\Services\ReservaService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
App::boot();

$cmd = $argv[1] ?? 'ajuda';
$out = fn (string $s) => fwrite(STDOUT, $s . PHP_EOL);
$err = fn (string $s) => fwrite(STDERR, $s . PHP_EOL);

/** Registra a execução de uma rotina agendada. */
$rotina = function (string $nome, callable $fn) use ($out): int {
    $id = Database::insert('rotinas_execucoes', ['rotina' => $nome, 'iniciada_em' => date('Y-m-d H:i:s')]);
    try {
        $res = (string) $fn();
        Database::query('UPDATE rotinas_execucoes SET concluida_em = NOW(), sucesso = 1, resultado = ? WHERE id = ?', [mb_substr($res, 0, 500), $id]);
        $out($res);
        return 0;
    } catch (Throwable $e) {
        Database::query('UPDATE rotinas_execucoes SET concluida_em = NOW(), sucesso = 0, resultado = ? WHERE id = ?', [mb_substr($e->getMessage(), 0, 500), $id]);
        throw $e;
    }
};

/** Lê uma resposta do terminal (oculta para senhas). */
$ler = function (string $p, bool $oculto = false, string $padrao = ''): string {
    fwrite(STDOUT, $p);
    $tty = $oculto && DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') ? @posix_isatty(STDIN) : $oculto && DIRECTORY_SEPARATOR === '/';
    if ($tty) {
        shell_exec('stty -echo 2>/dev/null');
    }
    $v = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);
    }
    $v = $v === false ? '' : rtrim($v, "\r\n");
    $v = $oculto ? $v : trim($v);
    return $v === '' ? $padrao : $v;
};

try {
    switch ($cmd) {
        case 'migrar':
            $out('Aplicando estrutura e dados iniciais…');
            $feitos = (new Migrador(Database::connection()))->migrar(true, $out);
            $out($feitos ? 'Concluído: ' . count($feitos) . ' arquivo(s) aplicado(s).' : 'Nada a aplicar: o banco já está atualizado.');
            break;

        case 'schema':
            $out('Gerado: ' . Migrador::gerarSchema());
            break;

        case 'seed-teste':
            if (!class_exists(SeedTeste::class)) {
                $err('Os dados de teste não fazem parte do pacote de produção.');
                exit(1);
            }
            if (!SeedTeste::permitido()) {
                $err('Bloqueado: dados de teste só entram com APP_ENV diferente de production e banco terminado em "_teste".');
                exit(1);
            }
            SeedTeste::executar();
            $out('Dados de teste carregados. Usuários teste.* com a senha ' . SeedTeste::SENHA . ' (somente neste banco de teste).');
            break;

        case 'expirar-reservas':
            exit($rotina('expirar-reservas', fn () => 'Reservas expiradas: ' . ReservaService::expirarVencidas()));

        case 'rotinas':
            exit($rotina('rotinas', function () {
                $r = \App\Services\RotinasService::executar(!in_array('--sem-backup', $GLOBALS['argv'], true));
                return implode(' · ', array_map(fn ($k, $v) => $k . ': ' . $v, array_keys($r), $r));
            }));

        case 'backup':
            $arqs = [\App\Services\BackupService::gerar('console')];
            if (in_array('--anexos', $argv, true)) {
                $arqs[] = \App\Services\BackupService::gerarAnexos();
            }
            foreach ($arqs as $a) {
                $out('Backup gerado: ' . $a);
            }
            break;

        case 'restaurar':
            $nome = $argv[2] ?? '';
            if ($nome === '') {
                $out('Informe o arquivo: php bin/console.php restaurar crm_AAAAMMDD_HHMMSS.sql.gz');
                $out('Backups disponíveis:');
                foreach (\App\Services\BackupService::listar() as $b) {
                    $out('  ' . $b['nome'] . '  (' . $b['tipo'] . ', ' . $b['data'] . ')');
                }
                exit(1);
            }
            $caminho = \App\Services\BackupService::caminho(basename($nome));
            $out('ATENÇÃO: o banco ' . \App\Core\Config::get('database.database') . ' será SUBSTITUÍDO pelo conteúdo de ' . basename($caminho) . '.');
            $out('Antes, será feito um backup do estado atual por segurança.');
            fwrite(STDOUT, 'Digite RESTAURAR para confirmar: ');
            if (trim((string) fgets(STDIN)) !== 'RESTAURAR') {
                $err('Cancelado.');
                exit(1);
            }
            $out('Backup de segurança: ' . \App\Services\BackupService::gerar('antes da restauração'));
            \App\Services\BackupService::restaurar($caminho);
            $out('Restauração concluída.');
            break;

        case 'instalar':
            if (Instalador::instalado()) {
                $err('O CRM já está instalado (storage/instalado.lock). Para atualizar a estrutura use: php bin/console.php migrar');
                exit(1);
            }
            $out('');
            $out('=== Instalação do CRM Comercial · Grupo Mirante ===');
            $out('');
            $out('1) Conferência do servidor');
            $req = Instalador::requisitos(true);
            foreach ($req as $x) {
                $out(sprintf('   %s %s — %s', $x['ok'] ? '[ok]' : ($x['obrigatorio'] ? '[FALTA]' : '[aviso]'), $x['item'], $x['detalhe']));
            }
            if (!Instalador::requisitosOk($req)) {
                $err('');
                $err('Corrija os itens [FALTA] e rode de novo. O Manual de instalação (etapa 2) explica cada um.');
                exit(1);
            }
            $env = fn (string $k, string $p = '') => (string) (\App\Core\Env::get($k) ?? $p);
            $out('');
            $out('2) Banco de dados MySQL (Enter aceita o valor entre colchetes)');
            $db = [
                'host' => $ler('   Servidor [' . $env('DB_HOST', '127.0.0.1') . ']: ', false, $env('DB_HOST', '127.0.0.1')),
                'port' => $ler('   Porta [' . $env('DB_PORT', '3306') . ']: ', false, $env('DB_PORT', '3306')),
                'socket' => $env('DB_SOCKET'),
                'database' => $ler('   Nome do banco [' . $env('DB_DATABASE', 'crm_grupo_mirante') . ']: ', false, $env('DB_DATABASE', 'crm_grupo_mirante')),
                'username' => $ler('   Usuário do MySQL' . ($env('DB_USERNAME') !== '' ? ' [' . $env('DB_USERNAME') . ']' : '') . ': ', false, $env('DB_USERNAME')),
            ];
            $db['password'] = $ler('   Senha do usuário do MySQL' . ($env('DB_PASSWORD') !== '' ? ' [Enter mantém a do .env]' : '') . ': ', true, $env('DB_PASSWORD'));
            $db['criar'] = in_array(mb_strtolower($ler('   Criar o banco se não existir? (s/N): ')), ['s', 'sim'], true);
            $out('');
            $out('3) Endereço do sistema');
            $urlPadrao = str_contains($env('APP_URL'), 'seudominio') ? '' : $env('APP_URL');
            $app = ['url' => $ler('   URL de acesso, ex. https://crm.grupomirante.com.br' . ($urlPadrao !== '' ? ' [' . $urlPadrao . ']' : '') . ': ', false, $urlPadrao),
                'env' => in_array(mb_strtolower($ler('   É homologação (ambiente de testes da equipe)? (s/N): ')), ['s', 'sim'], true) ? 'staging' : 'production'];
            $out('');
            $out('4) Primeiro administrador (vai trocar a senha no primeiro acesso)');
            $adm = ['nome' => $ler('   Nome completo: '), 'login' => $ler('   Usuário (ex.: maria.silva): '), 'email' => $ler('   E-mail: '),
                'senha' => $ler('   Senha provisória (mín. 12, letras e números): ', true), 'confirmacao' => $ler('   Repita a senha: ', true)];
            $out('');
            $out('5) Instalando…');
            try {
                $res = Instalador::instalar($db, $app, $adm, 'instalador (terminal)', $out);
            } catch (\App\Core\ValidationException $e) {
                $err('');
                $err($e->getMessage());
                foreach ($e->errors as $m) {
                    $err('   - ' . $m);
                }
                $err('Nada foi alterado no banco por causa destes campos. Rode de novo: php instalar.php');
                exit(1);
            }
            $out('');
            $out('Instalação concluída. Falta agendar as rotinas (cron, a cada 5 minutos):');
            $out('   */5 * * * * cd ' . App::root() . ' && php bin/console.php rotinas >> storage/logs/cron.log 2>&1');
            $out('');
            $out('Se este terminal não é o usuário do servidor web, ajuste o dono das pastas (exemplo para Apache/Nginx no Ubuntu):');
            $out('   sudo chown -R www-data:www-data ' . App::root() . '/storage ' . App::root() . '/.env');
            $out('');
            $out('Entre no navegador com o usuário "' . $res['admin'] . '" e a senha provisória.');
            break;

        case 'criar-admin':
            $out('Novo administrador (vai trocar a senha no primeiro acesso).');
            try {
                $a = Instalador::validarAdmin(['nome' => $ler('Nome completo: '), 'login' => $ler('Usuário (login): '), 'email' => $ler('E-mail: '),
                    'senha' => $ler('Senha provisória (mínimo 12 caracteres, letras e números): ', true), 'confirmacao' => $ler('Repita a senha: ', true)]);
            } catch (\App\Core\ValidationException $e) {
                foreach ($e->errors as $m) {
                    $err($m);
                }
                exit(1);
            }
            Instalador::criarAdmin($a, 'console');
            $out("Administrador {$a['login']} criado. No primeiro acesso, o sistema pede uma senha nova.");
            break;

        default:
            $out(trim((string) preg_replace('/^.*?\/\*|\*\/.*$/s', '', (string) file_get_contents(__FILE__, false, null, 0, 1200))));
    }
} catch (Throwable $e) {
    $err('Erro: ' . $e->getMessage());
    exit(1);
}
