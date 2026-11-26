<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Sessões guardadas na tabela sessoes. Permite ao administrador ver quem está conectado
 * e encerrar todas as sessões de um usuário (ao desativar ou redefinir a senha).
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private readonly Request $request)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $row = Database::first('SELECT dados FROM sessoes WHERE id = ? AND ultimo_uso > ?', [hash('sha256', $id), time() - $this->ttl()]);
        return $row ? (string) $row['dados'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $uid = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
        Database::query(
            'INSERT INTO sessoes (id, usuario_id, ip, user_agent, dados, ultimo_uso, criada_em) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), ip = VALUES(ip), user_agent = VALUES(user_agent), dados = VALUES(dados), ultimo_uso = VALUES(ultimo_uso)',
            [hash('sha256', $id), $uid, $this->request->ip(), $this->request->userAgent(), $data, time(), time()]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        Database::query('DELETE FROM sessoes WHERE id = ?', [hash('sha256', $id)]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return Database::query('DELETE FROM sessoes WHERE ultimo_uso < ?', [time() - $this->ttl()])->rowCount();
    }

    public function validateId(string $id): bool
    {
        return (bool) Database::value('SELECT 1 FROM sessoes WHERE id = ? AND ultimo_uso > ?', [hash('sha256', $id), time() - $this->ttl()]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    private function ttl(): int
    {
        return (int) Config::get('app.session.absolute_minutes', 720) * 60;
    }
}
