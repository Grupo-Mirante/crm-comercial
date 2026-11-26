<?php

declare(strict_types=1);

namespace App\Core;

/** Log técnico em storage/logs/app-AAAA-MM-DD.log (erros, avisos). A auditoria de negócio fica no banco. */
final class Logger
{
    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $c */
    public static function error(string $m, array $c = []): void
    {
        self::log('error', $m, $c);
    }

    /** @param array<string,mixed> $c */
    public static function warning(string $m, array $c = []): void
    {
        self::log('warning', $m, $c);
    }

    /** @param array<string,mixed> $c */
    public static function info(string $m, array $c = []): void
    {
        self::log('info', $m, $c);
    }
}
