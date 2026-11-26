<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Services\PiService;
use App\Services\PiWorkflow;
use Tests\TestCase;

/**
 * Geração, numeração, conferência, devolução, correção, aprovação, operação, faturamento, cancelamento,
 * renovação e cancela/substitui. RN-03 a RN-06, RN-17, RN-49, RN-51 a RN-59, RN-63, RN-74, RN-76.
 */
final class PiFluxoTest extends TestCase
{
    /** @return array<string,mixed> */
    private function piFm(array $f = []): array
    {
        $S = $this->estadoBase('FM', $f);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
        return $this->fecharParcela($S, 153.30);
    }

    private function pi(int $id): array
    {
        return Database::first('SELECT * FROM pis WHERE id = ?', [$id]);
    }

    private function checklistTodo(): array
    {
        return array_map(fn ($i) => (int) $i['id'], PiWorkflow::checklist());
    }

    public function testRn03Rn51GeraNumeroSequencialEEnviada(): void
    {
        $this->como('teste.exec');
        $ano = (int) date('Y');
        $a = PiService::gerar($this->piFm())['pis'][0];
        $b = PiService::gerar($this->piFm())['pis'][0];
        self::assertMatchesRegularExpression('/^FM-' . $ano . '-\d{4}$/', $a['numero']);
        self::assertSame((int) substr($a['numero'], -4) + 1, (int) substr($b['numero'], -4), 'sequência anual por veículo');
        self::assertSame('Enviada', $a['situacao']);
        $tv = $this->estadoBase('TV');
        $tv['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]]];
        $tv = $this->fecharParcela($tv, 302);
        $c = PiService::gerar($tv)['pis'][0];
        self::assertStringStartsWith('TV-' . $ano . '-', $c['numero'], 'prefixo por veículo');
        $p = $this->pi($a['id']);
        self::assertSame('153.30', (string) $p['valor_liquido']);
        self::assertSame((int) self::$u['teste.exec'], (int) $p['executivo_id']);
        self::assertSame((int) self::$u['teste.gerente'], (int) $p['gestor_id'], 'RN-76: gestor vem do cadastro');
        self::assertSame(2, (int) Database::value('SELECT COUNT(*) FROM pi_insercoes_diarias d JOIN pi_itens i ON i.id = d.pi_item_id WHERE i.pi_id = ?', [$a['id']]));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM pi_versoes WHERE pi_id = ?', [$a['id']]));
        self::assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND tipo = 'conferencia'", [self::$u['teste.conf']]));
    }

    public function testRn51ComPendenciaSaiComoRascunho(): void
    {
        $this->como('teste.exec');
        $S = $this->piFm(['ac_nome' => '']);
        $g = PiService::gerar($S)['pis'][0];
        self::assertSame('Rascunho', $g['situacao']);
        self::assertSame(1, $g['pendencias']);
        $pend = json_decode((string) $this->pi($g['id'])['pendencias'], true);
        self::assertSame('D10', $pend[0]['code']);
    }

    public function testRn76DadosDoExecutivoNaoPodemSerTrocados(): void
    {
        $this->como('teste.exec');
        $S = $this->piFm(['exec' => 'Outro Nome', 'mat' => '999', 'gestor' => 'Ninguém', 'executivo_id' => self::$u['teste.exec3']]);
        $g = PiService::gerar($S)['pis'][0];
        $f = json_decode((string) $this->pi($g['id'])['estado_formulario'], true)['f'];
        self::assertSame('Executivo de Teste', $f['exec']);
        self::assertSame('E001', $f['mat']);
        self::assertSame('Gerente de Teste', $f['gestor']);
        self::assertSame(self::$u['teste.exec'], (int) $this->pi($g['id'])['executivo_id']);
    }

    public function testRn63ClienteEAgenciaEntramNoCadastro(): void
    {
        $this->como('teste.exec');
        $S = $this->piFm(['cli_razao' => 'Novo Anunciante Ltda.', 'cli_cnpj' => '12ABC34501DE35', 'venda' => 'Agência', 'ag_nome' => 'Agência Nova', 'ag_cnpj' => '04.252.011/0001-10',
            'ag_com' => '20', 'ag_carta' => 'C-9', 'ag_val' => '2027-12-31', 'ag_contato' => 'Ana · ana@agencia.invalid']);
        $S = $this->fecharParcela($S, 153.30 * 0.8);
        $g = PiService::gerar($S)['pis'][0];
        self::assertSame('Enviada', $g['situacao']);
        self::assertSame('Novo Anunciante Ltda.', Database::value("SELECT razao_social FROM clientes WHERE documento = '12ABC34501DE35'"), 'cliente existente atualizado pelo documento');
        $ag = Database::first("SELECT * FROM agencias WHERE cnpj = '04252011000110'");
        self::assertSame('Agência Nova', $ag['razao_social']);
        self::assertSame('ana@agencia.invalid', $ag['email']);
        self::assertSame('30.66', (string) $this->pi($g['id'])['valor_comissao']);
    }

    public function testFluxoCompletoDevolucaoCorrecaoAprovacaoAteEncerrar(): void
    {
        $this->como('teste.exec');
        $g = PiService::gerar($this->piFm())['pis'][0];
        $id = $g['id'];

        $this->como('teste.conf');
        try {
            PiWorkflow::devolver($id, [], 'x');
            self::fail('devolução sem código não vale');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('codigos', $e->errors);
        }
        PiWorkflow::devolver($id, ['D05', 'D07'], 'Conferir o valor da segunda parcela.');
        $p = $this->pi($id);
        self::assertSame('Devolvida', $p['situacao']);
        self::assertSame(1, (int) $p['qtd_devolucoes']);
        self::assertSame(['D05', 'D07'], array_column(Database::all('SELECT motivo_codigo FROM pi_devolucao_codigos c JOIN pi_devolucoes d ON d.id = c.devolucao_id WHERE d.pi_id = ? ORDER BY motivo_codigo', [$id]), 'motivo_codigo'));

        // RN-53: correção mantém o número e volta como Corrigida
        $this->como('teste.exec');
        $rasc = PiService::abrir($id, 'correcao');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $S['f']['obs'] = 'Corrigido';
        $g2 = PiService::gerar($S, $rasc)['pis'][0];
        self::assertSame($g['numero'], $g2['numero']);
        self::assertSame('Corrigida', $g2['situacao']);
        self::assertNotNull(Database::value('SELECT corrigida_em FROM pi_devolucoes WHERE pi_id = ?', [$id]));
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM pi_rascunhos WHERE id = ?', [$rasc]), 'rascunho de trabalho é apagado após gerar');

        // 2ª devolução avisa o gestor
        $this->como('teste.conf');
        PiWorkflow::devolver($id, ['D10'], 'Aceite do cliente sem anexo.');
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND tipo = 'devolvida_gestor'", [self::$u['teste.gerente']]));
        self::assertSame(1, (int) Database::value('SELECT gestor_notificado FROM pi_devolucoes WHERE pi_id = ? AND sequencia = 2', [$id]));

        $this->como('teste.exec');
        $rasc = PiService::abrir($id, 'correcao');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $S['f']['obs'] = 'Aceite anexado';
        PiService::gerar($S, $rasc);

        $this->como('teste.conf');
        try {
            PiWorkflow::aprovar($id, [1, 2]);
            self::fail('aprovar exige todos os itens do checklist');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('checklist', $e->errors);
        }
        PiWorkflow::aprovar($id, $this->checklistTodo(), 'ok');
        $p = $this->pi($id);
        self::assertSame('Aprovada', $p['situacao']);
        self::assertSame(0, (int) $p['aprovada_primeira'], 'teve devolução: não foi certa de primeira');

        PiWorkflow::programar($id);
        PiWorkflow::iniciarVeiculacao($id);
        try {
            $this->como('teste.exec');
            PiWorkflow::finalizarVeiculacao($id, '2026-12-31');
            self::fail('executivo não finaliza veiculação');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        $this->como('teste.conf');
        PiWorkflow::finalizarVeiculacao($id, '31/12/2026');
        self::assertSame('Pronta para faturamento', $this->pi($id)['situacao']);
        self::assertSame('2026-12-31', $this->pi($id)['data_fim_veiculacao']);

        // RN-58: depois de pronta, só consulta
        $this->como('teste.exec');
        try {
            PiService::abrir($id, 'edicao');
            self::fail('PI pronta para faturamento não reabre para edição');
        } catch (BusinessException) {
            self::assertTrue(true);
        }

        $this->como('teste.fin');
        PiWorkflow::faturar($id, 'NF 123', '153,30');
        PiWorkflow::encerrar($id);
        self::assertSame('Encerrada', $this->pi($id)['situacao']);
        self::assertGreaterThanOrEqual(9, (int) Database::value('SELECT COUNT(*) FROM pi_historico WHERE pi_id = ?', [$id]), 'cada mudança fica no histórico (RN-59)');
        self::assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE entidade = 'pi' AND entidade_id = ?", [(string) $id]));
    }

    public function testAprovadaCertaDePrimeira(): void
    {
        $this->como('teste.exec');
        $id = PiService::gerar($this->piFm())['pis'][0]['id'];
        $this->como('teste.conf');
        PiWorkflow::aprovar($id, $this->checklistTodo());
        self::assertSame(1, (int) $this->pi($id)['aprovada_primeira']);
    }

    public function testRn54AprovadaAlteradaVoltaParaConferenciaSemAlteracaoNao(): void
    {
        $this->como('teste.exec');
        $g = PiService::gerar($this->piFm())['pis'][0];
        $this->como('teste.conf');
        PiWorkflow::aprovar($g['id'], $this->checklistTodo());
        $this->como('teste.exec');
        $rasc = PiService::abrir($g['id'], 'edicao');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $r = PiService::gerar($S, $rasc)['pis'][0];
        self::assertSame('Aprovada', $r['situacao'], 'gerar de novo sem alteração mantém a situação (RN-05)');
        self::assertFalse($r['mudou']);
        $rasc = PiService::abrir($g['id'], 'edicao');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $S['ins'][0]['gm']['2026-12']['3'] = 1;
        $S = $this->fecharParcela($S, 229.95);
        $r = PiService::gerar($S, $rasc)['pis'][0];
        self::assertSame('Enviada', $r['situacao']);
        self::assertSame($g['numero'], $r['numero']);
        self::assertSame(2, (int) $this->pi($g['id'])['versao_atual']);
    }

    public function testRn74PermissoesDoFluxo(): void
    {
        $this->como('teste.exec');
        $id = PiService::gerar($this->piFm())['pis'][0]['id'];
        foreach (['teste.exec', 'teste.vend', 'teste.fin'] as $login) {
            $this->como($login);
            try {
                PiWorkflow::aprovar($id, $this->checklistTodo());
                self::fail("{$login} não aprova");
            } catch (HttpException $e) {
                self::assertSame(403, $e->status);
            }
        }
        $this->como('teste.conf');
        try {
            PiService::gerar($this->piFm());
            self::fail('Conferência não gera PI');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        // executivo de outra equipe não vê a PI (RN-73)
        $this->como('teste.exec3');
        try {
            PiService::obter($id);
            self::fail('outra equipe não vê');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        // gerente da equipe vê e pode devolver; gerente de outra equipe não vê
        $this->como('teste.gerente');
        self::assertSame($id, (int) PiService::obter($id)['id']);
        $this->como('teste.gerente2');
        try {
            PiService::obter($id);
            self::fail('gerente de outra equipe não vê');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        // executivo não cancela PI Enviada; cancela o próprio Rascunho
        $this->como('teste.exec');
        try {
            PiWorkflow::cancelar($id, 'Cliente desistiu');
            self::fail('executivo só cancela o próprio rascunho');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status);
        }
        $r = PiService::gerar($this->piFm(['ac_nome' => '']))['pis'][0];
        PiWorkflow::cancelar($r['id'], 'Rascunho descartado');
        self::assertSame('Cancelada', $this->pi($r['id'])['situacao']);
    }

    public function testRn55CancelarExigeMotivoEIrreversivel(): void
    {
        $this->como('teste.exec');
        $id = PiService::gerar($this->piFm())['pis'][0]['id'];
        $this->como('teste.gerente');
        try {
            PiWorkflow::cancelar($id, '');
            self::fail('motivo obrigatório');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        PiWorkflow::cancelar($id, 'Cliente cancelou a campanha');
        self::assertSame('Cancelada', $this->pi($id)['situacao']);
        $this->expectException(BusinessException::class);
        PiWorkflow::cancelar($id, 'De novo');
    }

    public function testRn49RenovacaoGeraNumeroNovoComMesSeguinte(): void
    {
        $this->como('teste.exec');
        $g = PiService::gerar($this->piFm())['pis'][0];
        $rasc = PiService::abrir($g['id'], 'renovacao');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        self::assertSame('Renovação', $S['f']['tipo']);
        self::assertSame($g['numero'], $S['f']['origem']);
        self::assertSame('', $S['f']['num']);
        self::assertSame(['2027-01-01', '2027-01-31'], [$S['f']['ini'], $S['f']['fim']]);
        self::assertSame([], $S['ins'][0]['gm'], 'grade vazia');
        self::assertSame('', $S['f']['ac_nome'], 'aceite em branco');
        $S['ins'][0]['gm'] = ['2027-01' => ['4' => 1]];
        $S = $this->fecharParcela($S, 76.65);
        $S['f']['ac_nome'] = 'Cliente';
        $S['f']['ac_data'] = date('Y-m-d');
        $n = PiService::gerar($S, $rasc)['pis'][0];
        self::assertNotSame($g['numero'], $n['numero']);
        self::assertSame('Renovação', $this->pi($n['id'])['tipo']);
        self::assertSame($g['id'], (int) $this->pi($n['id'])['pi_origem_id']);
    }

    public function testRn56CancelaSubstituiTrocaAntigaSoQuandoANovaEGerada(): void
    {
        $this->como('teste.exec');
        $g = PiService::gerar($this->piFm())['pis'][0];
        $rasc = PiService::abrir($g['id'], 'substituicao');
        self::assertSame('Enviada', $this->pi($g['id'])['situacao'], 'abrir não substitui ainda');
        $S = json_decode((string) Database::value('SELECT estado FROM pi_rascunhos WHERE id = ?', [$rasc]), true);
        $S['f']['ac_nome'] = 'Cliente';
        $S['f']['ac_data'] = date('Y-m-d');
        $n = PiService::gerar($S, $rasc)['pis'][0];
        self::assertNotSame($g['numero'], $n['numero']);
        $antiga = $this->pi($g['id']);
        self::assertSame('Substituída', $antiga['situacao']);
        self::assertSame($n['id'], (int) $antiga['substituida_por_id']);
        self::assertSame('Cancela/substitui', $this->pi($n['id'])['tipo']);
    }

    public function testRn17TvDivididaGeraUmaPiPorMesComGrupo(): void
    {
        $this->como('teste.exec');
        $S = $this->estadoBase('TV', ['ini' => '2026-11-23', 'fim' => '2026-12-22']);
        $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-11' => ['23' => 1, '24' => 1], '2026-12' => ['1' => 1]]]];
        $r = PiService::gerar($S);
        self::assertCount(2, $r['pis']);
        self::assertSame($r['pis'][0]['numero'] . ' + ' . $r['pis'][1]['numero'], $r['grupo']);
        $p1 = $this->pi($r['pis'][0]['id']);
        $p2 = $this->pi($r['pis'][1]['id']);
        self::assertSame($p1['grupo_id'], $p2['grupo_id']);
        self::assertSame('2026-11', $p1['mes_referencia']);
        self::assertSame('604.00', (string) $p1['valor_liquido']);
        self::assertSame('302.00', (string) $p2['valor_liquido']);
        self::assertSame('2026-12-31', Database::value('SELECT vencimento FROM pi_parcelas WHERE pi_id = ?', [$p1['id']]));
        self::assertSame('Enviada', $p1['situacao'], 'D07 da TV dividida: parcela por PI mensal');
        // RN-55: cada PI mensal é cancelada separadamente
        $this->como('teste.gerente');
        PiWorkflow::cancelar((int) $p2['id'], 'Mês de dezembro cancelado');
        self::assertSame('Enviada', $this->pi((int) $p1['id'])['situacao']);
    }

    public function testNumeroDigitadoNaoExisteEBloqueado(): void
    {
        $this->como('teste.exec');
        $this->expectException(BusinessException::class);
        PiService::gerar($this->piFm(['num' => 'FM-2026-9999']));
    }

    public function testRn23NaoGeraComInsercaoEmDiaQueOProgramaNaoVaiAoAr(): void
    {
        $this->como('teste.exec');
        $antes = (int) Database::value('SELECT COUNT(*) FROM pis');
        $S = $this->piFm();
        // ACOREC vai ao ar de segunda a sexta: 05/12/2026 é sábado e 06/12/2026 é domingo
        $S['ins'][0]['gm']['2026-12'] += ['5' => 1, '6' => 1];
        try {
            PiService::gerar($S);
            self::fail('Deveria recusar inserção em dia sem exibição');
        } catch (BusinessException $e) {
            self::assertSame('dias_sem_exibicao', $e->codigo);
            self::assertStringContainsString('ACOREC', $e->getMessage());
            self::assertStringContainsString('dez/26 5, dez/26 6', $e->getMessage());
        }
        self::assertSame($antes, (int) Database::value('SELECT COUNT(*) FROM pis'), 'nenhuma PI gravada');
        // só com os dias de exibição a PI é gerada normalmente
        self::assertSame('Enviada', PiService::gerar($this->piFm())['pis'][0]['situacao']);
    }
}

