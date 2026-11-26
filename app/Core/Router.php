<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\Autenticado;
use App\Middleware\Convidado;
use App\Middleware\Permissao;
use App\Middleware\VerificaCsrf;

/**
 * Roteador simples. Cada rota declara as camadas de proteção:
 *   'auth'        usuário autenticado (e senha definitiva — primeiro acesso força a troca)
 *   'guest'       apenas não autenticado (login, recuperação de senha)
 *   'perm:mod.acao'  permissão exigida (conferida no servidor em toda requisição)
 * Toda requisição que altera dados passa pela verificação de CSRF.
 */
final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,handler:array{0:class-string,1:string},mw:list<string>}> */
    private array $routes = [];
    /** @var list<string> */
    private array $groupMw = [];
    private string $groupPrefix = '';
    /** @var list<string> caminhos GET registrados (o menu só mostra telas que existem) */
    public static array $caminhos = [];

    /** @param list<string> $mw */
    public function get(string $path, array $handler, array $mw = []): void
    {
        $this->add('GET', $path, $handler, $mw);
    }

    /** @param list<string> $mw */
    public function post(string $path, array $handler, array $mw = []): void
    {
        $this->add('POST', $path, $handler, $mw);
    }

    /** @param list<string> $mw */
    public function put(string $path, array $handler, array $mw = []): void
    {
        $this->add('PUT', $path, $handler, $mw);
    }

    /** @param list<string> $mw */
    public function delete(string $path, array $handler, array $mw = []): void
    {
        $this->add('DELETE', $path, $handler, $mw);
    }

    /** @param list<string> $mw @param callable(Router):void $fn */
    public function group(string $prefix, array $mw, callable $fn): void
    {
        [$p, $m] = [$this->groupPrefix, $this->groupMw];
        $this->groupPrefix .= $prefix;
        $this->groupMw = array_merge($this->groupMw, $mw);
        $fn($this);
        [$this->groupPrefix, $this->groupMw] = [$p, $m];
    }

    /** @param array{0:class-string,1:string} $handler @param list<string> $mw */
    private function add(string $method, string $path, array $handler, array $mw): void
    {
        $pattern = '/' . trim($this->groupPrefix . $path, '/');
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if ($method === 'GET') {
            self::$caminhos[] = $pattern;
        }
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'regex' => $regex, 'handler' => $handler, 'mw' => array_merge($this->groupMw, $mw)];
    }

    /** @return list<array{method:string,pattern:string,mw:list<string>}> rotas registradas (usado na revisão de segurança automatizada) */
    public function rotas(): array
    {
        return array_map(fn ($r) => ['method' => $r['method'], 'pattern' => $r['pattern'], 'mw' => $r['mw']], $this->routes);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method && !($route['method'] === 'GET' && $request->method === 'HEAD')) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $this->run($route, $request);
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    /** @param array{method:string,pattern:string,regex:string,handler:array{0:class-string,1:string},mw:list<string>} $route */
    private function run(array $route, Request $request): Response
    {
        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            (new VerificaCsrf())->handle($request);
        }
        foreach ($route['mw'] as $mw) {
            $result = match (true) {
                $mw === 'auth' => (new Autenticado())->handle($request),
                $mw === 'guest' => (new Convidado())->handle($request),
                str_starts_with($mw, 'perm:') => (new Permissao(substr($mw, 5)))->handle($request),
                default => throw new \LogicException('Middleware desconhecido: ' . $mw),
            };
            if ($result instanceof Response) {
                return $result;
            }
        }
        [$class, $action] = $route['handler'];
        $controller = new $class();
        $response = $controller->$action($request);
        return $response instanceof Response ? $response : Response::html((string) $response);
    }

    /** @return list<array{method:string,pattern:string,mw:list<string>}> */
    public function list(): array
    {
        return array_map(fn ($r) => ['method' => $r['method'], 'pattern' => $r['pattern'], 'mw' => $r['mw']], $this->routes);
    }
}
