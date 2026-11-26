<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Erro HTTP com status e mensagem amigável para o usuário. */
class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : match ($status) {
            400 => 'Requisição inválida.',
            403 => 'Você não tem permissão para esta ação.',
            404 => 'Página não encontrada.',
            405 => 'Método não permitido.',
            419 => 'A página expirou. Recarregue e tente de novo.',
            429 => 'Muitas tentativas. Aguarde alguns segundos.',
            default => 'Erro interno.',
        }, $status);
    }
}
