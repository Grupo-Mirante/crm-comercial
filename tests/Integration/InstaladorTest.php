<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\InstaladorController;
use App\Core\BusinessException;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\Configuracao;
use App\Services\Instalacao\Instalador;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Fase 6 — instalador. Cada teste usa uma pasta temporária (no lugar de .env e storage/) e um banco próprio,
 * crm_inst_phpunit_teste, apagado no final. A conexão e a configuração dos outros testes são restauradas.
 */
final class InstaladorTest extends TestCase
{
    private const BANCO = 'crm_inst_phpunit_teste';
    private string $dir;
    private PDO $pdoOriginal;
    private array $dbOriginal;
    private array $appOriginal;

    protected function setUp(): void
    {
        $this->pdoOriginal = Database::connection();
        $this->dbOriginal = Config::get('database');
        $this->appOriginal = Config::get('app');
        $this->dir = sys_get_temp_dir() . '/crm-inst-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/storage', 0750, true);
        Instalador::$raiz = $this->dir;
        $this->servidor()->exec('DROP DATABASE IF EXISTS `' . self::BANCO . '`');
    }

    protected function tearDown(): void
    {
        Database::setConnection($this->pdoOriginal);
        Config::set('database', $this->dbOriginal);
        Config::set('app', $this->appOriginal);
        Instalador::$raiz = null;
        Configuracao::limparCache();
        Auth::como(null);
        $this->servidor()->exec('DROP DATABASE IF EXISTS `' . self::BANCO . '`');
        exec('rm -rf ' . escapeshellarg($this->dir));
        $GLOBALS['__old'] = $GLOBALS['__errors'] = [];
    }

    private function servidor(): PDO
    {
        return Database::connect($this->dbOriginal, false);
    }

    private function db(array $f = []): array
    {
        return array_merge(['host' => (string) $this->dbOriginal['host'], 'port' => (string) $this->dbOriginal['port'], 'socket' => (string) $this->dbOriginal['socket'],
            'database' => self::BANCO, 'username' => (string) $this->dbOriginal['username'], 'password' => (string) $this->dbOriginal['password'], 'criar' => true], $f);
    }

    private function admin(array $f = []): array
    {
        $s = 'Provisoria' . random_int(1000, 9999) . 'xy';
        return array_merge(['nome' => 'Maria Instalação', 'login' => 'maria.silva', 'email' => 'maria@exemplo.test', 'senha' => $s, 'confirmacao' => $s], $f);
    }

    public function testInstalacaoCompletaDoZeroSemDadosFicticios(): void
    {
        Instalador::garantirCodigo();
        $res = Instalador::instalar($this->db(), ['url' => 'https://crm.exemplo.test/', 'env' => 'production'], $this->admin(), 'teste automático');
        self::assertCount(7, $res['passos']);
        self::assertFileExists($this->dir . '/storage/instalado.lock');
        self::assertFileDoesNotExist($this->dir . '/storage/codigo-instalacao.txt', 'o código some depois de instalar');
        $env = (string) file_get_contents($this->dir . '/.env');
        self::assertStringContainsString("APP_ENV=production\n", $env);
        self::assertStringContainsString("APP_DEBUG=false\n", $env);
        self::assertStringContainsString("APP_URL=https://crm.exemplo.test\n", $env, 'sem barra no final');
        self::assertStringContainsString('DB_DATABASE=' . self::BANCO . "\n", $env);
        self::assertSame('0640', substr(sprintf('%o', fileperms($this->dir . '/.env')), -4));
        foreach (['logs', 'sessions', 'cache', 'exports', 'uploads', 'backups'] as $p) {
            self::assertDirectoryExists($this->dir . '/storage/' . $p);
        }

        $pdo = Database::connection();
        $q = fn (string $sql) => $pdo->query($sql)->fetchColumn();
        self::assertSame(self::BANCO, $q('SELECT DATABASE()'));
        $u = $pdo->query('SELECT u.*, p.codigo AS perfil FROM usuarios u JOIN perfis p ON p.id = u.perfil_id')->fetchAll();
        self::assertCount(1, $u, 'só o administrador: nenhum usuário padrão nem de teste');
        self::assertSame('admin', $u[0]['perfil']);
        self::assertSame(1, (int) $u[0]['trocar_senha'], 'troca de senha obrigatória no primeiro acesso');
        self::assertStringStartsWith('$argon2id$', $u[0]['senha_hash']);
        foreach (['clientes', 'agencias', 'pis', 'projetos_especiais', 'reservas', 'pi_rascunhos'] as $t) {
            self::assertSame(0, (int) $q("SELECT COUNT(*) FROM {$t}"), "produção começa sem dados fictícios em {$t}");
        }
        self::assertSame(1034, (int) $q('SELECT COUNT(*) FROM precos_midia'), 'tabela de preços 2026 do Gerador');
        self::assertSame(7, (int) $q('SELECT COUNT(*) FROM perfis'));
        self::assertSame(2, (int) $q("SELECT COUNT(*) FROM logs_auditoria WHERE acao IN ('usuario_criado','sistema_instalado')"));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('já está instalado');
        Instalador::instalar($this->db(), ['url' => 'https://x.test'], $this->admin(), 'teste automático');
    }

    public function testRecusaBancoComOutroSistemaOuComOCrmJaInstalado(): void
    {
        $srv = $this->servidor();
        $srv->exec('CREATE DATABASE `' . self::BANCO . '`');
        $srv->exec('CREATE TABLE `' . self::BANCO . '`.clientes_antigos (id INT)');
        try {
            Instalador::instalar($this->db(['criar' => false]), ['url' => 'https://crm.exemplo.test'], $this->admin(), 'teste automático');
            self::fail('banco com tabelas de outro sistema');
        } catch (BusinessException $e) {
            self::assertStringContainsString('outro sistema', $e->getMessage());
        }
        self::assertFileDoesNotExist($this->dir . '/.env', 'nada é gravado antes de conferir o banco');
        self::assertSame(1, (int) $srv->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::BANCO . "'")->fetchColumn(), 'nada foi apagado');
        // o banco dos testes já tem o CRM com usuários
        try {
            Instalador::conferirBancoVazio($this->pdoOriginal);
            self::fail('banco com o CRM instalado');
        } catch (BusinessException $e) {
            self::assertStringContainsString('já tem o CRM instalado', $e->getMessage());
        }
    }

    public function testRetomaInstalacaoInterrompida(): void
    {
        // simula uma instalação que caiu depois de criar as tabelas e antes do administrador
        $c = Instalador::conectar($this->db(), true);
        (new \App\Services\Instalacao\Migrador($c['pdo']))->migrar(true);
        Instalador::conferirBancoVazio($c['pdo']);
        $res = Instalador::instalar($this->db(), ['url' => 'https://crm.exemplo.test'], $this->admin(), 'teste automático');
        self::assertStringContainsString('retomada', implode(' ', $res['passos']));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM usuarios'));
    }

    public function testValidacoesDoAdministrador(): void
    {
        $casos = [
            [['login' => 'admin'], 'adm_login'],
            [['login' => 'Maria Silva'], 'adm_login'],
            [['senha' => 'Curta123', 'confirmacao' => 'Curta123'], 'adm_senha'],
            [['senha' => 'somenteletrasaqui', 'confirmacao' => 'somenteletrasaqui'], 'adm_senha'],
            [['senha' => 'maria.silva2026', 'confirmacao' => 'maria.silva2026'], 'adm_senha'],
            [['confirmacao' => 'OutraSenha2026xx'], 'adm_confirmacao'],
            [['email' => 'sem-arroba'], 'adm_email'],
            [['nome' => 'Jo'], 'adm_nome'],
        ];
        foreach ($casos as [$f, $campo]) {
            try {
                Instalador::validarAdmin($this->admin($f));
                self::fail('deveria recusar ' . json_encode($f));
            } catch (ValidationException $e) {
                self::assertArrayHasKey($campo, $e->errors, json_encode($f));
            }
        }
        self::assertSame('maria.silva', Instalador::validarAdmin($this->admin(['login' => '  Maria.Silva ']))['login']);
        // falha de validação não cria banco nem .env
        try {
            Instalador::instalar($this->db(), ['url' => 'https://crm.exemplo.test'], $this->admin(['login' => 'admin']), 'teste automático');
        } catch (ValidationException) {
        }
        self::assertFileDoesNotExist($this->dir . '/.env');
        self::assertSame(0, (int) $this->servidor()->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . self::BANCO . "'")->fetchColumn());
    }

    public function testCodigoDeInstalacaoEEnv(): void
    {
        Instalador::garantirCodigo();
        $arq = $this->dir . '/storage/codigo-instalacao.txt';
        $codigo = trim((string) file_get_contents($arq));
        self::assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}-[A-Z2-9]{5}$/', $codigo);
        self::assertSame('0600', substr(sprintf('%o', fileperms($arq)), -4));
        Instalador::garantirCodigo();
        self::assertSame($codigo, trim((string) file_get_contents($arq)), 'não troca o código a cada visita');
        self::assertTrue(Instalador::codigoValido(strtolower(str_replace('-', '', $codigo))));
        self::assertFalse(Instalador::codigoValido('AAAAA-BBBBB-CCCCC'));
        self::assertFalse(Instalador::codigoValido(''));

        $env = Instalador::montarEnv(['DB_PASSWORD' => "sen ha#'1", 'DB_USERNAME' => 'crm_user', 'APP_URL' => 'https://crm.x.test', 'NOVA' => "a b"]);
        self::assertStringContainsString("DB_PASSWORD=\"sen ha#'1\"\n", $env);
        self::assertStringContainsString("DB_USERNAME=crm_user\n", $env);
        self::assertStringContainsString("NOVA='a b'\n", $env);
        self::assertStringContainsString('# --- Banco de dados MySQL', $env, 'mantém os comentários do .env.example');
        $this->expectException(ValidationException::class);
        Instalador::montarEnv(['DB_PASSWORD' => "a'b\\c"]);
    }

    public function testAssistenteWebExigeCodigoETravaDepois(): void
    {
        $req = fn (string $m, array $post = []) => InstaladorController::handle(new Request($m, '/instalar', [], $post, [],
            ['REQUEST_METHOD' => $m, 'HTTP_HOST' => 'crm.exemplo.test', 'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '10.0.0.5', 'HTTPS' => 'on']));
        $r = $req('GET');
        self::assertSame(200, $r->status());
        self::assertStringContainsString('Código de instalação', $r->body());
        self::assertStringNotContainsString(trim((string) file_get_contents($this->dir . '/storage/codigo-instalacao.txt')), $r->body(), 'o código nunca aparece na página');
        self::assertSame(422, $req('POST', ['acao' => 'entrar', 'codigo' => 'ERRAD-OERRA-DOXXX'])->status());
        $codigo = trim((string) file_get_contents($this->dir . '/storage/codigo-instalacao.txt'));
        // sem o código, nem o teste de conexão roda
        self::assertStringContainsString('Código incorreto', $req('POST', ['acao' => 'testar'] + $this->db())->body());
        $f = $req('POST', ['acao' => 'entrar', 'codigo' => $codigo]);
        self::assertStringContainsString('Dados da instalação', $f->body());
        self::assertStringContainsString('https://crm.exemplo.test', $f->body(), 'endereço detectado');
        $t = $req('POST', ['acao' => 'testar', 'codigo' => $codigo, 'db_host' => $this->dbOriginal['host'], 'db_port' => (string) $this->dbOriginal['port'], 'db_socket' => (string) $this->dbOriginal['socket'],
            'db_database' => self::BANCO, 'db_username' => $this->dbOriginal['username'], 'db_password' => 'senha-errada']);
        self::assertStringContainsString('Usuário ou senha do MySQL incorretos', $t->body());
        $i = $req('POST', ['acao' => 'instalar', 'codigo' => $codigo, 'db_host' => $this->dbOriginal['host'], 'db_port' => (string) $this->dbOriginal['port'], 'db_socket' => (string) $this->dbOriginal['socket'],
            'db_database' => self::BANCO, 'db_username' => $this->dbOriginal['username'], 'db_password' => $this->dbOriginal['password'], 'db_criar' => '1',
            'app_url' => 'https://crm.exemplo.test', 'app_env' => 'production', 'adm_nome' => 'Maria Instalação', 'adm_login' => 'maria.silva', 'adm_email' => 'maria@exemplo.test',
            'adm_senha' => 'Provisoria2026xy', 'adm_confirmacao' => 'Provisoria2026xy']);
        self::assertSame(200, $i->status(), strip_tags(substr($i->body(), 0, 600)));
        self::assertStringContainsString('CRM instalado', $i->body());
        self::assertStringNotContainsString('Provisoria2026xy', $i->body(), 'a senha não volta para a tela');
        self::assertSame(404, $req('GET')->status(), 'instalado: o assistente não abre mais');
        self::assertSame(404, $req('POST', ['acao' => 'entrar', 'codigo' => $codigo])->status());
    }
}
