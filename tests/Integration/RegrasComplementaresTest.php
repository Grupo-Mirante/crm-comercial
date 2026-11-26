<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\Calculo\CalculadoraPi;
use App\Services\Calculo\Catalogo;
use App\Services\Calculo\ContextoCalculo;
use App\Services\DashboardService;
use App\Services\PiService;
use App\Services\PiWorkflow;
use App\Services\ProjetoService;
use Tests\TestCase;

/** Fase 6 — regras que a matriz RN → teste ainda não cobria com um teste dedicado. */
final class RegrasComplementaresTest extends TestCase
{
    private function calc(array $S): array
    {
        return (new CalculadoraPi(new ContextoCalculo(new Catalogo())))->calcular($S);
    }

    /** RN-14: formato digital "sob consulta" (sem preço no mídia kit) só com preço manual aprovado. */
    public function testRn14DigitalSobConsultaExigePrecoManual(): void
    {
        $S = $this->estadoBase('IMI');
        $S['dig'] = [['s' => 'IESP', 'qtd' => 1]];
        $R = $this->calc($S);
        self::assertTrue($R['dig'][0]['semPreco'], 'IESP não tem preço na tabela 2026');
        self::assertSame('FALTA', $this->pendencias($R['C'])['D05 Preço encontrado para todas as linhas']);
        $S['dig'][0]['man'] = 1800;
        $R = $this->calc($S);
        self::assertSame(1800.0, $R['dig'][0]['tab']);
        self::assertTrue($R['needGs'], 'preço manual exige aprovação do gestor (RN-15/RN-41)');
        self::assertSame('IESP', $R['manuais'][0]['sigla']);
    }

    /** RN-50: acompanhamento do projeto — Aberto, Esgotado ou Encerrado pelo prazo. */
    public function testRn50SituacaoDoProjeto(): void
    {
        $P = ['data_limite_venda' => '2026-12-31', 'cotas' => [['id' => 1, 'nome' => 'Ouro', 'valor' => 5000, 'quantidade_total' => 2], ['id' => 2, 'nome' => 'Prata', 'valor' => 3000, 'quantidade_total' => 1]]];
        $s = ProjetoService::situacao($P, [1 => 1], [], '2026-12-01');
        self::assertSame('Aberto', $s['situacao']);
        self::assertSame(2, $s['disponiveis']);
        self::assertSame(8000.0, $s['falta_vender']);
        $s = ProjetoService::situacao($P, [1 => 2, 2 => 1], [], '2026-12-01');
        self::assertSame('Esgotado', $s['situacao']);
        self::assertSame(0, $s['disponiveis']);
        self::assertSame(0.0, $s['falta_vender']);
        $s = ProjetoService::situacao($P, [1 => 1], [], '2027-01-01');
        self::assertSame('Encerrado (prazo)', $s['situacao'], 'depois da data-limite, comparando com hoje (RN-33)');
        self::assertSame(0.0, $s['falta_vender']);
        self::assertSame(8000.0, $s['nao_vendido'], 'o que sobrou vira "não vendido"');
        $s = ProjetoService::situacao($P, [1 => 1], [1 => 1, 2 => 1], '2026-12-01');
        self::assertSame('Reservado', $s['situacao'], 'tudo o que resta está em reservas de 24 h');
    }

    /** RN-48 e RN-60: painel do mês sem canceladas e substituídas nos valores; canceladas contadas à parte. */
    public function testRn48Rn60PainelNaoSomaCanceladas(): void
    {
        $this->como('teste.admin');
        $f = DashboardService::filtros([]);
        $antes = DashboardService::indicadores($f);
        $this->como('teste.exec');
        $nova = function (int $qtd) {
            $S = $this->estadoBase('FM');
            $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => $qtd]]]];
            $R = $this->calc($S);
            $S = $this->fecharParcela($S, (float) $R['T']['liq']);
            return PiService::gerar($S)['pis'][0];
        };
        $a = $nova(1);
        $b = $nova(3);
        $this->como('teste.gerente');
        PiWorkflow::cancelar($b['id'], 'Cliente desistiu da campanha');
        $this->como('teste.admin');
        $depois = DashboardService::indicadores($f);
        $negA = (float) Database::value('SELECT valor_negociado FROM pis WHERE id = ?', [$a['id']]);
        self::assertEqualsWithDelta($negA, $depois['negociado'] - $antes['negociado'], 0.001, 'só a PI ativa entra no negociado');
        self::assertSame(1, $depois['ativas'] - $antes['ativas']);
        self::assertSame(1, $depois['canceladas'] - $antes['canceladas']);
    }
}
