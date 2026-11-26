<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\AprovacaoService;
use App\Services\Calculo\CalculadoraPi;
use App\Services\Configuracao;
use App\Services\PiService;
use App\Services\PiWorkflow;
use App\Services\ProjetoService;
use App\Services\ReservaService;
use Tests\TestCase;

/** Projetos especiais (RN-32 a RN-39), reservas de 24 h (RN-65 a RN-70) e aprovação do gestor (RN-41). */
final class ProjetosReservasAprovacoesTest extends TestCase
{
    private function projeto(string $ate, array $veiculos, int $ouro = 1, int $prata = 2): array
    {
        $this->como('teste.admin');
        $id = ProjetoService::salvar(null, ['codigo' => 'T' . random_int(1000, 9999), 'nome' => 'Projeto Teste', 'data_limite_venda' => $ate, 'veiculos' => $veiculos,
            'cotas' => [['nome' => 'Ouro', 'valor' => '5.000,00', 'quantidade_total' => $ouro], ['nome' => 'Prata', 'valor' => '3000', 'quantidade_total' => $prata]]]);
        $cotas = Database::all('SELECT id, nome FROM projetos_cotas WHERE projeto_id = ? ORDER BY ordem', [$id]);
        return ['id' => $id, 'ouro' => (int) $cotas[0]['id'], 'prata' => (int) $cotas[1]['id']];
    }

    private function comProjeto(array $P, int $cota, int $q = 1, string $prefixo = 'FM', string $cob = 'Pago'): array
    {
        $S = $this->estadoBase($prefixo);
        $S['prj'] = [['id' => $P['id'], 'cota' => $cota, 'q' => $q, 'cob' => $cob]];
        return $S;
    }

    public function testRn37ValorDaCotaEFechamento(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+10 days')), [$this->veiculo('FM')]);
        $this->como('teste.exec');
        $S = $this->fecharParcela($this->comProjeto($P, $P['prata'], 2), 6000);
        $R = PiService::previa($S, null);
        self::assertSame(6000.0, $R['T']['prjTab']);
        self::assertSame(0, $R['pend']);
        $g = PiService::gerar($S)['pis'][0];
        self::assertSame('Enviada', $g['situacao']);
        self::assertSame([$P['prata'] => 2], ProjetoService::vendidas());
    }

