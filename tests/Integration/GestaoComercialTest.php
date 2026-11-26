<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnexoService;
use App\Services\AprovacaoService;
use App\Services\Auth;
use App\Services\PiService;
use App\Services\PiWorkflow;
use App\Services\RelatorioService;
use App\Services\ReservaService;
use App\Services\RotinasService;
use App\Services\SlaService;
use Tests\TestCase;

/** Fase 5 — Conferência, aprovações, operação/faturamento, reservas, projetos, relatórios, anexos, rotinas e SLA. */
final class GestaoComercialTest extends TestCase
{
    private function entrar(string $login): void
    {
        $_SESSION = ['_csrf' => 'tok'];
        Auth::reset();
        Auth::entrar(Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]));
    }

    private function req(string $method, string $path, array $post = [], array $query = [], array $files = []): Response
    {
        Auth::reset();
        return App::handle(new Request($method, $path, $query, $post + ['_token' => 'tok'], $files,
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'phpunit']));
    }

    private function piEnviada(string $login = 'teste.exec', array $f = []): array
    {
        $this->como($login);
        $S = $this->estadoBase('FM', $f);
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
        $S = $this->fecharParcela($S, 153.30);
        return PiService::gerar($S)['pis'][0];
    }

    public function testConferenciaPelaTelaAprovaEDevolve(): void
    {
        $a = $this->piEnviada();
        $b = $this->piEnviada();
        $this->entrar('teste.conf');
        $fila = $this->req('GET', '/conferencia');
        self::assertSame(200, $fila->status());
        self::assertStringContainsString($a['numero'], $fila->body());
        $tela = $this->req('GET', '/conferencia/' . $a['id']);
        self::assertSame(200, $tela->status());
        self::assertStringContainsString('Checklist da Conferência', $tela->body());
        // sem todos os itens não aprova
        $this->req('POST', '/conferencia/' . $a['id'] . '/aprovar', ['checklist' => [1, 2, 3]]);
        self::assertSame('Enviada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]));
        $todos = array_map(fn ($i) => (int) $i['id'], PiWorkflow::checklist());
        $r = $this->req('POST', '/conferencia/' . $a['id'] . '/aprovar', ['checklist' => $todos]);
        self::assertSame(302, $r->status());
        self::assertStringContainsString('/conferencia/' . $b['id'], $r->headers()['Location'], 'abre a próxima da fila');
        self::assertSame('Aprovada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]));
        // devolução sem código não vale
        $this->req('POST', '/conferencia/' . $b['id'] . '/devolver', ['descricao' => 'Corrigir o e-mail']);
        self::assertSame('Enviada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$b['id']]));
        $this->req('POST', '/conferencia/' . $b['id'] . '/devolver', ['codigos' => ['D01', 'D07'], 'descricao' => 'E-mail de faturamento pessoal', 'checklist' => [1, 2]]);
        self::assertSame('Devolvida', Database::value('SELECT situacao FROM pis WHERE id = ?', [$b['id']]));
        $ck = json_decode((string) Database::value('SELECT checklist FROM pi_conferencias WHERE pi_id = ? ORDER BY id DESC LIMIT 1', [$b['id']]), true);
        self::assertTrue($ck[0]['ok']);
        self::assertFalse($ck[2]['ok'], 'checklist registra o que foi conferido');
        // executivo não acessa a Conferência
        $this->entrar('teste.exec');
        self::assertSame(403, $this->req('GET', '/conferencia')->status());
    }

    public function testAprovacoesPelaTela(): void
    {
        $this->como('teste.exec');
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'desc' => 10, 'gm' => ['2026-12' => ['1' => 2]]]];
        $rid = Database::insert('pi_rascunhos', ['usuario_id' => self::$u['teste.exec'], 'estado' => json_encode($S)]);
        $R = PiService::previa($S, $rid);
        $apr = AprovacaoService::solicitar($rid, null, $R['T'], $R['manuais'], 'Cliente novo', 'FM · teste');
        $this->entrar('teste.gerente');
        $tela = $this->req('GET', '/aprovacoes');
        self::assertSame(200, $tela->status());
        self::assertStringContainsString('Cliente novo', $tela->body());
        $this->req('POST', '/aprovacoes/' . $apr . '/responder', ['decisao' => 'recusar', 'resposta' => '']);
        self::assertSame('pendente', Database::value('SELECT situacao FROM pi_aprovacoes WHERE id = ?', [$apr]), 'recusa exige motivo');
        $this->req('POST', '/aprovacoes/' . $apr . '/responder', ['decisao' => 'aprovar', 'resposta' => 'ok']);
        self::assertSame('aprovada', Database::value('SELECT situacao FROM pi_aprovacoes WHERE id = ?', [$apr]));
        $this->entrar('teste.gerente2');
        self::assertStringNotContainsString('Cliente novo', $this->req('GET', '/aprovacoes')->body(), 'outra equipe não vê');
    }

    public function testQuemPedeNaoAprovaNemSendoAdministrador(): void
    {
        $this->como('teste.admin');
        $S = $this->estadoBase('FM');
        $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'desc' => 10, 'gm' => ['2026-12' => ['1' => 2]]]];
        $rid = Database::insert('pi_rascunhos', ['usuario_id' => self::$u['teste.admin'], 'estado' => json_encode($S)]);
        $R = PiService::previa($S, $rid);
        $apr = AprovacaoService::solicitar($rid, null, $R['T'], $R['manuais'], 'Pedido do próprio admin', 'FM · teste', self::$u['teste.admin']);
        self::assertSame(0, \App\Helpers\Menu::aprovacoesPendentes(), 'o contador do menu não conta o próprio pedido');
        try {
            AprovacaoService::responder($apr, true, 'ok');
            self::fail('administrador não pode aprovar o próprio pedido');
        } catch (\App\Core\BusinessException $e) {
            self::assertStringContainsString('Quem pede não pode aprovar', $e->getMessage());
        }
        $this->como('teste.diretor');
        self::assertGreaterThanOrEqual(1, \App\Helpers\Menu::aprovacoesPendentes(), 'diretoria vê o pedido do admin');
    }

    public function testOperacaoFaturamentoPelaFicha(): void
    {
        $a = $this->piEnviada();
        $this->como('teste.conf');
        PiWorkflow::aprovar($a['id'], array_map(fn ($i) => (int) $i['id'], PiWorkflow::checklist()));
        $this->entrar('teste.conf');
        self::assertSame(200, $this->req('GET', '/faturamento')->status());
        $this->req('POST', '/pis/' . $a['id'] . '/acao/programar');
        $this->req('POST', '/pis/' . $a['id'] . '/acao/veicular');
        $this->req('POST', '/pis/' . $a['id'] . '/acao/finalizar', ['data_fim' => '2026-12-31']);
        self::assertSame('Pronta para faturamento', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]));
        $this->req('POST', '/pis/' . $a['id'] . '/acao/faturar', ['nf_numero' => '1', 'nf_valor' => '150,00']);
        self::assertSame('Pronta para faturamento', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]), 'Conferência não fatura');
        $this->entrar('teste.fin');
        $this->req('POST', '/pis/' . $a['id'] . '/acao/faturar', ['nf_numero' => 'NF 1', 'nf_valor' => '150,00']);
        self::assertSame('Faturada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]));
        self::assertStringContainsString('diferente da PI', $this->req('GET', '/faturamento')->body() . (string) Database::value("SELECT evento FROM pi_historico WHERE pi_id = ? ORDER BY id DESC LIMIT 1", [$a['id']]));
        $this->req('POST', '/pis/' . $a['id'] . '/acao/encerrar');
        self::assertSame('Encerrada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$a['id']]));
    }

    public function testReservasEProjetosPelasTelas(): void
    {
        $this->entrar('teste.admin');
        $r = $this->req('POST', '/projetos', ['codigo' => 'TELA1', 'nome' => 'Projeto da Tela', 'data_limite_venda' => date('Y-m-d', strtotime('+10 days')), 'veiculos' => [$this->veiculo('FM')],
            'cotas' => [['id' => 0, 'nome' => 'Única', 'valor' => '1.000,00', 'quantidade_total' => 1], ['nome' => '', 'valor' => '', 'quantidade_total' => '']]]);
        self::assertSame(302, $r->status());
        $pid = (int) Database::value("SELECT id FROM projetos_especiais WHERE nome = 'Projeto da Tela'");
        self::assertMatchesRegularExpression('/^PROJ-' . date('Y') . '-\d{4}$/', (string) Database::value('SELECT codigo FROM projetos_especiais WHERE id = ?', [$pid]), 'código gerado pelo CRM, não o digitado');
        self::assertSame(1000.0, (float) Database::value('SELECT valor FROM projetos_cotas WHERE projeto_id = ?', [$pid]));
        self::assertSame(200, $this->req('GET', '/projetos')->status());
        self::assertSame(200, $this->req('GET', '/projetos/' . $pid)->status());
        self::assertSame(200, $this->req('GET', '/projetos/' . $pid . '/editar')->status());
        $cota = (int) Database::value('SELECT id FROM projetos_cotas WHERE projeto_id = ?', [$pid]);
        $this->como('teste.exec');
        $S = $this->estadoBase('FM');
        $S['prj'] = [['id' => $pid, 'cota' => $cota, 'q' => 1]];
        $res = ReservaService::reservar($S, null);
        $this->entrar('teste.exec');
        $tela = $this->req('GET', '/reservas');
        self::assertSame(200, $tela->status());
        self::assertStringContainsString('Abrir e gerar PI', $tela->body());
        $ab = $this->req('POST', '/reservas/' . $res['id'] . '/abrir');
        self::assertStringContainsString('/pis/nova?rascunho=', $ab->headers()['Location']);
        $this->entrar('teste.exec2');
        $this->req('POST', '/reservas/' . $res['id'] . '/liberar');
        self::assertSame('ativa', Database::value('SELECT situacao FROM reservas WHERE id = ?', [$res['id']]), 'outro vendedor não libera');
        $this->entrar('teste.exec');
        $this->req('POST', '/reservas/' . $res['id'] . '/liberar');
        self::assertSame('liberada', Database::value('SELECT situacao FROM reservas WHERE id = ?', [$res['id']]));
        $this->entrar('teste.exec');
        self::assertSame(403, $this->req('GET', '/projetos/novo')->status(), 'executivo não cadastra projeto');
    }

    public function testDezenoveRelatoriosEPrivacidadeDoRanking(): void
    {
        $a = $this->piEnviada();
        $this->piEnviada('teste.exec3');
        $this->como('teste.conf');
        PiWorkflow::devolver($a['id'], ['D05'], 'Valores divergentes');
        $cat = RelatorioService::catalogo();
        self::assertCount(19, $cat);
        $this->entrar('teste.admin');
        foreach (array_keys($cat) as $k) {
            foreach (['diaria', 'semanal', 'mensal'] as $v) {
                $r = $this->req('GET', '/relatorios/' . $k, [], ['visao' => $v, 'de' => date('Y-m-01'), 'ate' => date('Y-m-t')]);
                self::assertSame(200, $r->status(), "{$k}/{$v}: " . strip_tags(substr($r->body(), 0, 200)));
                self::assertStringNotContainsString('Algo deu errado', $r->body(), $k);
            }
        }
        $dl = $this->req('GET', '/relatorios/vendas_veiculo/excel', [], ['visao' => 'mensal']);
        self::assertSame(200, $dl->status());
        self::assertStringContainsString('Relatorio_Vendas_por_veiculo', $dl->headers()['Content-Disposition']);
        $mot = RelatorioService::executar('motivos_devolucao', RelatorioService::filtros(['visao' => 'mensal']));
        self::assertSame('D05', $mot['linhas'][0]['codigo']);
        self::assertSame(1, (int) $mot['linhas'][0]['qtd']);
        // executivo vê só os próprios números no ranking
        $this->como('teste.exec');
        $rk = RelatorioService::executar('vendas_executivo', RelatorioService::filtros(['visao' => 'mensal', 'de' => '2020-01-01', 'ate' => '2030-12-31']));
        self::assertSame(['Executivo de Teste'], array_column($rk['linhas'], 'grupo'));
        self::assertStringContainsString('só os próprios números', $rk['nota']);
        $this->como('teste.gerente');
        $rk = RelatorioService::executar('vendas_executivo', RelatorioService::filtros(['visao' => 'mensal', 'de' => '2020-01-01', 'ate' => '2030-12-31']));
        self::assertNotContains('Executivo de Outra Equipe', array_column($rk['linhas'], 'grupo'), 'gerente vê a própria equipe');
    }

    public function testAnexoProtegidoPorPermissao(): void
    {
        $a = $this->piEnviada();
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $this->como('teste.exec');
        $id = AnexoService::enviar($a['id'], 'aceite', ['name' => 'aceite cliente.pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK]);
        $an = Database::first('SELECT * FROM anexos WHERE id = ?', [$id]);
        self::assertSame('application/pdf', $an['mime']);
        self::assertStringNotContainsString('aceite', $an['caminho'], 'nome aleatório no servidor');
        self::assertFileExists(App::root() . '/storage/uploads/' . $an['caminho']);
        $this->entrar('teste.conf');
        self::assertSame(200, $this->req('GET', '/anexos/' . $id)->status());
        $this->entrar('teste.exec3');
        self::assertSame(403, $this->req('GET', '/anexos/' . $id)->status(), 'outra equipe não baixa');
        $falso = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($falso, "<?php echo 'x';");
        $this->como('teste.exec');
        try {
            AnexoService::enviar($a['id'], 'aceite', ['name' => 'aceite.pdf', 'tmp_name' => $falso, 'error' => UPLOAD_ERR_OK]);
            self::fail('script disfarçado de PDF');
        } catch (\App\Core\BusinessException $e) {
            self::assertStringContainsString('Tipo de arquivo não aceito', $e->getMessage());
        }
        @unlink(App::root() . '/storage/uploads/' . $an['caminho']);
        @unlink($tmp);
        @unlink($falso);
    }

    public function testRotinasEPrazos(): void
    {
        $a = $this->piEnviada();
        Database::query("UPDATE pi_historico SET criado_em = '2026-11-20 16:30:00' WHERE pi_id = ?", [$a['id']]);
        $this->como('teste.admin');
        $r = RotinasService::executar(false);
        self::assertGreaterThanOrEqual(1, $r['conferencias_atrasadas']);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND chave = ?", [self::$u['teste.conf'], 'sla-conf-' . $a['id'] . '-' . date('Y-m-d')]));
        RotinasService::executar(false);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND chave = ?", [self::$u['teste.conf'], 'sla-conf-' . $a['id'] . '-' . date('Y-m-d')]), 'não repete no mesmo dia');
        \App\Helpers\Calendario::definirFeriados([]);
        self::assertSame('2026-11-20 23:59:59', SlaService::prazoConferencia('2026-11-20 10:00:00'), 'até 15h: mesmo dia');
        self::assertSame('2026-11-23 12:00:00', SlaService::prazoConferencia('2026-11-20 16:30:00'), 'depois das 15h de sexta: segunda 12h');
        self::assertSame('2026-11-23 23:59:59', SlaService::prazoCorrecao('2026-11-20 09:00:00'), '1 dia útil');
    }

    public function testBackupRestritoAoAdministrador(): void
    {
        $this->entrar('teste.exec');
        self::assertSame(403, $this->req('GET', '/backup')->status());
        $this->entrar('teste.admin');
        self::assertSame(200, $this->req('GET', '/backup')->status());
        $trav = $this->req('GET', '/backup/..%2F..%2F.env');
        self::assertNotSame(200, $trav->status(), 'caminho fora da pasta é recusado');
        self::assertStringNotContainsString('DB_PASSWORD', $trav->body());
        $r = $this->req('GET', '/backup/crm_20000101_000000.sql.gz');
        self::assertSame(302, $r->status(), 'backup inexistente volta para a lista com aviso');
        try {
            \App\Services\BackupService::caminho('../.env');
            self::fail('nome fora do padrão deveria ser recusado');
        } catch (\App\Core\BusinessException $e) {
            self::assertSame('Arquivo de backup inválido.', $e->getMessage());
        }
    }
}
