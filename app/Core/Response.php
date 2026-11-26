<?php

declare(strict_types=1);

namespace App\Core;

/** Resposta HTTP: HTML, JSON, redirecionamento ou arquivo. */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];
    private ?string $file = null;
    private bool $deleteAfter = false;

    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return (new self($body, $status))->header('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $url = preg_match('#^https?://#', $to) ? $to : url($to);
        return (new self('', $status))->header('Location', $url);
    }

    public static function download(string $path, string $filename, string $mime = 'application/octet-stream', bool $deleteAfter = false, bool $inline = false): self
    {
        $r = new self('', 200);
        $r->file = $path;
        $r->deleteAfter = $deleteAfter;
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'arquivo';
        return $r->header('Content-Type', $mime)
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($filename))
            ->header('Content-Length', (string) filesize($path))
            ->header('Cache-Control', 'private, no-store');
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        if ($this->file !== null) {
            readfile($this->file);
            if ($this->deleteAfter) {
                @unlink($this->file);
            }
            return;
        }
        echo $this->body;
    }
}