    public function testRn33Rn35Rn34BloqueiosImpedemGerar(): void
    {
        $fm = $this->veiculo('FM');
        $vencido = $this->projeto(date('Y-m-d', strtotime('-1 day')), [$fm]);
        $outraEmpresa = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('TV')]);
        $ok = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$fm]);
        $this->como('teste.exec');
        foreach ([[$vencido, $vencido['ouro'], 1, 'P02'], [$outraEmpresa, $outraEmpresa['ouro'], 1, 'P04'], [$ok, $ok['ouro'], 2, 'P03']] as [$P, $cota, $q, $cod]) {
            try {
                PiService::gerar($this->comProjeto($P, $cota, $q));
                self::fail("{$cod} deveria bloquear a PI");
            } catch (BusinessException $e) {
                self::assertSame('projeto_bloqueado', $e->codigo, $cod);
            }
        }
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM pis'), 'nenhuma PI gerada (nem número consumido)');
    }

    public function testRn36RascunhoConsomeCanceladaDevolveERegerarNaoDuplica(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('FM')], 1);
        $this->como('teste.exec');
        $S = $this->comProjeto($P, $P['ouro']);
        $S['f']['ac_nome'] = ''; // fica Rascunho
        $g = PiService::gerar($S)['pis'][0];
        self::assertSame('Rascunho', $g['situacao']);
        self::assertSame(1, ProjetoService::vendidas()[$P['ouro']], 'Rascunho também desconta a cota');
        // gerar de novo a mesma PI não desconta duas vezes
        $rasc = PiService::abrir($g['id'], 'edicao');
        $S2 = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $S2['f']['obs'] = 'alterada';
        PiService::gerar($S2, $rasc);
        self::assertSame(1, ProjetoService::vendidas()[$P['ouro']]);
        // outro vendedor não consegue a última cota
        $this->como('teste.exec2');
        try {
            PiService::gerar($this->comProjeto($P, $P['ouro']));
            self::fail('cota esgotada');
        } catch (BusinessException $e) {
            self::assertSame('projeto_bloqueado', $e->codigo);
        }
        // cancelada devolve a cota
        $this->como('teste.exec');
        PiWorkflow::cancelar($g['id'], 'Desistência');
        self::assertArrayNotHasKey($P['ouro'], ProjetoService::vendidas());
        $this->como('teste.exec2');
        $S3 = $this->fecharParcela($this->comProjeto($P, $P['ouro']), 5000);
        self::assertSame('Enviada', PiService::gerar($S3)['pis'][0]['situacao']);
    }

    public function testRn38CotaSoNaPrimeiraPiMensalDaTv(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('TV')]);
        $this->como('teste.exec');
        $S = $this->comProjeto($P, $P['ouro'], 1, 'TV');
        $S['f']['ini'] = '2026-11-23';
        $S['f']['fim'] = '2026-12-22';
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-11' => ['23' => 1], '2026-12' => ['1' => 1]]]];
        $r = PiService::gerar($S);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM pi_itens WHERE bloco = 'projeto' AND pi_id = ?", [$r['pis'][0]['id']]));
        self::assertSame(0, (int) Database::value("SELECT COUNT(*) FROM pi_itens WHERE bloco = 'projeto' AND pi_id = ?", [$r['pis'][1]['id']]));
        self::assertSame(1, ProjetoService::vendidas()[$P['ouro']]);
    }

    public function testRn39NaoReduzAbaixoDoVendidoEExcluirMantemPis(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('FM')], 1, 2);
        $this->como('teste.exec');
        $g = PiService::gerar($this->comProjeto($P, $P['prata'], 2))['pis'][0];
        $this->como('teste.admin');
        try {
            ProjetoService::salvar($P['id'], ['codigo' => Database::value('SELECT codigo FROM projetos_especiais WHERE id = ?', [$P['id']]), 'nome' => 'X', 'data_limite_venda' => date('Y-m-d', strtotime('+5 days')),
                'veiculos' => [$this->veiculo('FM')], 'cotas' => [['id' => $P['ouro'], 'nome' => 'Ouro', 'valor' => 5000, 'quantidade_total' => 1], ['id' => $P['prata'], 'nome' => 'Prata', 'valor' => 3000, 'quantidade_total' => 1]]]);
            self::fail('RN-39');
        } catch (BusinessException $e) {
            self::assertStringContainsString('RN-39', $e->getMessage());
        }
        ProjetoService::excluir($P['id']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM pis WHERE id = ?', [$g['id']]));
        self::assertArrayNotHasKey($P['id'], ProjetoService::ativos());
    }

    public function testRn65Rn66Rn67Rn68ReservaSeguraCotaEAvisaEspaco(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('FM')], 1);
        $this->como('teste.exec');
        $S = $this->comProjeto($P, $P['ouro']);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
        $res = ReservaService::reservar($S, null);
        self::assertSame('ativa', $res['situacao']);
        $exp = strtotime((string) $res['expira_em']) - strtotime((string) $res['reservada_em']);
        self::assertSame(24 * 3600, $exp, 'RN-67: 24 horas');

        // RN-66: outro vendedor não tem a cota
        $this->como('teste.exec2');
        $S2 = $this->comProjeto($P, $P['ouro']);
        $R2 = PiService::previa($S2, null);
        self::assertTrue($R2['projBlock']);
        // RN-68: mesmo programa e dia = aviso, sem bloqueio
        $S3 = $this->estadoBase('FM', ['cli_razao' => 'Outro cliente']);
        $S3['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['2' => 1, '3' => 1]]]];
        $R3 = PiService::previa($S3, null);
        self::assertCount(1, $R3['conflitos']);
        self::assertSame('Executivo de Teste', $R3['conflitos'][0]['executivo']);
        self::assertSame('dias 2/12', $R3['conflitos'][0]['onde']);
        self::assertFalse($R3['projBlock']);

        // RN-67: atualizar a reserva não renova o prazo
        $this->como('teste.exec');
        $S['f']['res'] = $res['id'];
        $S['ins'][0]['gm']['2026-12']['3'] = 1;
        Database::query('UPDATE reservas SET reservada_em = reservada_em - INTERVAL 1 HOUR, expira_em = expira_em - INTERVAL 1 HOUR WHERE id = ?', [$res['id']]);
        $antes = Database::value('SELECT expira_em FROM reservas WHERE id = ?', [$res['id']]);
        $res2 = ReservaService::reservar($S, null);
        self::assertSame($res['id'], $res2['id']);
        self::assertSame($antes, $res2['expira_em']);

        // RN-70: só o dono libera
        $this->como('teste.exec2');
        try {
            ReservaService::liberar((int) $res['id']);
            self::fail('só o dono libera');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }

        // RN-69: gerar a PI converte a reserva em venda
        $this->como('teste.exec');
        $S = $this->fecharParcela($S, 5000 + 76.65 * 3);
        $g = PiService::gerar($S)['pis'][0];
        $r = Database::first('SELECT situacao, pi_id FROM reservas WHERE id = ?', [$res['id']]);
        self::assertSame('convertida', $r['situacao']);
        self::assertSame($g['id'], (int) $r['pi_id']);
        self::assertSame(1, ProjetoService::vendidas()[$P['ouro']]);
    }

    public function testExpiracaoAutomaticaDasReservas(): void
    {
        $P = $this->projeto(date('Y-m-d', strtotime('+5 days')), [$this->veiculo('FM')], 1);
        $this->como('teste.exec');
        $res = ReservaService::reservar($this->comProjeto($P, $P['ouro']), null);
        Database::query('UPDATE reservas SET expira_em = NOW() - INTERVAL 1 MINUTE WHERE id = ?', [$res['id']]);
        self::assertSame([], ProjetoService::reservadas(), 'vencida já não segura a cota, mesmo antes da rotina');
        self::assertSame(1, ReservaService::expirarVencidas());
        self::assertSame('expirada', Database::value('SELECT situacao FROM reservas WHERE id = ?', [$res['id']]));
        self::assertSame(0, ReservaService::expirarVencidas());
    }

    public function testRn41AprovacaoDoGestorNoSistema(): void
    {
        $this->como('teste.exec');
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'desc' => 10, 'gm' => ['2026-12' => ['1' => 10]]]];
        $S = $this->fecharParcela($S, 689.85);
        $rasc = (int) Database::insert('pi_rascunhos', ['usuario_id' => self::$u['teste.exec'], 'estado' => json_encode($S)]);
        $R = PiService::previa($S, $rasc);
        self::assertSame('FALTA', $this->pendencias($R['C'])['D06 Desconto, bonificação ou preço manual aprovados pelo gestor']);
        $apr = AprovacaoService::solicitar($rasc, null, $R['T'], $R['manuais'], 'Cliente fiel', 'FM · Cliente Teste');
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND tipo = 'aprovacao'", [self::$u['teste.gerente']]));
        // gerente de outra equipe não aprova
        $this->como('teste.gerente2');
        try {
            AprovacaoService::responder($apr, true, '');
            self::fail('gerente de outra equipe');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        $this->como('teste.gerente');
        AprovacaoService::responder($apr, true, 'ok');
        $this->como('teste.exec');
        $R = PiService::previa($S, $rasc);
        self::assertArrayNotHasKey('D06 Desconto, bonificação ou preço manual aprovados pelo gestor', $this->pendencias($R['C']));
        // aumentar o desconto depois da aprovação volta a pendência
        $S2 = $S;
        $S2['ins'][0]['desc'] = 15;
        $R2 = PiService::previa($S2, $rasc);
        self::assertSame('FALTA', $this->pendencias($R2['C'])['D06 Desconto, bonificação ou preço manual aprovados pelo gestor']);
        // gerada: nome e data do aprovador vão para a PI
        $g = PiService::gerar($S, $rasc)['pis'][0];
        self::assertSame('Enviada', $g['situacao']);
        $snap = json_decode((string) Database::value('SELECT snapshot FROM pis WHERE id = ?', [$g['id']]), true);
        self::assertSame('Gerente de Teste', $snap['f']['gs_nome']);
        self::assertSame($g['id'], (int) Database::value('SELECT pi_id FROM pi_aprovacoes WHERE id = ?', [$apr]));
    }

    public function testAlcadaAcimaDoGestorVaiParaDiretoria(): void
    {
        Configuracao::definir('alcada_gestor_desconto_pct', '10');
        $this->como('teste.exec');
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'desc' => 20, 'gm' => ['2026-12' => ['1' => 10]]]];
        $rasc = (int) Database::insert('pi_rascunhos', ['usuario_id' => self::$u['teste.exec'], 'estado' => json_encode($S)]);
        $R = PiService::previa($S, $rasc);
        $apr = AprovacaoService::solicitar($rasc, null, $R['T'], $R['manuais'], '', 'teste');
        self::assertSame('diretoria', Database::value('SELECT tipo FROM pi_aprovacoes WHERE id = ?', [$apr]));
        $this->como('teste.gerente');
        try {
            AprovacaoService::responder($apr, true, '');
            self::fail('acima da alçada do gestor');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        $this->como('teste.diretor');
        AprovacaoService::responder($apr, true, 'aprovado pela diretoria');
        self::assertSame('aprovada', Database::value('SELECT situacao FROM pi_aprovacoes WHERE id = ?', [$apr]));
    }

    public function testCalculadoraNaoBloqueiaSemProjeto(): void
    {
        self::assertFalse(CalculadoraPi::temBloqueio(['projBlock' => false]));
    }

    public function testRn23NaoReservaInsercaoEmDiaQueOProgramaNaoVaiAoAr(): void
    {
        $this->como('teste.exec');
        $S = $this->estadoBase('FM');
        // ACOREC vai ao ar de segunda a sexta: 06/12/2026 é domingo
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['4' => 1, '6' => 1]]]];
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('dez/26 6');
        ReservaService::reservar($S, null);
    }

    public function testRn23ProgramaSemDiasDefinidosNaoTemRestricao(): void
    {
        $R = ['lines' => [['p' => ['sigla' => 'X', 'nome' => 'Variável', 'dias_exibicao' => 'Variável'], 'nome' => 'Variável', 'noAir' => []]]];
        self::assertNull(CalculadoraPi::erroDiasSemExibicao($R));
        $R['lines'][0]['noAir'] = ['dez/26 6'];
        self::assertSame('Há inserções em dias em que o programa não vai ao ar. Limpe esses dias na grade: X (Variável, Variável): dez/26 6.', CalculadoraPi::erroDiasSemExibicao($R));
    }
}

