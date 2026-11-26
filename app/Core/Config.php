<?php

declare(strict_types=1);

namespace App\Core;

/** Acesso às configurações de config/*.php com notação de ponto: config('app.timezone'). */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!array_key_exists($file, self::$items)) {
            $path = dirname(__DIR__, 2) . '/config/' . basename($file) . '.php';
            self::$items[$file] = is_file($path) ? require $path : [];
        }
        $value = self::$items[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        self::get($file);
        $ref = &self::$items[$file];
        foreach ($parts as $part) {
            if (!is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }
}
