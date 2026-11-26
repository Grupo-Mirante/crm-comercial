<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sessão segura: cookie HttpOnly, SameSite=Lax, Secure em HTTPS, ID regenerado no login,
 * armazenamento no MySQL (tabela sessoes), expiração por inatividade e expiração absoluta (RN-77: no máximo 12 horas).
 */
final class Session
{
    private static bool $started = false;

    public static function start(Request $request, bool $useDatabase = true): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;
            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        if ($useDatabase) {
            session_set_save_handler(new DatabaseSessionHandler($request), true);
        } else {
            $dir = dirname(__DIR__, 2) . '/storage/sessions';
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            session_save_path($dir);
        }
        session_name((string) Config::get('app.session.cookie', 'crm_mirante'));
        session_set_cookie_params([
            'lifetime' => 0, // até fechar o navegador (RN-77)
            'path' => rtrim((string) (parse_url((string) Config::get('app.url', ''), PHP_URL_PATH) ?: ''), '/') . '/',
            'secure' => $request->isSecure() || Config::get('app.env') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) ((int) Config::get('app.session.absolute_minutes', 720) * 60));
        session_start();
        self::$started = true;

        $now = time();
        $idle = (int) Config::get('app.session.idle_minutes', 120) * 60;
        $absolute = (int) Config::get('app.session.absolute_minutes', 720) * 60;
        $created = (int) ($_SESSION['_criada_em'] ?? 0);
        $last = (int) ($_SESSION['_ultimo_uso'] ?? 0);
        if (isset($_SESSION['usuario_id']) && (($idle > 0 && $last && $now - $last > $idle) || ($created && $now - $created > $absolute))) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['warning'] = 'Sua sessão expirou. Entre novamente.';
        }
        $_SESSION['_criada_em'] ??= $now;
        $_SESSION['_ultimo_uso'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Novo ID de sessão (evita fixação de sessão) mantendo os dados. */
    public static function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_criada_em'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** @return array<string,string> */
    public static function pullFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($f) ? $f : [];
    }

    /** @param array<string,mixed> $old @param array<string,string> $errors */
    public static function withInput(array $old, array $errors = []): void
    {
        unset($old['_token'], $old['senha'], $old['senha_atual'], $old['nova_senha'], $old['confirmacao']);
        $_SESSION['_old'] = $old;
        $_SESSION['_errors'] = $errors;
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    public static function pullInput(): array
    {
        $o = $_SESSION['_old'] ?? [];
        $e = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_old'], $_SESSION['_errors']);
        return [is_array($o) ? $o : [], is_array($e) ? $e : []];
    }
}
