<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\Instalacao\SeedTeste;
use Tests\TestCase;

/**
 * Varre as telas pela mesma entrada do servidor (App::handle): cada página abre sem erro para quem tem permissão,
 * devolve 403 para quem não tem, exige login e recusa POST sem token CSRF.
 */
final class TelasTest extends TestCase
{
    private function req(string $method, string $path, array $post = [], array $query = []): Response
    {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'phpunit', 'HTTP_ACCEPT' => 'text/html'];
        Auth::reset();
        return App::handle(new Request($method, $path, $query, $post, [], $server));
    }

    private function entrar(string $login): void
    {
        $_SESSION = [];
        Auth::reset();
        $u = Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]);
        Auth::entrar($u);
    }

    /** @return list<string> */
    private function paginas(): array
    {
        $cli = (int) Database::value('SELECT id FROM clientes LIMIT 1');
        $ag = (int) Database::value('SELECT id FROM agencias LIMIT 1');
        $us = self::$u['teste.exec'];
        $tab = (int) Database::value("SELECT t.id FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE v.prefixo = 'FM' AND t.codigo = 'LOC'");
        $tabTv = (int) Database::value("SELECT t.id FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE v.prefixo = 'TV' AND t.codigo = 'MA1'");
        $tabDig = (int) Database::value("SELECT t.id FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE v.prefixo = 'IMI'");
        $prog = (int) Database::value("SELECT id FROM programas WHERE sigla = 'ACOREC'");
        $fmt = (int) Database::value("SELECT id FROM formatos_midia WHERE sigla = 'IBAL'");
        \App\Services\Auditoria::registrar('teste_telas', 'teste', 1, 'Registro para abrir o detalhe da auditoria', ['a' => 1], ['a' => 2]);
        $log = (int) Database::value('SELECT MAX(id) FROM logs_auditoria');
        return ['/', '/notificacoes', '/minha-senha', '/clientes', '/clientes/novo', "/clientes/{$cli}", "/clientes/{$cli}/editar", '/agencias', '/agencias/nova', "/agencias/{$ag}", "/agencias/{$ag}/editar",
            '/usuarios', '/usuarios/novo', "/usuarios/{$us}", "/usuarios/{$us}/editar", '/permissoes', '/cadastros', '/cadastros/empresas', '/cadastros/veiculos', '/cadastros/veiculos/1',
            '/cadastros/tabelas', "/cadastros/tabelas/{$tab}/precos", "/cadastros/tabelas/{$tabTv}/precos", "/cadastros/tabelas/{$tabDig}/precos", '/cadastros/programas', '/cadastros/programas/novo', "/cadastros/programas/{$prog}",
            '/cadastros/formatos', '/cadastros/formatos/novo', "/cadastros/formatos/{$fmt}", '/cadastros/indices', '/cadastros/clausulas', '/cadastros/feriados', '/cadastros/listas',
            '/configuracoes', '/auditoria', "/auditoria/{$log}"];
    }

    public function testAdministradorAbreTodasAsTelas(): void
    {
        $this->entrar('teste.admin');
        foreach ($this->paginas() as $pg) {
            $r = $this->req('GET', $pg);
            self::assertSame(200, $r->status(), "GET {$pg}: " . strip_tags(substr($r->body(), 0, 300)));
            self::assertStringNotContainsString('Algo deu errado', $r->body(), $pg);
        }
        $r = $this->req('GET', '/api/clientes', [], ['q' => 'Cliente']);
        self::assertSame(200, $r->status());
        self::assertSame('Cliente Teste Comércio Ltda.', json_decode($r->body(), true)['itens'][0]['titulo']);
        $r = $this->req('GET', '/api/painel');
        self::assertArrayHasKey('indicadores', json_decode($r->body(), true));
    }

    public function testPainelAbreParaTodosOsPerfis(): void
    {
        foreach (['teste.diretor', 'teste.gerente', 'teste.conf', 'teste.exec', 'teste.vend', 'teste.fin'] as $login) {
            $this->entrar($login);
            $r = $this->req('GET', '/', [], ['de' => '2026-01-01', 'ate' => '2026-12-31', 'veiculo' => '1', 'situacao' => 'Enviada']);
            self::assertSame(200, $r->status(), $login . ': ' . strip_tags(substr($r->body(), 0, 300)));
            self::assertStringNotContainsString('Algo deu errado', $r->body());
        }
    }

    public function testPermissoesNasRotas(): void
    {
        $this->entrar('teste.exec');
        foreach (['/usuarios', '/permissoes', '/cadastros', '/configuracoes', '/auditoria', '/clientes/1/excluir'] as $pg) {
            $metodo = str_ends_with($pg, 'excluir') ? 'POST' : 'GET';
            if ($metodo === 'POST') {
                $_SESSION['_csrf'] = 'x';
            }
            self::assertSame(403, $this->req($metodo, $pg, ['_token' => 'x'])->status(), $pg);
        }
        self::assertSame(200, $this->req('GET', '/clientes')->status());
        $this->entrar('teste.conf');
        self::assertSame(403, $this->req('GET', '/clientes/novo')->status(), 'Conferência não cadastra cliente');
    }

    public function testSemLoginVaiParaOLoginEApiResponde401(): void
    {
        $_SESSION = [];
        Auth::reset();
        $r = $this->req('GET', '/clientes');
        self::assertSame(302, $r->status());
        self::assertStringEndsWith('/login', $r->headers()['Location']);
        self::assertSame(200, $this->req('GET', '/login')->status());
        $api = App::handle(new Request('GET', '/api/clientes', ['q' => 'x'], [], [], ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json']));
        self::assertSame(401, $api->status());
    }

    public function testPostSemCsrfERecusado(): void
    {
        $this->entrar('teste.admin');
        $_SESSION['_csrf'] = 'token-certo';
        $r = $this->req('POST', '/clientes', ['_token' => 'errado', 'razao_social' => 'X', 'documento' => '52998224725']);
        self::assertSame(302, $r->status(), '419 vira redirecionamento com aviso');
        self::assertSame(0, (int) Database::value("SELECT COUNT(*) FROM clientes WHERE documento = '52998224725'"));
        $r = $this->req('POST', '/clientes', ['_token' => 'token-certo', 'razao_social' => 'Pessoa Teste', 'documento' => '529.982.247-25']);
        self::assertSame(302, $r->status());
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM clientes WHERE documento = '52998224725'"));
    }

    public function testPrimeiroAcessoObrigaCriarSenha(): void
    {
        Database::query('UPDATE usuarios SET trocar_senha = 1 WHERE login = ?', ['teste.vend']);
        $this->entrar('teste.vend');
        $r = $this->req('GET', '/clientes');
        self::assertSame(302, $r->status());
        self::assertStringEndsWith('/primeiro-acesso', $r->headers()['Location']);
        self::assertSame(200, $this->req('GET', '/primeiro-acesso')->status());
    }

    public function testCabecalhosDeSeguranca(): void
    {
        $r = $this->req('GET', '/login');
        $h = $r->headers();
        self::assertSame('DENY', $h['X-Frame-Options']);
        self::assertSame('nosniff', $h['X-Content-Type-Options']);
        self::assertStringContainsString("script-src 'self'", $h['Content-Security-Policy']);
    }

    public function testEscapeContraXss(): void
    {
        $this->entrar('teste.admin');
        Database::insert('clientes', ['razao_social' => '<script>alert(1)</script>', 'tipo_documento' => 'CPF', 'documento' => '39053344705', 'documento_formatado' => '390.533.447-05']);
        $r = $this->req('GET', '/clientes', [], ['q' => 'script']);
        self::assertStringNotContainsString('<script>alert(1)</script>', $r->body());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $r->body());
    }

    public function testCadastrosSalvamPrecoEAuditam(): void
    {
        $this->entrar('teste.admin');
        $_SESSION['_csrf'] = 't';
        $tab = (int) Database::value("SELECT t.id FROM tabelas_precos t JOIN veiculos v ON v.id = t.veiculo_id WHERE v.prefixo = 'FM' AND t.codigo = 'LOC'");
        $prog = (int) Database::value("SELECT id FROM programas WHERE sigla = 'ACOREC'");
        $r = $this->req('POST', "/cadastros/tabelas/{$tab}/precos", ['_token' => 't', 'preco' => ["p{$prog}|duracao|30" => '80,00']]);
        self::assertSame(302, $r->status());
        self::assertSame('80.00', (string) Database::value("SELECT valor FROM precos_midia WHERE tabela_preco_id = ? AND programa_id = ? AND tipo = 'duracao' AND duracao_seg = 30", [$tab, $prog]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'precos_alterados'"));
        $this->req('POST', '/configuracoes', ['_token' => 't', 'cfg' => ['alcada_gestor_desconto_pct' => '15']]);
        self::assertSame('15', Database::value("SELECT valor FROM configuracoes WHERE chave = 'alcada_gestor_desconto_pct'"));
        $this->req('POST', '/permissoes', ['_token' => 't', 'perm' => []]);
        self::assertSame(0, (int) Database::value("SELECT COUNT(*) FROM perfil_permissoes pp JOIN perfis p ON p.id = pp.perfil_id WHERE p.codigo = 'vend'"), 'matriz salva');
        self::assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM perfil_permissoes pp JOIN perfis p ON p.id = pp.perfil_id WHERE p.codigo = 'admin'"), 'administrador não é alterado');
        self::assertSame(SeedTeste::SENHA, SeedTeste::SENHA);
    }
}
