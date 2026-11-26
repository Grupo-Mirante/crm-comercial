<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\ValidationException;

/**
 * Cadastro de usuários.
 *  RN-75  só o administrador cadastra; usuário novo e senha redefinida recebem senha provisória, mostrada uma vez
 *  RN-76  nome, matrícula e gestor do executivo vêm daqui (não podem ser trocados na PI)
 *  RN-77  não se desativa nem se rebaixa o único administrador ativo
 */
final class UsuarioService
{
    /**
     * @param array<string,mixed> $dados
     * @return array{id:int,senha:string}
     */
    public static function criar(array $dados): array
    {
        $d = self::validar($dados, null);
        $senha = AuthService::senhaProvisoria();
        $id = Database::insert('usuarios', $d + [
            'senha_hash' => AuthService::hash($senha),
            'trocar_senha' => 1,
            'ativo' => 1,
            'created_by' => Auth::id(),
        ]);
        Auditoria::registrar('usuario_criado', 'usuario', $id, 'Usuário ' . $d['login'] . ' criado', null, $d);
        return ['id' => $id, 'senha' => $senha];
    }

    /** @param array<string,mixed> $dados */
    public static function atualizar(int $id, array $dados): void
    {
        $antes = Database::first('SELECT nome, login, email, matricula, telefone, perfil_id, gestor_id, ativo FROM usuarios WHERE id = ?', [$id]);
        if (!$antes) {
            throw new BusinessException('Usuário não encontrado.');
        }
        $d = self::validar($dados, $id);
        if ((int) $antes['perfil_id'] === self::perfilAdminId() && (int) $d['perfil_id'] !== self::perfilAdminId() && self::adminsAtivos($id) === 0) {
            throw new BusinessException('Este é o único administrador ativo: cadastre outro administrador antes de mudar o perfil dele.');
        }
        $d['updated_by'] = Auth::id();
        Database::update('usuarios', $d, ['id' => $id]);
        Auditoria::registrar('usuario_alterado', 'usuario', $id, 'Usuário ' . $d['login'] . ' alterado', $antes, $d);
    }

    public static function definirAtivo(int $id, bool $ativo): void
    {
        $u = Database::first('SELECT id, login, perfil_id, ativo FROM usuarios WHERE id = ?', [$id]);
        if (!$u) {
            throw new BusinessException('Usuário não encontrado.');
        }
        if (!$ativo && (int) $u['perfil_id'] === self::perfilAdminId() && self::adminsAtivos($id) === 0) {
            throw new BusinessException('Não é possível desativar o único administrador ativo.');
        }
        if (!$ativo && $id === Auth::id()) {
            throw new BusinessException('Você não pode desativar o próprio usuário.');
        }
        Database::query('UPDATE usuarios SET ativo = ?, updated_by = ? WHERE id = ?', [$ativo ? 1 : 0, Auth::id(), $id]);
        if (!$ativo) {
            Database::query('DELETE FROM sessoes WHERE usuario_id = ?', [$id]);
        }
        Auditoria::registrar($ativo ? 'usuario_ativado' : 'usuario_desativado', 'usuario', $id, ($ativo ? 'Ativou ' : 'Desativou ') . $u['login'], ['ativo' => (int) $u['ativo']], ['ativo' => $ativo ? 1 : 0]);
    }

    /** RN-75 — gera senha provisória (mostrada uma vez) e obriga a troca no próximo acesso. */
    public static function redefinirSenha(int $id): string
    {
        $u = Database::first('SELECT id, login FROM usuarios WHERE id = ?', [$id]);
        if (!$u) {
            throw new BusinessException('Usuário não encontrado.');
        }
        $senha = AuthService::senhaProvisoria();
        Database::query('UPDATE usuarios SET senha_hash = ?, trocar_senha = 1, senha_alterada_em = NOW(), falhas_consecutivas = 0, bloqueado_ate = NULL, updated_by = ? WHERE id = ?', [AuthService::hash($senha), Auth::id(), $id]);
        Database::query('DELETE FROM sessoes WHERE usuario_id = ?', [$id]);
        Auditoria::registrar('senha_redefinida_admin', 'usuario', $id, 'Senha provisória gerada para ' . $u['login']);
        return $senha;
    }

    public static function perfilAdminId(): int
    {
        return (int) Database::value("SELECT id FROM perfis WHERE codigo = 'admin'");
    }

    /** Administradores ativos além do informado. */
    private static function adminsAtivos(int $exceto): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM usuarios WHERE perfil_id = ? AND ativo = 1 AND id <> ?', [self::perfilAdminId(), $exceto]);
    }

    /** @param array<string,mixed> $dados @return array<string,mixed> */
    private static function validar(array $dados, ?int $id): array
    {
        $d = [
            'nome' => trim((string) ($dados['nome'] ?? '')),
            'login' => mb_strtolower(trim((string) ($dados['login'] ?? ''))),
            'email' => mb_strtolower(trim((string) ($dados['email'] ?? ''))) ?: null,
            'matricula' => trim((string) ($dados['matricula'] ?? '')) ?: null,
            'telefone' => trim((string) ($dados['telefone'] ?? '')) ?: null,
            'perfil_id' => (int) ($dados['perfil_id'] ?? 0),
            'gestor_id' => ($dados['gestor_id'] ?? '') !== '' ? (int) $dados['gestor_id'] : null,
        ];
        $e = [];
        if (mb_strlen($d['nome']) < 3) {
            $e['nome'] = 'Informe o nome completo.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $d['login'])) {
            $e['login'] = 'Use de 3 a 60 caracteres: letras minúsculas, números, ponto, hífen ou sublinhado.';
        } elseif (Database::value('SELECT id FROM usuarios WHERE login = ? AND id <> ?', [$d['login'], $id ?? 0])) {
            $e['login'] = 'Este nome de usuário já existe.';
        }
        if ($d['email'] !== null) {
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $e['email'] = 'E-mail inválido.';
            } elseif (Database::value('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$d['email'], $id ?? 0])) {
                $e['email'] = 'Este e-mail já está em outro usuário.';
            }
        }
        $perfil = Database::first('SELECT codigo FROM perfis WHERE id = ?', [$d['perfil_id']]);
        if (!$perfil) {
            $e['perfil_id'] = 'Escolha o perfil.';
        } elseif (in_array($perfil['codigo'], ['exec', 'vend'], true)) {
            if ($d['matricula'] === null) {
                $e['matricula'] = 'Executivo e vendedor precisam de matrícula (sai na PI).';
            }
            if ($d['gestor_id'] === null) {
                $e['gestor_id'] = 'Executivo e vendedor precisam de gestor comercial (sai na PI).';
            }
        }
        if ($d['gestor_id'] !== null) {
            if ($d['gestor_id'] === $id) {
                $e['gestor_id'] = 'O usuário não pode ser gestor de si mesmo.';
            } else {
                $g = Database::first("SELECT p.codigo FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.id = ? AND u.ativo = 1", [$d['gestor_id']]);
                if (!$g || !in_array($g['codigo'], ['gerente', 'diretor', 'admin'], true)) {
                    $e['gestor_id'] = 'O gestor precisa ser um gerente comercial, diretor ou administrador ativo.';
                }
            }
        }
        if ($e) {
            throw new ValidationException($e);
        }
        return $d;
    }
}
