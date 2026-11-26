<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Auth;
use Throwable;

/** Inicialização da aplicação e tratamento central de erros. */
final class App
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function boot(): void
    {
        Env::load(self::root() . '/.env');
        date_default_timezone_set((string) Config::get('app.timezone', 'America/Sao_Paulo'));
        mb_internal_encoding('UTF-8');
        setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'Portuguese_Brazil');
        // Em produção os detalhes técnicos nunca aparecem na tela, mesmo com APP_DEBUG=true por engano
        if (Config::get('app.env') === 'production' && Config::get('app.debug')) {
            Config::set('app.debug', false);
        }
        $debug = (bool) Config::get('app.debug', false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::root() . '/storage/logs/php-errors.log');
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) {
                Logger::warning('Aviso de descontinuação do PHP: ' . $str, ['arquivo' => $file . ':' . $line]);
                return true;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
    }

    public static function installed(): bool
    {
        return is_file(self::root() . '/storage/instalado.lock') || Config::get('app.env') === 'testing';
    }

    public static function handle(Request $request): Response
    {
        try {
            // Assistente de instalação: só abre enquanto não existir storage/instalado.lock (depois responde 404)
            if ($request->path === '/instalar' && Config::get('app.env') !== 'testing') {
                return self::secure(\App\Controllers\InstaladorController::handle($request), $request);
            }
            if (!self::installed()) {
                return Response::html(View::render('erros/nao-instalado', ['titulo' => 'Instalação pendente'], 'simples'), 503);
            }
            Session::start($request);
            $router = new Router();
            (require self::root() . '/routes/web.php')($router);
            $response = $router->dispatch($request);
        } catch (HttpException $e) {
            $response = self::error($request, $e->status, $e->getMessage());
        } catch (ValidationException $e) {
            $response = $request->wantsJson()
                ? Response::json(['ok' => false, 'mensagem' => $e->getMessage(), 'erros' => $e->errors], 422)
                : self::back($request, $e->getMessage(), $e->errors);
        } catch (BusinessException $e) {
            $response = $request->wantsJson()
                ? Response::json(['ok' => false, 'mensagem' => $e->getMessage(), 'codigo' => $e->codigo, 'contexto' => $e->context], 409)
                : self::back($request, $e->getMessage());
        } catch (Throwable $e) {
            $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            Logger::error($e->getMessage(), ['ref' => $ref, 'arquivo' => $e->getFile() . ':' . $e->getLine(), 'rota' => $request->method . ' ' . $request->path, 'usuario' => Auth::id(), 'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12)]);
            $msg = Config::get('app.debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Ocorreu um erro inesperado. Informe ao suporte o código ' . $ref . '.';
            $response = self::error($request, 500, $msg);
        }
        return self::secure($response, $request);
    }

    private static function back(Request $request, string $message, array $errors = []): Response
    {
        Session::withInput($request->all(), $errors);
        Session::flash('error', $message);
        return Response::redirect(self::refererSeguro($request));
    }

    /** Volta para a página anterior só se ela for deste mesmo sistema (evita redirecionar para outro site). */
    public static function refererSeguro(Request $request): string
    {
        $ref = $request->header('Referer');
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($ref === '' || preg_match('/[\r\n]/', $ref)) {
            return '/';
        }
        if ($base !== '') {
            return $ref === $base || str_starts_with($ref, $base . '/') || str_starts_with($ref, $base . '?') ? $ref : '/';
        }
        $host = (string) ($request->server['HTTP_HOST'] ?? '');
        return $host !== '' && strcasecmp((string) parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : ''), $host) === 0 ? $ref : '/';
    }

    private static function error(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'mensagem' => $message], $status);
        }
        if ($status === 419) {
            Session::flash('error', $message);
            return Response::redirect(Auth::check() ? '/' : '/login');
        }
        try {
            return Response::html(View::render('erros/erro', ['status' => $status, 'mensagem' => $message], Auth::check() ? 'app' : 'simples'), $status);
        } catch (Throwable) {
            return Response::html('<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>', $status);
        }
    }

    /** Cabeçalhos de segurança aplicados a todas as respostas. */
    public static function secure(Response $r, Request $request): Response
    {
        $r->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('Referrer-Policy', 'same-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->header('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        if (!isset($r->headers()['Cache-Control'])) {
            $r->header('Cache-Control', 'no-store, private');
        }
        if ($request->isSecure()) {
            $r->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        return $r;
    }
}
