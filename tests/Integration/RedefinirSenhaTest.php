<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\BusinessException;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\AuthService;
use App\Services\Instalacao\SeedTeste;
use App\Services\UsuarioService;
use Tests\TestCase;

/**
 * RN-75 — botão "Redefinir senha" no painel de usuários: gera senha provisória nova (mostrada uma vez),
 * obriga a troca no próximo acesso, fica registrado na auditoria e não amplia o poder de quem só tem
 * "gerenciar usuários" (a senha de um administrador só é redefinida por outro administrador).
 */
final class RedefinirSenhaTest extends TestCase
{
    private function req(string $method, string $path, array $post = []): Response
    {
        Auth::reset();
        return App::handle(new Request($method, $path, [], $post, [],
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'phpunit', 'HTTP_HOST' => '127.0.0.1']));
    }

    private function entrar(string $login): void
    {
        $_SESSION = ['_csrf' => 'tok'];
        Auth::reset();
        Auth::entrar(Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]));
    }

    /** Dá "gerenciar usuários" ao perfil de diretor (configuração possível na tela de permissões). */
    private function diretorGerenciaUsuarios(): void
    {
        Database::query("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
            SELECT (SELECT id FROM perfis WHERE codigo = 'diretor'), id FROM permissoes WHERE modulo = 'usuarios' AND acao IN ('ver', 'gerenciar')");
    }

    private function hash(string $login): string
    {
        return (string) Database::value('SELECT senha_hash FROM usuarios WHERE login = ?', [$login]);
    }

    public function testAdministradorRedefineSenhaComTrocaObrigatoriaEAuditoria(): void
    {
        $exec = self::$u['teste.exec'];
        Database::query('INSERT INTO sessoes (id, usuario_id, dados, ip, user_agent, criada_em, ultimo_uso) VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())', [str_repeat('b', 64), $exec, '', '10.0.0.9', 'phpunit']);
        $this->como('teste.admin');
        $senha = UsuarioService::redefinirSenha($exec);

        self::assertSame(10, strlen($senha));
        self::assertSame(1, (int) Database::value('SELECT trocar_senha FROM usuarios WHERE id = ?', [$exec]), 'troca obrigatória no próximo acesso');
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM sessoes WHERE usuario_id = ?', [$exec]), 'sessões abertas encerradas');
        self::assertStringNotContainsString($senha, $this->hash('teste.exec'));
        $log = Database::first("SELECT usuario_id, entidade, entidade_id, descricao FROM logs_auditoria WHERE acao = 'senha_redefinida_admin' ORDER BY id DESC LIMIT 1");
        self::assertSame(self::$u['teste.admin'], (int) $log['usuario_id'], 'auditoria guarda quem redefiniu');
        self::assertSame(['usuario', (string) $exec], [$log['entidade'], (string) $log['entidade_id']]);
        self::assertStringNotContainsString($senha, (string) Database::value('SELECT CONCAT_WS(" ", descricao, dados_antes, dados_depois) FROM logs_auditoria ORDER BY id DESC LIMIT 1'), 'a senha não vai para o log');

        Auth::como(null);
        try {
            AuthService::tentarLogin('teste.exec', SeedTeste::SENHA, '10.0.0.7');
            self::fail('a senha antiga deixa de valer');
        } catch (BusinessException) {
        }
        self::assertSame(1, (int) AuthService::tentarLogin('teste.exec', $senha, '10.0.0.7')['trocar_senha']);
    }

    public function testQuemSoGerenciaUsuariosNaoRedefineSenhaDeAdministrador(): void
    {
        $this->diretorGerenciaUsuarios();
        $this->como('teste.diretor');
        self::assertTrue(Auth::can('usuarios.gerenciar'));
        $antes = $this->hash('teste.admin');
        try {
            UsuarioService::redefinirSenha(self::$u['teste.admin']);
            self::fail('diretor não pode redefinir a senha do administrador');
        } catch (BusinessException $e) {
            self::assertStringContainsString('Só um administrador', $e->getMessage());
        }
        self::assertSame($antes, $this->hash('teste.admin'), 'senha do administrador intacta');
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'senha_redefinida_recusada' AND usuario_id = ? AND entidade_id = ?", [self::$u['teste.diretor'], (string) self::$u['teste.admin']]), 'tentativa recusada fica na auditoria');

        // ...mas continua podendo redefinir a de quem não é administrador
        self::assertSame(10, strlen(UsuarioService::redefinirSenha(self::$u['teste.exec'])));
    }

    public function testAdministradorRedefineSenhaDeOutroAdministradorMasNaoADele(): void
    {
        $outro = Database::insert('usuarios', ['nome' => 'Outro Administrador', 'login' => 'outro.admin', 'senha_hash' => AuthService::hash(SeedTeste::SENHA),
            'perfil_id' => UsuarioService::perfilAdminId(), 'ativo' => 1, 'trocar_senha' => 0]);
        $this->como('teste.admin');
        self::assertSame(10, strlen(UsuarioService::redefinirSenha($outro)));
        $antes = $this->hash('teste.admin');
        try {
            UsuarioService::redefinirSenha(self::$u['teste.admin']);
            self::fail('a própria senha se troca em "Minha senha"');
        } catch (BusinessException $e) {
            self::assertStringContainsString('Minha senha', $e->getMessage());
        }
        self::assertSame($antes, $this->hash('teste.admin'));
    }

    public function testBotaoNasTelasESenhaAparecendoUmaSoVez(): void
    {
        $exec = self::$u['teste.exec'];
        $this->entrar('teste.admin');
        $acao = '/usuarios/' . $exec . '/redefinir-senha';
        foreach (['/usuarios', '/usuarios/' . $exec, '/usuarios/' . $exec . '/editar'] as $pg) {
            $r = $this->req('GET', $pg);
            self::assertSame(200, $r->status(), $pg);
            self::assertStringContainsString('action="' . url($acao) . '"', $r->body(), 'botão de redefinir disponível em ' . $pg);
        }

        $r = $this->req('POST', $acao, ['_token' => 'tok']);
        self::assertSame(302, $r->status());
        self::assertStringEndsWith('/usuarios/' . $exec, $r->headers()['Location']);
        $senha = (string) $_SESSION['_senha_unica']['senha'];
        self::assertStringContainsString($senha, $this->req('GET', '/usuarios/' . $exec)->body(), 'senha aparece na tela do usuário');
        self::assertStringNotContainsString($senha, $this->req('GET', '/usuarios/' . $exec)->body(), 'e só uma vez');
        self::assertTrue(password_verify($senha, $this->hash('teste.exec')));
    }

    public function testDiretorComGerenciarNaoVeBotaoAtivoParaAdministradorENemConsegueViaPost(): void
    {
        $this->diretorGerenciaUsuarios();
        $admin = self::$u['teste.admin'];
        $this->entrar('teste.diretor');
        $acao = '/usuarios/' . $admin . '/redefinir-senha';
        $b = $this->req('GET', '/usuarios/' . $admin)->body();
        self::assertStringNotContainsString('action="' . url($acao) . '"', $b);
        self::assertStringContainsString('Só um administrador pode redefinir', $b, 'o botão aparece desabilitado, com o motivo');
        self::assertStringNotContainsString('action="' . url($acao) . '"', $this->req('GET', '/usuarios')->body());

        $antes = $this->hash('teste.admin');
        $r = $this->req('POST', $acao, ['_token' => 'tok']);
        self::assertSame(302, $r->status());
        self::assertSame($antes, $this->hash('teste.admin'), 'POST direto também é recusado');
        self::assertArrayNotHasKey('_senha_unica', $_SESSION);
    }

    public function testSemGerenciarUsuariosRecebe403(): void
    {
        $this->entrar('teste.gerente');
        $antes = $this->hash('teste.exec');
        self::assertSame(403, $this->req('POST', '/usuarios/' . self::$u['teste.exec'] . '/redefinir-senha', ['_token' => 'tok'])->status());
        self::assertSame($antes, $this->hash('teste.exec'));
    }
}
