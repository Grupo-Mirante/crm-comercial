<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Erros de validação por campo, exibidos ao lado de cada campo do formulário. */
class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Confira os campos destacados.')
    {
        parent::__construct($message);
    }
}
