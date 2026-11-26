<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Avisos dentro do sistema (sino no cabeçalho) e, quando há SMTP, por e-mail. */
final class Notificacoes
{
    /** Cria o aviso; com $chave, não repete o mesmo aviso para o mesmo usuário. */
    public static function enviar(int $usuarioId, string $tipo, string $titulo, string $mensagem = '', string $link = '', ?string $chave = null): void
    {
        $id = (int) Database::value('SELECT id FROM notificacoes WHERE usuario_id = ? AND chave = ?', [$usuarioId, $chave]);
        if ($chave !== null && $id) {
            return;
        }
        $nid = Database::insert('notificacoes', [
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => mb_substr($titulo, 0, 160),
            'mensagem' => mb_substr($mensagem, 0, 600),
            'link' => $link !== '' ? $link : null,
            'chave' => $chave,
        ]);
        if (Mailer::configurado() && Configuracao::booleano('notificar_por_email', true)) {
            $u = Database::first('SELECT nome, email FROM usuarios WHERE id = ? AND ativo = 1', [$usuarioId]);
            if ($u && $u['email']) {
                $texto = $mensagem . ($link !== '' ? "\n\nAbrir no CRM: " . url($link) : '') . "\n\n— CRM Comercial do Grupo Mirante";
                if (Mailer::enviar((string) $u['email'], (string) $u['nome'], $titulo, $texto)) {
                    Database::query('UPDATE notificacoes SET email_enviado_em = NOW() WHERE id = ?', [$nid]);
                }
            }
        }
    }

    /** @param list<int> $ids */
    public static function enviarVarios(array $ids, string $tipo, string $titulo, string $mensagem = '', string $link = '', ?string $chave = null): void
    {
        foreach (array_unique($ids) as $id) {
            self::enviar((int) $id, $tipo, $titulo, $mensagem, $link, $chave);
        }
    }

    /** @return list<int> usuários ativos com a permissão informada */
    public static function usuariosComPermissao(string $permissao): array
    {
        [$mod, $acao] = explode('.', $permissao, 2);
        return array_map('intval', array_column(Database::all(
            'SELECT DISTINCT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
              LEFT JOIN perfil_permissoes pp ON pp.perfil_id = p.id LEFT JOIN permissoes m ON m.id = pp.permissao_id
              WHERE u.ativo = 1 AND (p.codigo = \'admin\' OR (m.modulo = ? AND m.acao = ?))',
            [$mod, $acao]
        ), 'id'));
    }

    /** @return list<int> usuários ativos do perfil (ex.: 'conf' = Conferência/OPEC) */
    public static function usuariosDoPerfil(string $codigo): array
    {
        return array_map('intval', array_column(Database::all(
            'SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.ativo = 1 AND p.codigo = ?',
            [$codigo]
        ), 'id'));
    }

    public static function naoLidas(int $usuarioId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL', [$usuarioId]);
    }
}
