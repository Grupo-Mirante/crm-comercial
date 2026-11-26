<?php

declare(strict_types=1);

namespace App\Core;

/** Requisição HTTP normalizada (método, caminho relativo à base da aplicação, entradas e arquivos). */
final class Request
{
    /** @var array<string,string> parâmetros capturados na rota ({id}) */
    public array $params = [];
    /** @var array<string,mixed> */
    private array $json = [];

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     * @param array<string,mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
        string $rawBody = ''
    ) {
        $ct = strtolower((string) ($server['CONTENT_TYPE'] ?? ''));
        if ($rawBody !== '' && str_contains($ct, 'application/json')) {
            $decoded = json_decode($rawBody, true, 64);
            $this->json = is_array($decoded) ? $decoded : [];
        }
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($_POST['_method']) && in_array(strtoupper((string) $_POST['_method']), ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = strtoupper((string) $_POST['_method']);
        }
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = rtrim((string) parse_url((string) Config::get('app.url', ''), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $script = dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== '/' && $script !== '.' && $script !== '' && str_starts_with($uri, $script)) {
            $uri = substr($uri, strlen($script));
        }
        $path = '/' . trim(rawurldecode($uri), '/');
        $raw = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? (string) file_get_contents('php://input') : '';
        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER, $raw);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function param(string $key): string
    {
        return $this->params[$key] ?? '';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) ($this->server['HTTP_ACCEPT'] ?? ''));
        return str_contains($accept, 'application/json')
            || strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_starts_with($this->path, '/api/');
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && Config::get('app.trust_proxy'));
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($this->server[$key] ?? '');
    }
}
