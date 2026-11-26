<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\AuthService;
use App\Services\ClienteService;
use App\Services\Instalacao\SeedTeste;
use App\Services\UsuarioService;
use Tests\TestCase;

/** Login, bloqueio, senhas, usuários, permissões por perfil (RN-71 a RN-78) e cadastros (RN-61 a RN-64, RN-79). */
final class AutenticacaoPermissoesTest extends TestCase
{
    public function testLoginELogout(): void
    {
        $u = AuthService::tentarLogin('teste.exec', SeedTeste::SENHA, '10.0.0.1');
        self::assertSame(self::$u['teste.exec'], (int) $u['id']);
        self::assertSame(self::$u['teste.exec'], Auth::id());
        self::assertNotNull(Database::value('SELECT ultimo_acesso_em FROM usuarios WHERE id = ?', [$u['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'login' AND usuario_id = ?", [$u['id']]));
        AuthService::sair();
        self::assertFalse(Auth::check());
        self::assertSame([], array_diff_key($_SESSION, ['_flash' => 1]));
    }

    public function testSenhaErradaEBloqueioTemporario(): void
    {
        for ($i = 0; $i < 4; $i++) {
            try {
                AuthService::tentarLogin('teste.vend', 'errada123', '10.0.0.2');
                self::fail('senha errada');
            } catch (BusinessException $e) {
                self::assertSame('login_invalido', $e->codigo);
            }
        }
        try {
            AuthService::tentarLogin('teste.vend', 'errada123', '10.0.0.2');
        } catch (BusinessException) {
        }
        self::assertNotNull(Database::value('SELECT bloqueado_ate FROM usuarios WHERE login = ?', ['teste.vend']), 'RN-77: 5 erros bloqueiam');
        try {
            AuthService::tentarLogin('teste.vend', SeedTeste::SENHA, '10.0.0.2');
            self::fail('bloqueado mesmo com a senha certa');
        } catch (BusinessException $e) {
            self::assertSame('login_bloqueado', $e->codigo);
        }
        Database::query('UPDATE usuarios SET bloqueado_ate = NOW() - INTERVAL 1 SECOND WHERE login = ?', ['teste.vend']);
        self::assertSame('teste.vend', AuthService::tentarLogin('teste.vend', SeedTeste::SENHA, '10.0.0.2')['login']);
        try {
            AuthService::tentarLogin('nao.existe', 'x1234567', '10.0.0.2');
        } catch (BusinessException $e) {
            self::assertSame('Usuário ou senha incorretos.', $e->getMessage(), 'não revela se o usuário existe');
        }
    }

    public function testUsuarioDesativadoNaoEntra(): void
    {
        $this->como('teste.admin');
        UsuarioService::definirAtivo(self::$u['teste.exec2'], false);
        $this->expectExceptionMessage('Usuário desativado');
        AuthService::tentarLogin('teste.exec2', SeedTeste::SENHA, '10.0.0.3');
    }

    public function testRn75SenhaProvisoriaEPrimeiroAcesso(): void
    {
        $this->como('teste.admin');
        $novo = UsuarioService::criar(['nome' => 'Nova Executiva', 'login' => 'nova.exec', 'email' => 'nova@teste.invalid', 'matricula' => 'E099',
            'perfil_id' => Database::value("SELECT id FROM perfis WHERE codigo = 'exec'"), 'gestor_id' => self::$u['teste.gerente']]);
        self::assertSame(10, strlen($novo['senha']));
        $hash = (string) Database::value('SELECT senha_hash FROM usuarios WHERE id = ?', [$novo['id']]);
        self::assertStringStartsWith('$argon2id$', $hash, 'RN-78: Argon2id');
        self::assertStringNotContainsString($novo['senha'], $hash);
        Auth::como(null);
        $u = AuthService::tentarLogin('nova.exec', $novo['senha'], '10.0.0.4');
        self::assertSame(1, (int) $u['trocar_senha'], 'primeiro acesso obriga a criar senha');
        try {
            AuthService::trocarSenha($novo['id'], null, 'curta', 'curta', true);
            self::fail('senha curta');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('nova_senha', $e->errors);
        }
        try {
            AuthService::trocarSenha($novo['id'], null, 'nova.exec123', 'nova.exec123', true);
            self::fail('contém o login');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('nova_senha', $e->errors);
        }
        AuthService::trocarSenha($novo['id'], null, 'Pessoal2026x', 'Pessoal2026x', true);
        self::assertSame(0, (int) Database::value('SELECT trocar_senha FROM usuarios WHERE id = ?', [$novo['id']]));
        Auth::como(null);
        self::assertSame('nova.exec', AuthService::tentarLogin('nova.exec', 'Pessoal2026x', '10.0.0.4')['login']);
        // troca voluntária exige a senha atual
        try {
            AuthService::trocarSenha($novo['id'], 'errada', 'Outra2026xy', 'Outra2026xy', false);
            self::fail('senha atual errada');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('senha_atual', $e->errors);
        }
        // redefinição pelo administrador
        $this->como('teste.admin');
        $prov = UsuarioService::redefinirSenha($novo['id']);
        self::assertSame(1, (int) Database::value('SELECT trocar_senha FROM usuarios WHERE id = ?', [$novo['id']]));
        Auth::como(null);
        self::assertSame(1, (int) AuthService::tentarLogin('nova.exec', $prov, '10.0.0.4')['trocar_senha']);
    }

    public function testRecuperacaoPorTokenDeUsoUnico(): void
    {
        $token = bin2hex(random_bytes(32));
        Database::query('INSERT INTO senha_tokens (usuario_id, token_hash, expira_em) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)', [self::$u['teste.exec'], hash('sha256', $token)]);
        self::assertSame('teste.exec', AuthService::usuarioDoToken($token)['login']);
        AuthService::redefinirComToken($token, 'Recuperada2026', 'Recuperada2026');
        self::assertNull(AuthService::usuarioDoToken($token), 'token de uso único');
        self::assertSame('teste.exec', AuthService::tentarLogin('teste.exec', 'Recuperada2026', '10.0.0.5')['login']);
        $this->expectException(BusinessException::class);
        AuthService::redefinirComToken($token, 'Outra2026xx', 'Outra2026xx');
    }

    public function testRn77UnicoAdministradorNaoPodeSerDesativadoNemRebaixado(): void
    {
        Database::query("UPDATE usuarios SET ativo = 0 WHERE perfil_id = (SELECT id FROM perfis WHERE codigo = 'admin') AND login <> 'teste.admin'");
        $this->como('teste.diretor');
        try {
            UsuarioService::definirAtivo(self::$u['teste.admin'], false);
            self::fail('único admin');
        } catch (BusinessException $e) {
            self::assertStringContainsString('único administrador', $e->getMessage());
        }
        try {
            UsuarioService::atualizar(self::$u['teste.admin'], ['nome' => 'Administrador de Teste', 'login' => 'teste.admin', 'perfil_id' => Database::value("SELECT id FROM perfis WHERE codigo = 'diretor'")]);
            self::fail('rebaixar único admin');
        } catch (BusinessException $e) {
            self::assertStringContainsString('único administrador', $e->getMessage());
        }
    }

    public function testPermissoesPorPerfil(): void
    {
        $esperado = [
            'teste.admin' => ['usuarios.gerenciar' => true, 'permissoes.gerenciar' => true, 'pis.criar' => true],
            'teste.diretor' => ['conferencia.conferir' => true, 'aprovacoes.aprovar_diretoria' => true, 'usuarios.gerenciar' => false, 'pis.ver_todas' => true],
            'teste.gerente' => ['pis.ver_equipe' => true, 'pis.ver_todas' => false, 'aprovacoes.aprovar' => true, 'pis.cancelar' => true, 'projetos.gerenciar' => false],
            'teste.conf' => ['conferencia.conferir' => true, 'pis.criar' => false, 'operacao.finalizar' => true, 'relatorios.ranking_nominal' => true],
            'teste.exec' => ['pis.criar' => true, 'pis.cancelar' => false, 'conferencia.conferir' => false, 'relatorios.ranking_nominal' => false, 'clientes.excluir' => false],
            'teste.vend' => ['pis.criar' => true, 'reservas.criar' => true, 'usuarios.ver' => false],
            'teste.fin' => ['financeiro.faturar' => true, 'pis.criar' => false, 'conferencia.conferir' => false],
        ];
        foreach ($esperado as $login => $perms) {
            $this->como($login);
            foreach ($perms as $p => $v) {
                self::assertSame($v, Auth::can($p), "{$login} · {$p}");
            }
        }
        $this->como('teste.gerente');
        self::assertSame('equipe', Auth::escopoPis());
        self::assertEqualsCanonicalizing([self::$u['teste.exec'], self::$u['teste.exec2'], self::$u['teste.vend']], Auth::equipe());
        $this->como('teste.exec');
        self::assertSame('proprias', Auth::escopoPis());
        $this->como('teste.conf');
        self::assertSame('todas', Auth::escopoPis());
    }

    public function testRn63ClienteUnicoPorDocumentoEExclusaoLogica(): void
    {
        $this->como('teste.exec');
        try {
            ClienteService::salvar(null, ['razao_social' => 'Duplicado', 'documento' => '11222333000181']);
            self::fail('duplicado');
        } catch (ValidationException $e) {
            self::assertStringContainsString('já está cadastrado', $e->errors['documento']);
        }
        try {
            ClienteService::salvar(null, ['razao_social' => 'Inválido', 'documento' => '11.222.333/0001-80']);
            self::fail('inválido');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('documento', $e->errors);
        }
        $id = ClienteService::salvar(null, ['razao_social' => 'Pessoa Física', 'documento' => '52998224725', 'cep' => '65000000', 'uf' => 'ma']);
        $c = Database::first('SELECT * FROM clientes WHERE id = ?', [$id]);
        self::assertSame('529.982.247-25', $c['documento_formatado']);
        self::assertSame('65000-000', $c['cep']);
        self::assertSame('MA', $c['uf']);
        self::assertSame('excluido', ClienteService::excluir($id));
        self::assertNotEmpty(ClienteService::buscar('11.222'), 'busca pelo documento');
        self::assertNotEmpty(ClienteService::buscar('Cliente Teste'), 'busca pela fantasia');
    }

    public function testSessaoComecaVaziaNoCli(): void
    {
        Session::set('x', 1);
        self::assertSame(1, Session::get('x'));
    }
}
