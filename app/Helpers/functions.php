<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Services\Auth;

if (!function_exists('e')) {
    /** Escapa texto para HTML (proteção contra XSS). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** URL absoluta dentro da aplicação. */
function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) Config::get('app.url', ''), '/');
    $u = $base . '/' . ltrim($path, '/');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

/** URL de arquivo estático com versão (evita cache antigo após atualização). */
function asset(string $path): string
{
    $file = dirname(__DIR__, 2) . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

/** Valor em reais: 1234.5 → "R$ 1.234,50". */
function moeda(float|int|string|null $v, bool $simbolo = true): string
{
    $s = number_format((float) ($v ?? 0), 2, ',', '.');
    return $simbolo ? 'R$ ' . $s : $s;
}

/** Número no padrão brasileiro com casas decimais variáveis. */
function numero(float|int|string|null $v, int $casas = 0): string
{
    return number_format((float) ($v ?? 0), $casas, ',', '.');
}

/** Data ISO (AAAA-MM-DD) ou DateTime → DD/MM/AAAA. */
function data_br(mixed $d): string
{
    if ($d instanceof DateTimeInterface) {
        return $d->format('d/m/Y');
    }
    $s = (string) ($d ?? '');
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $s;
}

/** Data e hora → DD/MM/AAAA HH:MM. */
function data_hora_br(mixed $d): string
{
    $s = (string) ($d ?? '');
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $s, $m)) {
        return "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}:{$m[5]}";
    }
    return data_br($s);
}

/** DD/MM/AAAA → AAAA-MM-DD (ou string vazia se inválida). */
function data_iso(?string $br): string
{
    $s = trim((string) $br);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s;
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return '';
}

function hoje(): string
{
    return date('Y-m-d');
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

/** Valor antigo do formulário (após erro de validação). */
function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['__old'][$key] ?? $default;
}

/** Mensagem de erro de validação do campo. */
function erro(string $key): string
{
    $m = $GLOBALS['__errors'][$key] ?? '';
    return $m !== '' ? '<div class="invalid-feedback d-block">' . e($m) . '</div>' : '';
}

function tem_erro(string $key): string
{
    return isset($GLOBALS['__errors'][$key]) ? ' is-invalid' : '';
}

/** Remove acentos (mapa fixo, sem depender de iconv/intl do servidor). */
function sem_acento(string $s): string
{
    static $map = null;
    $map ??= array_combine(
        mb_str_split('ÀÁÂÃÄÅàáâãäåÈÉÊËèéêëÌÍÎÏìíîïÒÓÔÕÖòóôõöÙÚÛÜùúûüÇçÑñÝýÿ'),
        str_split('AAAAAAaaaaaaEEEEeeeeIIIIiiiiOOOOOoooooUUUUuuuuCcNnYyy')
    );
    return strtr($s, $map);
}

/** Remove acentos e símbolos (nomes de arquivo). */
function slug_arquivo(string $s, int $max = 30): string
{
    $t = preg_replace('/[^A-Za-z0-9]+/', '_', sem_acento($s)) ?? '';
    return substr(trim($t, '_'), 0, $max) ?: 'cliente';
}

/** Normaliza texto para busca (minúsculas, sem acento). */
function normaliza(string $s): string
{
    return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', sem_acento($s))));
}

/** Converte valor digitado ("1.234,56", "1234.56", "R$ 10") em número. */
function valor_decimal(mixed $v): float
{
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    $s = preg_replace('/[^\d,.\-]/', '', (string) ($v ?? '')) ?? '';
    if (str_contains($s, ',')) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    }
    return is_numeric($s) ? (float) $s : 0.0;
}

/** Selo colorido da situação (PI, cliente, projeto): <span class="st st-...">. */
function selo(?string $situacao): string
{
    $s = (string) $situacao;
    $cls = trim((string) preg_replace('/[^a-z0-9]+/', '-', normaliza($s)), '-');
    return '<span class="st st-' . e($cls) . '">' . e($s) . '</span>';
}
