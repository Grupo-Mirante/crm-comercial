<?php

declare(strict_types=1);

namespace App\Services\Instalacao;

use App\Core\App;
use App\Core\BusinessException;
use App\Core\Config;
use App\Core\Database;
use App\Core\ValidationException;
use App\Services\Auditoria;
use App\Services\AuthService;
use PDO;
use Throwable;

/**
 * Instalação do CRM (usada pelo assistente web /instalar e pelo comando "php instalar.php").
 *
 *  1. confere o servidor (PHP, extensões, pastas graváveis, HTTPS, mysqldump);
 *  2. conecta ao MySQL 8 (cria o banco se pedido) e recusa banco com outra instalação ou com tabelas alheias;
 *  3. grava o .env;
 *  4. aplica a estrutura e os dados iniciais de PRODUÇÃO (nunca dados de teste);
 *  5. cria o primeiro administrador com senha forte e troca obrigatória no primeiro acesso;
 *  6. grava a trava storage/instalado.lock — a partir daí o instalador não abre mais.
 */
final class Instalador
{
    public const PHP_MINIMO = '8.5.0';
    public const MYSQL_MINIMO = '8.0.0';
    public const VERSAO = '1.0.0';

    private const PASTAS = ['storage', 'storage/logs', 'storage/sessions', 'storage/cache', 'storage/exports', 'storage/uploads', 'storage/backups'];

    /** Pasta onde ficam .env e storage/ (substituível nos testes automáticos). */
    public static ?string $raiz = null;

    public static function raiz(): string
    {
        return self::$raiz ?? App::root();
    }

    public static function trava(): string
    {
        return self::raiz() . '/storage/instalado.lock';
    }

    public static function instalado(): bool
    {
        return is_file(self::trava());
    }

    // ------------------------------------------------------------------ código de instalação (assistente web)

    public static function arquivoCodigo(): string
    {
        return self::raiz() . '/storage/codigo-instalacao.txt';
    }

