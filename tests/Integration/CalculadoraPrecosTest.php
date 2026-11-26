<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\Calculo\CalculadoraPi;
use App\Services\Calculo\Catalogo;
use App\Services\Calculo\ContextoCalculo;
use App\Services\Calculo\DivisaoMensalTv;
use App\Services\ParcelamentoService;
use Tests\TestCase;

/**
 * Cálculos de preço com os dados REAIS migrados do Gerador (tabelas 2026) e os exemplos numéricos do documento.
 * RN-12, RN-13, RN-17, RN-20, RN-24 a RN-31, RN-40, RN-42, RN-43.
 */
final class CalculadoraPrecosTest extends TestCase
{
    private function calc(array $S, ?ContextoCalculo $ctx = null): array
    {
        return (new CalculadoraPi($ctx ?? new ContextoCalculo(new Catalogo())))->calcular($S);
    }

    private function preco(string $prefixo, string $sigla, string $tab, string $tipo, ?int $dur = null): float
    {
        return (float) Database::value(
            'SELECT pm.valor FROM precos_midia pm JOIN programas p ON p.id = pm.programa_id JOIN tabelas_precos t ON t.id = pm.tabela_preco_id JOIN veiculos v ON v.id = p.veiculo_id
              WHERE v.prefixo = ? AND p.sigla = ? AND t.codigo = ? AND pm.tipo = ? AND (pm.duracao_seg <=> ?)',
            [$prefixo, $sigla, $tab, $tipo, $dur]
        );
    }

