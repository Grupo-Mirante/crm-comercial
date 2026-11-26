<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renderização de templates PHP em resources/views. Toda saída de dado deve passar por e() (proteção contra XSS).
 * Layout: resources/layouts/{layout}.php recebe $content e as mesmas variáveis da view.
 */
final class View
{
    /** @var array<string,mixed> dados globais (usuário logado, menu, notificações) */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'app'): string
    {
        $base = dirname(__DIR__, 2) . '/resources';
        $content = self::capture($base . '/views/' . self::safe($view) . '.php', array_merge(self::$shared, $data));
        if ($layout === null) {
            return $content;
        }
        return self::capture($base . '/layouts/' . self::safe($layout) . '.php', array_merge(self::$shared, $data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture(dirname(__DIR__, 2) . '/resources/views/' . self::safe($view) . '.php', array_merge(self::$shared, $data));
    }

    /** @param array<string,mixed> $__data */
    private static function capture(string $__file, array $__data): string
    {
        if (!is_file($__file)) {
            throw new \RuntimeException('View não encontrada: ' . basename($__file));
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private static function safe(string $name): string
    {
        if (!preg_match('#^[a-z0-9_\-/]+$#', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Nome de view inválido.');
        }
        return $name;
    }
}
