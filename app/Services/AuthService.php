<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\Session;
use App\Core\ValidationException;

/**
 * Login, bloqueio por tentativas, troca e recuperação de senha.
 *  RN-71  login obrigatório; senha provisória obriga a criar a senha pessoal
 *  RN-75  senha pessoal com tamanho mínimo; senha provisória mostrada uma vez ao administrador
 *  RN-77  usuário desativado não entra; N senhas erradas seguidas bloqueiam por alguns segundos
 *  RN-78  senhas guardadas com hash (Argon2id), nunca em texto
 */
final class AuthService
{
    public static function algoritmo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $senha): string
    {
        return password_hash($senha, self::algoritmo());
    }

    /**
     * @return array<string,mixed> usuário autenticado
     * @throws BusinessException com mensagem pronta para a tela
     */
    public static function tentarLogin(string $login, string $senha, string $ip): array
    {
        $login = mb_strtolower(trim($login));
        if ($login === '' || $senha === '') {
            throw new BusinessException('Informe usuário e senha.');
        }
        $limiteIp = Configuracao::inteiro('login_max_tentativas_ip_15min', 30);
        $falhasIp = (int) Database::value('SELECT COUNT(*) FROM login_tentativas WHERE ip = ? AND sucesso = 0 AND criado_em > (NOW() - INTERVAL 15 MINUTE)', [$ip]);
        if ($limiteIp > 0 && $falhasIp >= $limiteIp) {
            self::registrarTentativa($login, $ip, false, 'ip_bloqueado');
            throw new BusinessException('Muitas tentativas a partir deste computador. Aguarde 15 minutos e tente de novo.', 'login_ip');
        }
        $u = Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]);
        if ($u && $u['bloqueado_ate'] !== null && strtotime((string) $u['bloqueado_ate']) > time()) {
            $s = strtotime((string) $u['bloqueado_ate']) - time();
            self::registrarTentativa($login, $ip, false, 'bloqueado');
            throw new BusinessException("Muitas tentativas. Aguarde {$s} s.", 'login_bloqueado');
        }
        // Mesmo sem usuário, calcula um hash para não revelar pela demora quais logins existem.
        static $falso = null;
        $falso ??= self::hash(bin2hex(random_bytes(8)));
        $ok = password_verify($senha, $u ? (string) $u['senha_hash'] : $falso) && $u !== null;
        if (!$u || !$ok) {
            if ($u) {
                $max = Configuracao::inteiro('login_max_tentativas', 5);
                $falhas = (int) $u['falhas_consecutivas'] + 1;
                if ($falhas >= $max) {
                    Database::query('UPDATE usuarios SET falhas_consecutivas = 0, bloqueado_ate = (NOW() + INTERVAL ? SECOND) WHERE id = ?', [Configuracao::inteiro('login_bloqueio_segundos', 30), (int) $u['id']]);
                    Auditoria::registrar('login_bloqueado', 'usuario', (int) $u['id'], "Login bloqueado após {$max} senhas erradas", null, null, (int) $u['id'], (string) $u['nome']);
                } else {
                    Database::query('UPDATE usuarios SET falhas_consecutivas = ? WHERE id = ?', [$falhas, (int) $u['id']]);
                }
            }
            self::registrarTentativa($login, $ip, false, $u ? 'senha' : 'usuario');
            throw new BusinessException('Usuário ou senha incorretos.', 'login_invalido');
        }
        if ((int) $u['ativo'] !== 1) {
            self::registrarTentativa($login, $ip, false, 'inativo');
            throw new BusinessException('Usuário desativado. Fale com o administrador.', 'login_inativo');
        }
        if (password_needs_rehash((string) $u['senha_hash'], self::algoritmo())) {
            Database::query('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [self::hash($senha), (int) $u['id']]);
        }
        Database::query('UPDATE usuarios SET falhas_consecutivas = 0, bloqueado_ate = NULL, ultimo_acesso_em = NOW() WHERE id = ?', [(int) $u['id']]);
        self::registrarTentativa($login, $ip, true, null);
        Auth::entrar($u);
        Auditoria::registrar('login', 'usuario', (int) $u['id'], 'Entrou no sistema', null, null, (int) $u['id'], (string) $u['nome']);
        return $u;
    }

    private static function registrarTentativa(string $login, string $ip, bool $ok, ?string $motivo): void
    {
        Database::insert('login_tentativas', ['login' => mb_substr($login, 0, 60), 'ip' => $ip, 'sucesso' => $ok ? 1 : 0, 'motivo' => $motivo]);
    }

    public static function sair(): void
    {
        $u = Auth::user();
        if ($u) {
            Auditoria::registrar('logout', 'usuario', (int) $u['id'], 'Saiu do sistema');
        }
        Auth::sair();
    }

    /**
     * RN-75 — política da senha pessoal.
     * @return string|null mensagem de erro ou null se a senha é aceita
     */
    public static function validarSenha(string $senha, string $login, int $minimo = 0): ?string
    {
        try {
            $cfg = Configuracao::inteiro('senha_tamanho_minimo', 8);
        } catch (\Throwable) {
            $cfg = 8; // durante a instalação a tabela de configurações ainda não existe
        }
        $min = max($minimo, $cfg, 8);
        if (mb_strlen($senha) < $min) {
            return "A senha precisa de pelo menos {$min} caracteres.";
        }
        if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
            return 'Use letras e números na senha.';
        }
        $baixa = mb_strtolower($senha);
        if ($baixa === mb_strtolower($login) || str_contains($baixa, mb_strtolower($login)) && mb_strlen($login) >= 4) {
            return 'A senha não pode conter o nome de usuário.';
        }
        if (in_array($baixa, ['admin123', 'senha123', '12345678a', 'mirante123', 'password1', 'grupomirante1'], true) || preg_match('/^(.)\1+$/', $senha)) {
            return 'Essa senha é fácil de adivinhar. Escolha outra.';
        }
        return null;
    }

    /** Administradores usam senha de no mínimo 12 caracteres (o mesmo exigido no instalador); os demais, o mínimo configurado. */
    public const MINIMO_ADMIN = 12;

    public static function minimoPara(int $usuarioId): int
    {
        try {
            $cfg = Configuracao::inteiro('senha_tamanho_minimo', 8);
        } catch (\Throwable) {
            $cfg = 8;
        }
        $admin = Database::value('SELECT p.codigo FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.id = ?', [$usuarioId]) === 'admin';
        return max(8, $cfg, $admin ? self::MINIMO_ADMIN : 0);
    }

    /** Troca da própria senha (primeiro acesso ou voluntária). */
    public static function trocarSenha(int $usuarioId, ?string $atual, string $nova, string $confirmacao, bool $obrigatoria): void
    {
        $u = Database::first('SELECT * FROM usuarios WHERE id = ?', [$usuarioId]);
        if (!$u) {
            throw new BusinessException('Usuário não encontrado.');
        }
        $erros = [];
        if (!$obrigatoria && !password_verify((string) $atual, (string) $u['senha_hash'])) {
            $erros['senha_atual'] = 'Senha atual incorreta.';
        }
        if ($m = self::validarSenha($nova, (string) $u['login'], self::minimoPara((int) $u['id']))) {
            $erros['nova_senha'] = $m;
        } elseif (password_verify($nova, (string) $u['senha_hash'])) {
            $erros['nova_senha'] = 'A nova senha precisa ser diferente da atual.';
        }
        if ($nova !== $confirmacao) {
            $erros['confirmacao'] = 'As duas senhas não são iguais.';
        }
        if ($erros) {
            throw new ValidationException($erros);
        }
        Database::query('UPDATE usuarios SET senha_hash = ?, trocar_senha = 0, senha_alterada_em = NOW() WHERE id = ?', [self::hash($nova), $usuarioId]);
        Database::query('DELETE FROM sessoes WHERE usuario_id = ? AND id <> ?', [$usuarioId, self::sessaoAtualHash()]);
        Database::query('UPDATE senha_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL', [$usuarioId]);
        if (Session::get('usuario_id') === $usuarioId) {
            Session::regenerate();
            Session::set('usuario_versao', Auth::versao($usuarioId));
        }
        Auth::reset();
        Auditoria::registrar('senha_alterada', 'usuario', $usuarioId, $obrigatoria ? 'Senha pessoal criada no primeiro acesso' : 'Senha alterada pelo usuário', null, null, $usuarioId, (string) $u['nome']);
    }

    private static function sessaoAtualHash(): string
    {
        return PHP_SAPI === 'cli' || session_status() !== PHP_SESSION_ACTIVE ? '' : hash('sha256', (string) session_id());
    }

    /** Senha provisória legível (sem caracteres ambíguos), como no Gerador original. */
    public static function senhaProvisoria(): string
    {
        $a = 'abcdefghjkmnpqrstuvwxyz';
        $n = '23456789';
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= $a[random_int(0, strlen($a) - 1)];
        }
        $s .= $n[random_int(0, strlen($n) - 1)] . $n[random_int(0, strlen($n) - 1)];
        return str_shuffle($s);
    }

    /**
     * Recuperação de senha por e-mail: cria token de uso único e envia o link.
     * Resposta sempre igual (não revela se o e-mail existe).
     */
    public static function solicitarRecuperacao(string $email, string $ip): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !Mailer::configurado()) {
            return;
        }
        $recentes = (int) Database::value('SELECT COUNT(*) FROM senha_tokens WHERE ip = ? AND criado_em > (NOW() - INTERVAL 15 MINUTE)', [$ip]);
        if ($recentes >= 5) {
            return;
        }
        $u = Database::first('SELECT id, nome, email, ativo FROM usuarios WHERE email = ?', [$email]);
        if (!$u || (int) $u['ativo'] !== 1) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $min = Configuracao::inteiro('senha_token_minutos', 60);
        Database::query('UPDATE senha_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL', [(int) $u['id']]);
        Database::query('INSERT INTO senha_tokens (usuario_id, token_hash, expira_em, ip) VALUES (?, ?, NOW() + INTERVAL ? MINUTE, ?)', [(int) $u['id'], hash('sha256', $token), $min, $ip]);
        $link = url('/redefinir-senha/' . $token);
        Mailer::enviar((string) $u['email'], (string) $u['nome'], 'Redefinição de senha — CRM Grupo Mirante',
            "Olá, {$u['nome']}.\n\nRecebemos um pedido para redefinir sua senha do CRM Comercial do Grupo Mirante.\n"
            . "Use o link abaixo em até {$min} minutos:\n\n{$link}\n\nSe você não pediu, ignore esta mensagem: sua senha continua a mesma.");
        Auditoria::registrar('senha_recuperacao_solicitada', 'usuario', (int) $u['id'], 'Link de redefinição enviado por e-mail', null, null, (int) $u['id'], (string) $u['nome']);
    }

    /** @return array<string,mixed>|null usuário dono do token válido */
    public static function usuarioDoToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::first(
            'SELECT u.id, u.nome, u.login, t.id AS token_id FROM senha_tokens t JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.token_hash = ? AND t.usado_em IS NULL AND t.expira_em > NOW() AND u.ativo = 1',
            [hash('sha256', $token)]
        );
    }

    public static function redefinirComToken(string $token, string $nova, string $confirmacao): void
    {
        $u = self::usuarioDoToken($token);
        if (!$u) {
            throw new BusinessException('Link inválido ou expirado. Peça um novo link de redefinição.');
        }
        $erros = [];
        if ($m = self::validarSenha($nova, (string) $u['login'], self::minimoPara((int) $u['id']))) {
            $erros['nova_senha'] = $m;
        }
        if ($nova !== $confirmacao) {
            $erros['confirmacao'] = 'As duas senhas não são iguais.';
        }
        if ($erros) {
            throw new ValidationException($erros);
        }
        Database::transaction(function () use ($u, $nova) {
            Database::query('UPDATE usuarios SET senha_hash = ?, trocar_senha = 0, senha_alterada_em = NOW(), falhas_consecutivas = 0, bloqueado_ate = NULL WHERE id = ?', [self::hash($nova), (int) $u['id']]);
            Database::query('UPDATE senha_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL', [(int) $u['id']]);
            Database::query('DELETE FROM sessoes WHERE usuario_id = ?', [(int) $u['id']]);
        });
        Auditoria::registrar('senha_redefinida_link', 'usuario', (int) $u['id'], 'Senha redefinida pelo link de recuperação', null, null, (int) $u['id'], (string) $u['nome']);
    }
}
