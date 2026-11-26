<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\Auth;
use App\Services\ExcelPiService;
use App\Services\PiService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Fase 6 — revisão de segurança automatizada: acesso direto a rotas, CSRF, XSS, SQL Injection,
 * injeção de fórmula no Excel, cabeçalhos, arquivos fora da pasta pública e redirecionamento externo.
 */
final class SegurancaTest extends TestCase
{
    private const PUBLICAS = ['/login', '/esqueci-senha', '/redefinir-senha/{token}'];

    private function req(string $method, string $path, array $post = [], array $query = [], array $server = []): Response
    {
        Auth::reset();
        return App::handle(new Request($method, $path, $query, $post, [],
            $server + ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'phpunit', 'HTTP_HOST' => '127.0.0.1']));
    }

    private function entrar(string $login): void
    {
        $_SESSION = ['_csrf' => 'tok'];
        Auth::reset();
        Auth::entrar(Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]));
    }

    private function rotas(): array
    {
        $r = new Router();
        (require App::root() . '/routes/web.php')($r);
        return $r->rotas();
    }

    private static function preencher(string $pattern): string
    {
        return strtr($pattern, ['{id}' => '1', '{token}' => str_repeat('a', 64), '{chave}' => 'vendas_periodo', '{nome}' => 'crm_20260101_000000.sql.gz', '{acao}' => 'aprovar', '{tipo}' => 'programas']);
    }

    public function testTodaRotaInternaExigeLoginEPermissaoNoServidor(): void
    {
        $rotas = $this->rotas();
        self::assertGreaterThan(80, count($rotas));
        $abertas = [];
        foreach ($rotas as $rt) {
            if (in_array($rt['pattern'], self::PUBLICAS, true)) {
                self::assertContains('guest', $rt['mw'], $rt['pattern']);
                continue;
            }
            if (!in_array('auth', $rt['mw'], true)) {
                $abertas[] = $rt['method'] . ' ' . $rt['pattern'];
                continue;
            }
            $_SESSION = [];
            $resp = $this->req($rt['method'], self::preencher($rt['pattern']), $rt['method'] === 'GET' ? [] : ['_token' => 'qualquer']);
            self::assertNotSame(200, $resp->status(), 'sem login: ' . $rt['method'] . ' ' . $rt['pattern']);
            if (str_starts_with($rt['pattern'], '/api/')) {
                self::assertContains($resp->status(), [401, 419], 'API sem login: ' . $rt['pattern']);
            } elseif ($rt['method'] === 'GET') {
                self::assertSame(302, $resp->status(), $rt['pattern']);
                self::assertStringContainsString('/login', $resp->headers()['Location'] ?? '', $rt['pattern']);
            }
        }
        self::assertSame([], $abertas, 'rotas internas sem exigência de login');
        // perfil sem permissão recebe 403 nas telas de gestão
        $this->entrar('teste.exec');
        foreach (['/usuarios', '/permissoes', '/configuracoes', '/auditoria', '/backup', '/cadastros', '/conferencia', '/projetos/novo'] as $p) {
            self::assertSame(403, $this->req('GET', $p)->status(), 'executivo em ' . $p);
        }
    }

    public function testPostSemTokenCsrfERecusado(): void
    {
        $this->entrar('teste.admin');
        $antes = (int) Database::value('SELECT COUNT(*) FROM clientes');
        $dados = ['razao_social' => 'Empresa CSRF Ltda', 'documento' => '11.444.777/0001-61', 'endereco' => 'Rua A, 1', 'cidade' => 'São Luís', 'uf' => 'MA'];
        foreach ([[], ['_token' => ''], ['_token' => 'token-de-outro-site']] as $t) {
            $r = $this->req('POST', '/clientes', $dados + $t);
            self::assertSame(302, $r->status());
            self::assertSame($antes, (int) Database::value('SELECT COUNT(*) FROM clientes'), 'nada gravado sem o token CSRF');
        }
        $j = $this->req('POST', '/api/pis/calcular', [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'errado']);
        self::assertSame(419, $j->status());
    }

    public function testXssEEscapadoNasTelas(): void
    {
        $xss = '<script>alert("x")</script><img src=x onerror=alert(1)>';
        $id = Database::insert('clientes', ['razao_social' => $xss . ' Ltda', 'nome_fantasia' => $xss, 'tipo_documento' => 'CNPJ', 'documento' => '11444777000161',
            'documento_formatado' => '11.444.777/0001-61', 'endereco' => $xss, 'cidade' => 'São Luís', 'uf' => 'MA']);
        $this->entrar('teste.admin');
        foreach (['/clientes/' . $id, '/clientes'] as $p) {
            $b = $this->req('GET', $p, [], ['q' => 'script'])->body();
            self::assertStringNotContainsString('<script>alert', $b, $p);
            self::assertStringNotContainsString('<img src=x', $b, $p);
            self::assertStringContainsString('&lt;script&gt;', $b, $p);
        }
        $b = $this->req('GET', '/clientes', [], ['q' => '"><script>alert(2)</script>'])->body();
        self::assertStringNotContainsString('<script>alert(2)', $b, 'o termo buscado volta escapado');
    }

    public function testSqlInjectionNaoAfetaBuscasNemLogin(): void
    {
        $this->entrar('teste.admin');
        $ataques = ["' OR 1=1 -- ", '1; DROP TABLE pis; --', "\" OR \"\"=\"", "') UNION SELECT senha_hash FROM usuarios -- "];
        foreach ($ataques as $a) {
            foreach (['/clientes', '/agencias', '/pis', '/usuarios', '/auditoria'] as $p) {
                $r = $this->req('GET', $p, [], ['q' => $a, 'pagina' => $a, 'situacao' => $a]);
                self::assertSame(200, $r->status(), $p . ' com ' . $a);
                self::assertStringNotContainsString('$argon2id$', $r->body());
                self::assertStringNotContainsString('SQLSTATE', $r->body());
            }
        }
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pis'"), 'tabela intacta');
        self::assertGreaterThan(5, (int) Database::value('SELECT COUNT(*) FROM usuarios'));
        $_SESSION = ['_csrf' => 'tok'];
        $r = $this->req('POST', '/login', ['_token' => 'tok', 'login' => "teste.admin' -- ", 'senha' => "' OR '1'='1"]);
        self::assertNull($_SESSION['usuario_id'] ?? null, 'login não é burlado');
        self::assertSame(302, $r->status());
    }

    public function testExcelNaoExecutaFormulaDigitadaPeloUsuario(): void
    {
        $f = '=HYPERLINK("http://site-malicioso.test","Clique aqui")';
        $this->como('teste.exec');
        $S = $this->estadoBase('FM', ['titulo' => $f, 'produto' => '+cmd|calc', 'cli_contato' => '@SUM(1+1)']);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]]];
        $pi = PiService::gerar($S)['pis'][0];
        [$arq] = ExcelPiService::gerarArquivo($pi['id']);
        $ws = IOFactory::load($arq)->getActiveSheet();
        unlink($arq);
        $achados = 0;
        foreach ($ws->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $v = (string) $cell->getValue();
                if (str_contains($v, 'site-malicioso') || str_contains($v, '+cmd|calc') || str_contains($v, '@SUM(1+1)')) {
                    $achados++;
                    self::assertSame(DataType::TYPE_STRING, $cell->getDataType(), 'texto digitado vira texto, não fórmula: ' . $cell->getCoordinate());
                }
            }
        }
        self::assertGreaterThanOrEqual(2, $achados);
    }

    public function testCabecalhosDeSegurancaEArquivosForaDaPastaPublica(): void
    {
        $h = $this->req('GET', '/login')->headers();
        self::assertSame('DENY', $h['X-Frame-Options']);
        self::assertSame('nosniff', $h['X-Content-Type-Options']);
        self::assertStringContainsString("script-src 'self'", $h['Content-Security-Policy']);
        self::assertStringNotContainsString("'unsafe-eval'", $h['Content-Security-Policy']);
        self::assertStringContainsString("frame-ancestors 'none'", $h['Content-Security-Policy']);
        self::assertSame('no-store, private', $h['Cache-Control']);
        $hs = $this->req('GET', '/login', [], [], ['HTTPS' => 'on'])->headers();
        self::assertStringContainsString('max-age=31536000', $hs['Strict-Transport-Security']);

        $pub = App::root() . '/public';
        foreach (['.env', 'storage', 'vendor', 'app', 'config', 'database', 'instalar.php', 'composer.json'] as $x) {
            self::assertFileDoesNotExist($pub . '/' . $x, $x . ' não pode estar na pasta pública');
        }
        self::assertStringContainsString('Require all denied', (string) file_get_contents(App::root() . '/.htaccess'));
        $ht = (string) file_get_contents($pub . '/.htaccess');
        self::assertStringContainsString('(^|/)\. - [F,L]', $ht, 'bloqueia arquivos ocultos');
        self::assertStringContainsString('-Indexes', $ht, 'sem listagem de pastas');
        // nada de senha real no código-fonte
        $suspeitos = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(App::root() . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arq) {
            $txt = (string) file_get_contents((string) $arq);
            if (preg_match('/(DB_PASSWORD|MAIL_PASSWORD)\s*=\s*[\'"]?[^\'"\s]{4,}/', $txt) || preg_match('/password_hash\(\s*[\'"]/', $txt)) {
                $suspeitos[] = (string) $arq;
            }
        }
        self::assertSame([], $suspeitos);
        self::assertMatchesRegularExpression('/^DB_PASSWORD=$/m', (string) file_get_contents(App::root() . '/.env.example'), '.env.example sem senha');
    }

    public function testNaoRedirecionaParaSiteExterno(): void
    {
        $mk = fn (string $ref) => new Request('POST', '/clientes', [], [], [], ['HTTP_REFERER' => $ref, 'HTTP_HOST' => 'crm.grupomirante.test']);
        $url = \App\Core\Config::get('app.url');
        \App\Core\Config::set('app.url', 'https://crm.grupomirante.test');
        try {
            self::assertSame('/', App::refererSeguro($mk('https://crm.grupomirante.test.evil.com/x')));
            self::assertSame('/', App::refererSeguro($mk('https://evil.com/?https://crm.grupomirante.test')));
            self::assertSame('https://crm.grupomirante.test/clientes?q=a', App::refererSeguro($mk('https://crm.grupomirante.test/clientes?q=a')));
            \App\Core\Config::set('app.url', '');
            self::assertSame('/', App::refererSeguro($mk('https://evil.com/clientes')));
            self::assertSame('https://crm.grupomirante.test/pis', App::refererSeguro($mk('https://crm.grupomirante.test/pis')));
        } finally {
            \App\Core\Config::set('app.url', $url);
        }
    }

    public function testInstaladorTravadoNaoAbre(): void
    {
        $lock = App::root() . '/storage/instalado.lock';
        $criado = false;
        if (!is_file($lock)) {
            file_put_contents($lock, '{}');
            $criado = true;
        }
        try {
            $r = \App\Controllers\InstaladorController::handle(new Request('GET', '/instalar', [], [], [], ['HTTP_HOST' => 'x']));
            self::assertSame(404, $r->status());
            self::assertFileDoesNotExist(App::root() . '/storage/codigo-instalacao.txt', 'instalado: nenhum código novo é gerado');
        } finally {
            if ($criado) {
                unlink($lock);
            }
        }
    }
}
