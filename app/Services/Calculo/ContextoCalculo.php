<?php

declare(strict_types=1);

namespace App\Services\Calculo;

use Closure;

/**
 * Dados externos de que o cálculo precisa, além do formulário:
 *  - catálogo do veículo (preços);
 *  - projetos especiais e cotas ocupadas (vendidas por outras PIs + reservadas por outros vendedores — RN-36/RN-66);
 *  - verificação da aprovação do gestor (RN-41) registrada no sistema;
 *  - data de hoje (RN-33: o prazo do projeto é comparado com hoje, não com a emissão).
 */
final class ContextoCalculo
{
    /**
     * @param array<int,array<string,mixed>> $projetos projetos ativos por id, com 'veiculos' (ids) e 'cotas' (id, nome, valor, quantidade_total)
     * @param Closure(int):int|null $ocupadas cotas já vendidas/reservadas por terceiros, por id da cota
     * @param Closure(array<string,mixed>,list<array<string,mixed>>):array{ok:bool,nome:?string,data:?string,situacao:string}|null $aprovacao
     */
    public function __construct(
        public readonly Catalogo $catalogo,
        public readonly array $projetos = [],
        public readonly ?Closure $ocupadas = null,
        public readonly ?Closure $aprovacao = null,
        public readonly ?string $hoje = null,
    ) {
    }

    public function hoje(): string
    {
        return $this->hoje ?? date('Y-m-d');
    }

    public function ocupadas(int $cotaId): int
    {
        return $this->ocupadas ? ($this->ocupadas)($cotaId) : 0;
    }
}
