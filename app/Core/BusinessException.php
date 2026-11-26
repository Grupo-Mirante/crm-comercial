<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Regra de negócio violada (ex.: cota esgotada, PI bloqueada). A mensagem explica ao usuário como corrigir. */
class BusinessException extends RuntimeException
{
    /** @param array<string,mixed> $context */
    public function __construct(string $message, public readonly string $codigo = '', public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