    /**
     * O assistente web só funciona para quem consegue ler este arquivo no servidor (gerenciador de arquivos
     * da hospedagem, FTP ou terminal). Assim, ninguém de fora "instala primeiro" e vira administrador.
     */
    public static function garantirCodigo(): void
    {
        $arq = self::arquivoCodigo();
        if (is_file($arq) && trim((string) file_get_contents($arq)) !== '') {
            return;
        }
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 15; $i++) {
            $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)] . ($i % 5 === 4 && $i < 14 ? '-' : '');
        }
        if (!is_dir(dirname($arq))) {
            @mkdir(dirname($arq), 0750, true);
        }
        if (@file_put_contents($arq, $c . PHP_EOL) !== false) {
            @chmod($arq, 0600);
        }
    }

    public static function codigoValido(string $informado): bool
    {
        $arq = self::arquivoCodigo();
        if (!is_file($arq)) {
            return false;
        }
        $norm = fn (string $s) => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $s));
        $certo = $norm((string) file_get_contents($arq));
        return $certo !== '' && hash_equals($certo, $norm($informado));
    }

    // ------------------------------------------------------------------ 1. servidor

    /** @return list<array{item:string,ok:bool,detalhe:string,obrigatorio:bool}> */
    public static function requisitos(bool $https = true, bool $local = false): array
    {
        $r = [];
        $add = function (string $item, bool $ok, string $detalhe, bool $obrigatorio = true) use (&$r) {
            $r[] = compact('item', 'ok', 'detalhe', 'obrigatorio');
        };
        $add('PHP ' . self::PHP_MINIMO . ' ou mais novo', version_compare(PHP_VERSION, self::PHP_MINIMO, '>='), 'Versão instalada: ' . PHP_VERSION);
        foreach (['pdo_mysql' => 'acesso ao MySQL', 'mbstring' => 'textos com acento', 'json' => 'dados JSON', 'zip' => 'gerar Excel', 'gd' => 'imagens do Excel',
            'dom' => 'gerar Excel', 'xml' => 'gerar Excel', 'xmlreader' => 'gerar Excel', 'xmlwriter' => 'gerar Excel', 'simplexml' => 'gerar Excel',
            'iconv' => 'gerar Excel', 'ctype' => 'validações', 'fileinfo' => 'conferir o tipo dos anexos', 'openssl' => 'e-mail seguro e HTTPS', 'zlib' => 'backup compactado'] as $ext => $pra) {
            $add('Extensão ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'ativa (' . $pra . ')' : 'FALTANDO — necessária para ' . $pra, $ext !== 'openssl');
        }
        $add('Senhas com Argon2id', defined('PASSWORD_ARGON2ID'), defined('PASSWORD_ARGON2ID') ? 'disponível' : 'o PHP não tem Argon2id (instale o PHP com libsodium/argon2)');
        $add('Bibliotecas (pasta vendor)', is_file(App::root() . '/vendor/autoload.php'), 'PhpSpreadsheet e PHPMailer');
        foreach (self::PASTAS as $p) {
            $d = self::raiz() . '/' . $p;
            if (!is_dir($d)) {
                @mkdir($d, 0750, true);
            }
            $add('Pasta ' . $p . ' gravável', is_dir($d) && is_writable($d), is_writable($d) ? 'ok' : 'sem permissão de escrita para o usuário do servidor web');
        }
        $env = self::raiz() . '/.env';
        $envOk = is_file($env) ? is_writable($env) || is_readable($env) : is_writable(self::raiz());
        $add('Arquivo de configuração (.env)', $envOk, is_file($env) ? (is_writable($env) ? 'existe e pode ser atualizado' : 'existe (somente leitura)') : (is_writable(self::raiz()) ? 'será criado pelo instalador' : 'a pasta do CRM não é gravável: crie o .env a partir do .env.example'));
        $add('Acesso por HTTPS', $https, $https ? 'conexão segura' : ($local ? 'endereço local (teste). No servidor da empresa use HTTPS' : 'use HTTPS em produção (certificado SSL). Sem HTTPS, senhas trafegam abertas'), false);
        $dump = (string) Config::get('app.backup.mysqldump', 'mysqldump');
        $temDump = self::programaExiste($dump);
        $add('mysqldump (backup)', $temDump, $temDump ? 'encontrado' : 'não encontrado: o CRM funciona, mas o backup automático não. Informe MYSQLDUMP_PATH no .env', false);
        return $r;
    }

    /** @param list<array{ok:bool,obrigatorio:bool}> $req */
    public static function requisitosOk(array $req): bool
    {
        foreach ($req as $x) {
            if ($x['obrigatorio'] && !$x['ok']) {
                return false;
            }
        }
        return true;
    }

    private static function programaExiste(string $prog): bool
    {
        if (str_contains($prog, '/')) {
            return is_file($prog) && is_executable($prog);
        }
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_file($dir . '/' . $prog) && is_executable($dir . '/' . $prog)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ 2. banco

    /**
     * @param array{host:string,port:int|string,socket?:string,database:string,username:string,password:string} $db
     * @return array{pdo:PDO,versao:string,criado:bool}
     */
    public static function conectar(array $db, bool $criarBanco): array
    {
        $erros = [];
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $db['database'])) {
            $erros['db_database'] = 'Nome do banco: use só letras, números e _ (ex.: crm_grupo_mirante).';
        }
        if (trim((string) $db['username']) === '') {
            $erros['db_username'] = 'Informe o usuário do MySQL.';
        }
        if (empty($db['socket']) && trim((string) $db['host']) === '') {
            $erros['db_host'] = 'Informe o servidor do MySQL (geralmente 127.0.0.1 ou localhost).';
        }
        if ($erros) {
            throw new ValidationException($erros, 'Confira os dados do banco.');
        }
        $cfg = ['host' => trim((string) $db['host']), 'port' => (int) ($db['port'] ?: 3306), 'socket' => trim((string) ($db['socket'] ?? '')),
            'database' => (string) $db['database'], 'username' => trim((string) $db['username']), 'password' => (string) $db['password']];
        try {
            $srv = Database::connect($cfg, false);
        } catch (Throwable $e) {
            throw new BusinessException(self::explicarErroMysql($e));
        }
        $versao = (string) $srv->query('SELECT VERSION()')->fetchColumn();
        if (version_compare((string) preg_replace('/[^0-9.].*$/', '', $versao), self::MYSQL_MINIMO, '<')) {
            throw new BusinessException("O servidor é MySQL {$versao}. O CRM precisa de MySQL 8.0 ou mais novo.");
        }
        $existe = (bool) $srv->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $srv->quote($cfg['database']))->fetchColumn();
        $criado = false;
        if (!$existe) {
            if (!$criarBanco) {
                throw new BusinessException("O banco \"{$cfg['database']}\" não existe. Crie-o no painel da hospedagem ou marque \"Criar o banco se não existir\".");
            }
            try {
                $srv->exec('CREATE DATABASE `' . $cfg['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
                $criado = true;
            } catch (Throwable $e) {
                throw new BusinessException("O usuário \"{$cfg['username']}\" não tem permissão para criar bancos. Crie o banco \"{$cfg['database']}\" no painel da hospedagem e tente de novo.");
            }
        }
        $srv = null;
        try {
            $pdo = Database::connect($cfg);
        } catch (Throwable $e) {
            throw new BusinessException(self::explicarErroMysql($e));
        }
        return ['pdo' => $pdo, 'versao' => $versao, 'criado' => $criado, 'cfg' => $cfg];
    }

    /** Recusa banco que já tem o CRM instalado ou que tem tabelas de outro sistema (nada é apagado). */
    public static function conferirBancoVazio(PDO $pdo): void
    {
        $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (!$tabelas) {
            return;
        }
        if (!in_array('migrations', $tabelas, true)) {
            throw new BusinessException('O banco escolhido já tem ' . count($tabelas) . ' tabela(s) de outro sistema. Use um banco vazio, só para o CRM.');
        }
        if (in_array('usuarios', $tabelas, true) && (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
            throw new BusinessException('Este banco já tem o CRM instalado, com usuários cadastrados. Para atualizar, use "php bin/console.php migrar". O instalador não sobrescreve dados.');
        }
        // migrations sem usuários: instalação anterior interrompida — o Migrador continua de onde parou
    }

    private static function explicarErroMysql(Throwable $e): string
    {
        $m = $e->getMessage();
        return match (true) {
            str_contains($m, '1045') => 'Usuário ou senha do MySQL incorretos.',
            str_contains($m, '1044') => 'O usuário do MySQL não tem acesso a esse banco. Dê a ele todas as permissões no banco do CRM.',
            str_contains($m, '2002'), str_contains($m, 'Connection refused'), str_contains($m, 'No such file') => 'Não foi possível falar com o MySQL nesse endereço/porta. Confira o servidor e a porta, e se o MySQL está ligado.',
            str_contains($m, '2005'), str_contains($m, 'getaddrinfo') => 'O endereço do servidor MySQL não foi encontrado.',
            default => 'Erro ao conectar ao MySQL: ' . $m,
        };
    }

    // ------------------------------------------------------------------ 3. .env

    /**
     * Monta o conteúdo do .env a partir do .env.example, trocando só as chaves informadas.
     * @param array<string,string> $valores
     */
    public static function montarEnv(array $valores): string
    {
        $atual = is_file(self::raiz() . '/.env') ? (string) file_get_contents(self::raiz() . '/.env') : '';
        $base = $atual !== '' ? $atual : (string) file_get_contents(App::root() . '/.env.example');
        $linhas = preg_split('/\R/', $base) ?: [];
        $usadas = [];
        foreach ($linhas as $i => $l) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $l, $m) && array_key_exists($m[1], $valores)) {
                $linhas[$i] = $m[1] . '=' . self::valorEnv($valores[$m[1]]);
                $usadas[$m[1]] = true;
            }
        }
        foreach ($valores as $k => $v) {
            if (!isset($usadas[$k])) {
                $linhas[] = $k . '=' . self::valorEnv($v);
            }
        }
        return rtrim(implode("\n", $linhas)) . "\n";
    }

    private static function valorEnv(string $v): string
    {
        if ($v === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $v)) {
            return $v;
        }
        if (!str_contains($v, "'")) {
            return "'" . $v . "'";
        }
        if (!str_contains($v, '\\') && !str_contains($v, "\n")) {
            return '"' . str_replace('"', '\\"', $v) . '"';
        }
        throw new ValidationException(['db_password' => 'A senha do MySQL não pode ter, ao mesmo tempo, aspas simples e barra invertida. Troque a senha do usuário do banco.']);
    }

    /** Grava o .env (permissão 640). Retorna false se a pasta não for gravável. */
    public static function gravarEnv(string $conteudo): bool
    {
        $arq = self::raiz() . '/.env';
        if (is_file($arq) && (string) file_get_contents($arq) === $conteudo) {
            return true;
        }
        if ((is_file($arq) && !is_writable($arq)) || (!is_file($arq) && !is_writable(self::raiz()))) {
            return false;
        }
        if (file_put_contents($arq, $conteudo, LOCK_EX) === false) {
            return false;
        }
        @chmod($arq, 0640);
        return true;
    }

    // ------------------------------------------------------------------ 5. administrador

    /** @param array{nome:string,login:string,email:string,senha:string,confirmacao:string} $a */
    public static function validarAdmin(array $a): array
    {
        $a = ['nome' => trim((string) $a['nome']), 'login' => mb_strtolower(trim((string) $a['login'])), 'email' => mb_strtolower(trim((string) $a['email'])),
            'senha' => (string) $a['senha'], 'confirmacao' => (string) $a['confirmacao']];
        $erros = [];
        if (mb_strlen($a['nome']) < 3) {
            $erros['adm_nome'] = 'Informe o nome completo.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $a['login'])) {
            $erros['adm_login'] = 'Usuário: de 3 a 60 caracteres, letras minúsculas, números, ponto, hífen ou _.';
        } elseif (in_array($a['login'], ['admin', 'administrador', 'root', 'teste', 'test'], true)) {
            $erros['adm_login'] = 'Escolha um usuário pessoal (ex.: nome.sobrenome). Nomes genéricos como "admin" são os primeiros que um invasor tenta.';
        }
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['adm_email'] = 'Informe um e-mail válido.';
        }
        if ($m = AuthService::validarSenha($a['senha'], $a['login'], 12)) {
            $erros['adm_senha'] = $m;
        }
        if ($a['senha'] !== $a['confirmacao']) {
            $erros['adm_confirmacao'] = 'As duas senhas não são iguais.';
        }
        if ($erros) {
            throw new ValidationException($erros, 'Confira os dados do administrador.');
        }
        return $a;
    }

    /** Cria um administrador com troca de senha obrigatória no primeiro acesso. */
    public static function criarAdmin(array $a, string $origem): int
    {
        if (Database::value('SELECT id FROM usuarios WHERE login = ? OR email = ?', [$a['login'], $a['email']])) {
            throw new BusinessException('Já existe usuário com esse login ou e-mail.');
        }
        $id = Database::insert('usuarios', ['nome' => $a['nome'], 'login' => $a['login'], 'email' => $a['email'],
            'perfil_id' => (int) Database::value("SELECT id FROM perfis WHERE codigo = 'admin'"),
            'senha_hash' => AuthService::hash($a['senha']), 'trocar_senha' => 1, 'ativo' => 1, 'senha_alterada_em' => date('Y-m-d H:i:s')]);
        Auditoria::registrar('usuario_criado', 'usuario', $id, 'Administrador criado pelo ' . $origem . ' (troca de senha obrigatória no 1º acesso)', null, ['login' => $a['login'], 'perfil' => 'admin'], null, $origem);
        return $id;
    }

    // ------------------------------------------------------------------ execução completa

    /**
     * @param array<string,mixed> $db    host, port, socket, database, username, password, criar
     * @param array<string,mixed> $app   url, env (production|staging)
     * @param array<string,mixed> $admin nome, login, email, senha, confirmacao
     * @return array{passos:list<string>,env:string,env_gravado:bool,admin:string}
     */
    public static function instalar(array $db, array $app, array $admin, string $origem, ?callable $log = null): array
    {
        if (self::instalado()) {
            throw new BusinessException('O CRM já está instalado neste servidor (arquivo storage/instalado.lock).');
        }
        $passos = [];
        $passo = function (string $s) use (&$passos, $log) {
            $passos[] = $s;
            if ($log) {
                $log('  ✓ ' . $s);
            }
        };
        $adm = self::validarAdmin($admin);
        $url = rtrim(trim((string) ($app['url'] ?? '')), '/');
        if (!preg_match('#^https?://[^\s/]+(/[^\s]*)?$#i', $url)) {
            throw new ValidationException(['app_url' => 'Informe o endereço completo, ex.: https://crm.grupomirante.com.br'], 'Confira o endereço do sistema.');
        }
        $ambiente = in_array($app['env'] ?? null, ['production', 'staging'], true) ? (string) $app['env'] : 'production';

        $req = self::requisitos(true);
        if (!self::requisitosOk($req)) {
            $faltam = array_map(fn ($x) => $x['item'] . ': ' . $x['detalhe'], array_filter($req, fn ($x) => $x['obrigatorio'] && !$x['ok']));
            throw new BusinessException('O servidor ainda não atende aos requisitos: ' . implode('; ', $faltam));
        }
        $passo('Servidor conferido (PHP ' . PHP_VERSION . ')');

        $c = self::conectar($db, !empty($db['criar']));
        $passo('Conectado ao MySQL ' . $c['versao'] . ($c['criado'] ? ' — banco ' . $c['cfg']['database'] . ' criado' : ''));
        self::conferirBancoVazio($c['pdo']);
        $passo('Banco ' . $c['cfg']['database'] . ' pronto para receber o CRM');

        $env = self::montarEnv([
            'APP_ENV' => $ambiente, 'APP_DEBUG' => 'false', 'APP_URL' => $url, 'APP_TIMEZONE' => 'America/Sao_Paulo',
            'DB_HOST' => $c['cfg']['host'], 'DB_PORT' => (string) $c['cfg']['port'], 'DB_SOCKET' => $c['cfg']['socket'],
            'DB_DATABASE' => $c['cfg']['database'], 'DB_USERNAME' => $c['cfg']['username'], 'DB_PASSWORD' => $c['cfg']['password'],
        ]);
        $gravado = self::gravarEnv($env);
        if (!$gravado) {
            throw new BusinessException('Não foi possível gravar o arquivo .env na pasta do CRM (sem permissão). Dê permissão de escrita ou crie o .env manualmente a partir do .env.example e rode o instalador de novo.');
        }
        $passo('Arquivo de configuração .env gravado (permissão 640)');

        Config::set('database', $c['cfg']);
        Config::set('app.url', $url);
        Config::set('app.env', $ambiente);
        Database::setConnection($c['pdo']);
        $n = (new Migrador($c['pdo']))->migrar(true, fn (string $s) => null);
        $tabelas = (int) $c['pdo']->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        $precos = (int) $c['pdo']->query('SELECT COUNT(*) FROM precos_midia')->fetchColumn();
        $passo('Estrutura e dados iniciais aplicados' . ($n ? ' (' . count($n) . ' arquivos)' : ' (retomada: já estavam aplicados)') . ': ' . $tabelas . ' tabelas, ' . $precos . ' preços da tabela 2026, perfis, permissões, checklist e cláusulas');

        $id = self::criarAdmin($adm, $origem);
        $passo('Administrador "' . $adm['login'] . '" criado (vai trocar a senha no primeiro acesso)');

        foreach (self::PASTAS as $p) {
            $d = self::raiz() . '/' . $p;
            if (!is_dir($d)) {
                mkdir($d, 0750, true);
            }
        }
        $trava = json_encode(['instalado_em' => date('c'), 'versao' => self::VERSAO, 'banco' => $c['cfg']['database'], 'admin_id' => $id, 'origem' => $origem], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents(self::trava(), $trava . "\n", LOCK_EX) === false) {
            throw new BusinessException('Tudo foi instalado, mas não foi possível gravar storage/instalado.lock. Crie esse arquivo (pode ser vazio) para liberar o login.');
        }
        @chmod(self::trava(), 0640);
        @unlink(self::arquivoCodigo());
        Auditoria::registrar('sistema_instalado', 'sistema', self::VERSAO, 'CRM instalado pelo ' . $origem, null, null, null, $origem);
        $passo('Instalação travada (storage/instalado.lock): o instalador não abre mais');
        return ['passos' => $passos, 'env' => $env, 'env_gravado' => $gravado, 'admin' => $adm['login']];
    }
}
