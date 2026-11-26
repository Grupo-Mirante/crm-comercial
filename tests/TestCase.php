<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Services\Auth;
use App\Services\Configuracao;
use App\Services\Instalacao\SeedTeste;
use PHPUnit\Framework\TestCase as Base;

/**
 * Base dos testes de integração: cada teste roda dentro de uma transação desfeita no final,
 * então o banco de teste volta ao estado inicial entre um teste e outro.
 */
abstract class TestCase extends Base
{
    /** @var array<string,int> */
    protected static array $u = [];

    protected function setUp(): void
    {
        self::$u = SeedTeste::usuarios();
        Configuracao::limparCache();
        Database::connection()->beginTransaction();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $pdo = Database::connection();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Auth::como(null);
        Configuracao::limparCache();
    }

    protected function como(string $login): void
    {
        Auth::como(self::$u[$login]);
    }

    protected function veiculo(string $prefixo): int
    {
        return (int) Database::value('SELECT id FROM veiculos WHERE prefixo = ?', [$prefixo]);
    }

    /**
     * Estado de PI completo e válido (sem pendências), para ser ajustado em cada teste.
     * @param array<string,mixed> $f campos a sobrepor
     * @return array<string,mixed>
     */
    protected function estadoBase(string $prefixo, array $f = []): array
    {
        $tab = ['TV' => 'MA1', 'FM' => 'LOC', 'NEWS' => 'LOC', 'G1' => 'MA', 'IMI' => 'MA'][$prefixo];
        return ['f' => array_merge([
            'veic' => $this->veiculo($prefixo), 'tab' => $tab, 'tipo' => 'Nova', 'exec' => 'Executivo de Teste', 'mat' => 'E001', 'gestor' => 'Gerente de Teste', 'emissao' => date('Y-m-d'), 'venda' => 'Direta',
            'cli_razao' => 'Cliente Teste Comércio Ltda.', 'cli_cnpj' => '11.222.333/0001-81', 'cli_end' => 'Rua de Teste, 100', 'cli_cid' => 'São Luís', 'cli_uf' => 'MA',
            'cli_contato' => 'Contato Teste', 'cli_tel' => '(98) 0000-0001', 'cli_email' => 'financeiro@cliente.teste.invalid',
            'titulo' => 'Campanha de Teste', 'produto' => 'Produto Teste', 'ini' => '2026-12-01', 'fim' => '2026-12-31', 'pcob' => 'São Luís',
            'forma' => 'Boleto', 'ac_nome' => 'Cliente que aceitou', 'ac_data' => date('Y-m-d'),
        ], $f), 'ins' => [], 'pat' => [], 'dig' => [], 'avi' => [], 'prj' => [], 'ent' => [], 'parc' => []];
    }

    /** Uma parcela igual ao líquido (para fechar a pendência D07). @param array<string,mixed> $S */
    protected function fecharParcela(array $S, float $liquido): array
    {
        $S['parc'] = [['venc' => '2027-01-29', 'val' => round($liquido, 2)]];
        return $S;
    }

    /** @param list<array<string,mixed>> $C @return array<string,string> código → situação das verificações que não estão OK */
    protected function pendencias(array $C): array
    {
        $out = [];
        foreach ($C as $c) {
            if ($c['st'] !== 'OK') {
                $out[$c['code'] . ' ' . $c['txt']] = $c['st'];
            }
        }
        return $out;
    }
}