    public function testRn12TvPrecoDo30VezesIndice(): void
    {
        self::assertSame(302.0, $this->preco('TV', 'HORA', 'MA1', 'base_30s'), 'HORA São Luís 30" = R$ 302 (tabela 2026)');
        $S = $this->estadoBase('TV');
        $S['ins'] = [
            ['s' => 'HORA — Hora Um', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]],
            ['s' => 'HORA', 'dur' => 45, 'gm' => ['2026-12' => ['2' => 1]]],
            ['s' => 'HORA', 'dur' => 5, 'gm' => ['2026-12' => ['3' => 1]]],
            ['s' => 'HORA', 'dur' => 15, 'gm' => ['2026-12' => ['4' => 2]]],
        ];
        $R = $this->calc($S);
        $u = array_map(fn ($x) => round($x['unit'], 4), $R['ins']);
        self::assertSame([302.0, 453.0, 113.25, 151.0], $u, '30"×1 · 45"×1,5 · 5"×0,375 · 15"×0,5 (índice do programa)');
        self::assertEqualsWithDelta(302 + 453 + 113.25 + 302, $R['T']['insBruto'], 0.001);
        self::assertSame(5, $R['T']['nIns']);
    }

    public function testTv15SegundosUsaIndiceProprioDoPrograma(): void
    {
        $base = $this->preco('TV', 'GESP', 'MA1', 'base_30s');
        self::assertGreaterThan(0, $base);
        $S = $this->estadoBase('TV');
        $S['ins'] = [['s' => 'GESP', 'dur' => 15, 'gm' => ['2026-12' => ['1' => 1]]]];
        $R = $this->calc($S);
        self::assertEqualsWithDelta($base * 0.75, $R['ins'][0]['unit'], 0.0001, 'GESP tem índice 0,75 no 15" (campo c do Gerador)');
    }

    public function testRn25TvGrpImpactosCpm(): void
    {
        $S = $this->estadoBase('TV');
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
        $R = $this->calc($S);
        self::assertSame(16753 * 2, $R['T']['imp']);
        self::assertEqualsWithDelta(1.61 * 2, $R['T']['grp'], 0.00001);
        self::assertEqualsWithDelta(604 / 33506 * 1000, $R['T']['cpm'], 0.0001);
    }

    public function testRn13RadioPorDuracaoEVerba(): void
    {
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 2, '2' => 1]]]];
        $R = $this->calc($S);
        self::assertSame(76.65, $R['ins'][0]['unit']);
        self::assertEqualsWithDelta(229.95, $R['T']['insBruto'], 0.0001);
        $S['f']['tab'] = 'NAC';
        self::assertSame(153.3, $this->calc($S)['ins'][0]['unit'], 'verba nacional');
        $S['ins'][0]['dur'] = 5;
        $R = $this->calc($S);
        self::assertNull($R['ins'][0]['unit'], 'duração sem preço na tabela');
        self::assertArrayHasKey('D05 Preço encontrado para todas as linhas', $this->pendencias($R['C']));
    }

    public function testRn18RadioUmaPiComLinhasPorMes(): void
    {
        $S = $this->estadoBase('FM', ['ini' => '2026-11-23', 'fim' => '2026-12-22']);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-11' => ['23' => 1, '24' => 1], '2026-12' => ['1' => 1]]]];
        $R = $this->calc($S);
        self::assertFalse($R['splitTV']);
        self::assertCount(2, $R['ins'], 'uma linha por mês');
        self::assertSame(['2026-11', '2026-12'], array_map(fn ($x) => $x['M']['key'], $R['ins']));
        $partes = (new DivisaoMensalTv(new CalculadoraPi(new ContextoCalculo(new Catalogo()))))->dividir($S);
        self::assertCount(1, $partes, 'rádio sai em uma PI só');
    }

    public function testRn22eRn23DiasInvalidos(): void
    {
        $S = $this->estadoBase('TV', ['ini' => '2026-12-05', 'fim' => '2026-12-31']);
        // 01/12 fora do período; 06/12 é domingo e HORA vai ao ar SEG/SEX
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '6' => 1, '7' => 1]]]];
        $R = $this->calc($S);
        $p = $this->pendencias($R['C']);
        self::assertSame('ERRO', $p['D02 Inserções só em dias dentro do período da campanha']);
        self::assertSame('ERRO', $p['D03 Inserções só nos dias em que o programa vai ao ar']);
        self::assertSame(['dez/26 1'], $R['lines'][0]['out']);
        self::assertSame(['dez/26 6'], $R['lines'][0]['noAir']);
    }

    public function testRn24BonificadoEReaplicacaoValemZero(): void
    {
        $S = $this->estadoBase('FM');
        $S['ins'] = [
            ['s' => 'ACOREC', 'dur' => 30, 'cob' => 'Bonificado', 'gm' => ['2026-12' => ['1' => 2]]],
            ['s' => 'ACOREC', 'dur' => 30, 'cob' => 'Reaplicação', 'gm' => ['2026-12' => ['2' => 1]]],
            ['s' => 'ACOREC', 'dur' => 30, 'cob' => 'Permuta', 'desc' => 10, 'gm' => ['2026-12' => ['3' => 1]]],
        ];
        $R = $this->calc($S);
        self::assertEqualsWithDelta(76.65 * 3, $R['T']['bonif'], 0.0001);
        self::assertEqualsWithDelta(76.65 * 0.9, $R['T']['insTotal'], 0.0001);
        self::assertEqualsWithDelta(76.65 * 4, $R['T']['tab'], 0.0001, 'bonificado conta no valor de tabela');
        self::assertArrayHasKey('D06 Desconto, bonificação ou preço manual aprovados pelo gestor', $this->pendencias($R['C']));
    }

    public function testRn26PatrocinioEsquemaVezesCotas(): void
    {
        $S = $this->estadoBase('FM');
        $S['pat'] = [['s' => 'ACOREC', 'cot' => 2, 'out' => 3]];
        $R = $this->calc($S);
        $x = $R['pat'][0];
        self::assertSame([176, 0, 132, 0, 44, 0], $x['esq'], 'esquema [88,0,66,0,22,0] × 2 cotas');
        self::assertSame(176 + 132 + 44 + 3, $x['n']);
        self::assertEqualsWithDelta(6237 * 2, $x['valor'], 0.001);
        $S['f']['tab'] = 'NAC';
        self::assertEqualsWithDelta(12474 * 2, $this->calc($S)['pat'][0]['valor'], 0.001);
    }

    public function testRn27DigitalMensalProporcional(): void
    {
        $casos = [['2026-12-01', '2026-12-31', 4850.00], ['2026-12-01', '2026-12-15', 2346.77], ['2026-11-16', '2026-11-30', 2425.00], ['2026-11-23', '2026-12-22', 4735.27]];
        foreach ($casos as [$a, $b, $esperado]) {
            $S = $this->estadoBase('G1', ['ini' => $a, 'fim' => $b]);
            $S['dig'] = [['s' => 'G1PAT']];
            $R = $this->calc($S);
            self::assertSame($esperado, round($R['dig'][0]['tab'], 2), "G1PAT {$a} a {$b} (exemplo do documento)");
        }
    }

    public function testRn28Rn29Rn30DigitalDiariaCpmECobranca(): void
    {
        $S = $this->estadoBase('G1');
        $S['dig'] = [['s' => 'G1DUT', 'qtd' => 3], ['s' => 'G1BB', 'ent' => 100000], ['s' => 'G1RM'], ['s' => 'G1PAT', 'cob' => 'Incluso no pacote'], ['s' => 'G1DFS', 'cob' => 'Bonificado']];
        $R = $this->calc($S);
        self::assertSame(2070.0, $R['dig'][0]['tab'], '3 diárias × R$ 690');
        self::assertSame(2000.0, $R['dig'][1]['tab'], '100.000 impressões a R$ 20');
        self::assertTrue($R['dig'][2]['cpmSemEnt'], 'CPM sem impressões');
        self::assertSame('FALTA', $this->pendencias($R['C'])['D05 Impressões informadas nos formatos vendidos por CPM']);
        self::assertSame(0.0, $R['dig'][3]['tab'], 'Incluso no pacote: tabela zero');
        self::assertSame(636.0, $R['dig'][4]['bonif']);
        self::assertSame(0.0, $R['dig'][4]['total']);
    }

    public function testRn31PublicidadeLegalSomenteImirante(): void
    {
        $S = $this->estadoBase('IMI');
        $S['avi'] = [['s' => 'IBAL', 'mat' => 'Balanço 2026', 'col' => 2, 'cm' => 10, 'pub' => 1]];
        $R = $this->calc($S);
        self::assertSame(3380.0, $R['avi'][0]['tab'], 'Balanço, 2 col × 10 cm × 1 × R$ 169');
        $S2 = $this->estadoBase('G1');
        $S2['avi'] = [['s' => 'IBAL', 'col' => 2, 'cm' => 10]];
        self::assertSame([], $this->calc($S2)['avi'], 'G1 não tem bloco de publicidade legal');
        $S['dig'] = [['s' => 'IBAL']];
        self::assertSame('Sigla não cadastrada', $this->calc($S)['dig'][0]['nome'], 'publicidade legal não entra como formato digital');
    }

    public function testRn40Rn42TotaisComissaoELiquido(): void
    {
        $S = $this->estadoBase('FM', ['venda' => 'Agência', 'ag_nome' => 'Agência Teste', 'ag_cnpj' => '11.444.777/0001-61', 'ag_com' => '20', 'ag_carta' => 'CC-1', 'producao' => '100']);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'desc' => 10, 'gm' => ['2026-12' => ['1' => 10]]]];
        $R = $this->calc($S);
        $T = $R['T'];
        self::assertEqualsWithDelta(766.5 + 100, $T['tab'], 0.0001, 'tabela = linhas + produção');
        self::assertEqualsWithDelta(689.85 + 100, $T['neg'], 0.0001, 'negociado = linhas com desconto + produção');
        self::assertEqualsWithDelta(76.65, $T['desc'], 0.0001);
        self::assertEqualsWithDelta(789.85 * 0.2, $T['com'], 0.0001, 'comissão = negociado × %');
        self::assertEqualsWithDelta(789.85 * 0.8, $T['liq'], 0.0001, 'líquido = negociado − comissão');
    }

    public function testRn43ParcelasCentavosNaUltima(): void
    {
        $p = ParcelamentoService::gerar(1000.00, 3, '2026-12-31');
        self::assertSame(['2026-12-31', '2027-01-31', '2027-02-28'], array_column($p, 'venc'), 'dia ajustado ao fim do mês');
        self::assertSame([333.33, 333.33, 333.34], array_column($p, 'val'));
        self::assertEqualsWithDelta(1000.0, array_sum(array_column($p, 'val')), 0.0001);
        self::assertCount(12, ParcelamentoService::gerar(100, 40, '2026-12-01'), 'no máximo 12 parcelas');
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 3]]]];
        $S['parc'] = [['venc' => '2027-01-10', 'val' => 100], ['venc' => '', 'val' => 129.95]];
        self::assertSame('ERRO', $this->pendencias($this->calc($S)['C'])['D07 Pagamento: forma e parcelas fechando com o líquido'], 'parcela sem vencimento');
        $S['parc'][1]['venc'] = '2027-02-10';
        self::assertArrayNotHasKey('D07 Pagamento: forma e parcelas fechando com o líquido', $this->pendencias($this->calc($S)['C']));
    }

    public function testRn17Rn20Rn38TvDivididaPorMes(): void
    {
        $S = $this->estadoBase('TV', ['ini' => '2026-11-23', 'fim' => '2026-12-22', 'producao' => '500']);
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-11' => ['23' => 1, '24' => 1], '2026-12' => ['1' => 2]]]];
        $calc = new CalculadoraPi(new ContextoCalculo(new Catalogo()));
        $R = $calc->calcular($S);
        self::assertTrue($R['splitTV']);
        $partes = (new DivisaoMensalTv($calc))->dividir($S);
        self::assertCount(2, $partes);
        self::assertSame(['2026-11-23', '2026-11-30'], [$partes[0]['f']['ini'], $partes[0]['f']['fim']], 'PI de 23 a 30/11');
        self::assertSame(['2026-12-01', '2026-12-22'], [$partes[1]['f']['ini'], $partes[1]['f']['fim']], 'PI de 01 a 22/12');
        self::assertSame('2026-12-31', $partes[0]['parc'][0]['venc'], 'PI de novembro vence em 31/12 (RN-20)');
        self::assertSame('2027-01-29', $partes[1]['parc'][0]['venc']);
        self::assertSame(604.0 + 500, $partes[0]['parc'][0]['val'], 'produção só na PI do 1º mês (ajuste A-01)');
        self::assertSame(604.0, $partes[1]['parc'][0]['val']);
        self::assertSame('1 de 2', $partes[0]['f']['parte']);
    }

    public function testConferenciaAutomaticaPiCompletaSemPendencias(): void
    {
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
        $S = $this->fecharParcela($S, 153.30);
        $R = $this->calc($S);
        self::assertSame([], $this->pendencias($R['C']));
        self::assertSame(0, $R['pend']);
    }

    public function testConferenciaAutomaticaApontaPendenciasComCodigos(): void
    {
        $S = ['f' => ['veic' => $this->veiculo('FM'), 'tipo' => 'Renovação', 'emissao' => date('Y-m-d'), 'venda' => 'Agência', 'cli_cnpj' => '11.222.333/0001-80',
            'ag_nome' => 'Ag', 'ag_cnpj' => '11.444.777/0001-61', 'ag_com' => 10, 'ag_carta' => 'X', 'ag_val' => '2026-01-01', 'ini' => '2026-12-10', 'fim' => '2026-12-01'],
            'ins' => [['s' => 'NAOEXISTE', 'dur' => 30, 'gm' => []], ['s' => 'ACOREC', 'gm' => []]],
            'ent' => [['tipo' => 'Promoção / sorteio', 'jur' => 'Pendente']]];
        $p = $this->pendencias($this->calc($S)['C']);
        foreach (['D14 Veículo e tabela/praça escolhidos', 'D14 Tipo de PI, emissão e PI de origem (renovação, alteração, cancelamento ou cancela/substitui)', 'D15 Executivo, matrícula e gestor',
                     'D01 CNPJ do cliente válido', 'D08 Carta de credenciamento da agência no prazo', 'D02 Período da campanha (início ≤ fim)', 'D11 Siglas cadastradas',
                     'D12 Duração em todas as linhas de inserção', 'D09 Entregas especiais com área e nº do briefing', 'D13 Promoção/sorteio com aval do jurídico',
                     'D10 Aceite do cliente (nome e data)', 'D07 Pagamento: forma e parcelas fechando com o líquido'] as $k) {
            self::assertArrayHasKey($k, $p, $k);
        }
        self::assertSame('ERRO', $p['D01 CNPJ do cliente válido'], 'RN-61');
        self::assertSame('ERRO', $p['D08 Carta de credenciamento da agência no prazo'], 'RN-80');
    }

    public function testRn15SatSemTabelaExigePrecoManualComAprovacao(): void
    {
        $S = $this->estadoBase('TV', ['tab' => 'SAT']);
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]]];
        $R = $this->calc($S);
        self::assertSame('FALTA', $this->pendencias($R['C'])['D05 Preço encontrado para todas as linhas']);
        $S['ins'][0]['man'] = 250;
        $R = $this->calc($S);
        self::assertSame(250.0, $R['ins'][0]['unit']);
        self::assertTrue($R['needGs'], 'preço manual exige aprovação (RN-15/RN-41)');
        self::assertSame([['bloco' => 'ins', 'linha' => 0, 'sigla' => 'HORA', 'preco' => 250.0]], $R['manuais']);
    }

    public function testRn11SiglaPorCodigoOuNome(): void
    {
        $cat = new Catalogo();
        $tv = $this->veiculo('TV');
        self::assertSame('HORA', $cat->resolverSigla($tv, 2026, 'ins', 'Hora Um'));
        self::assertSame('HORA', $cat->resolverSigla($tv, 2026, 'ins', 'hora — qualquer'));
        self::assertSame('BPRA', $cat->resolverSigla($tv, 2026, 'ins', 'bom dia mirante'), 'nome exato, sem acento e sem maiúsculas');
        self::assertSame('IBAL', $cat->resolverSigla($this->veiculo('IMI'), 2026, 'avi', 'Publicidade legal: Balanço'));
        self::assertSame('XYZ', $cat->resolverSigla($tv, 2026, 'ins', 'xyz'));
    }
}
