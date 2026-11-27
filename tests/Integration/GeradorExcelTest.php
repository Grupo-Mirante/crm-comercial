<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\ExcelPiService;
use App\Services\PdfPiService;
use App\Services\PiService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

/**
 * Fase 4 — Gerador de PI pela API (rascunho, cálculo, geração), Excel (RN-46/RN-47), ficha, impressão, lista e permissões.
 */
final class GeradorExcelTest extends TestCase
{
    private function entrar(string $login): void
    {
        $_SESSION = ['_csrf' => 'tok'];
        Auth::reset();
        Auth::entrar(Database::first('SELECT * FROM usuarios WHERE login = ?', [$login]));
    }

    /** @param array<string,mixed> $json */
    private function req(string $method, string $path, array $json = [], array $query = [], array $post = []): Response
    {
        $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'phpunit',
            'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html', 'HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => $json ? 'application/json' : 'application/x-www-form-urlencoded'];
        Auth::reset();
        return App::handle(new Request($method, $path, $query, $post + ($json ? [] : ['_token' => 'tok']), [], $server, $json ? (string) json_encode($json) : ''));
    }

    /** @return array<string,mixed> */
    private function j(Response $r): array
    {
        return json_decode($r->body(), true) ?? [];
    }

    private function estado(string $prefixo): array
    {
        $S = $this->estadoBase($prefixo);
        switch ($prefixo) {
            case 'TV':
                $S['ins'] = [['s' => 'HORA', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1, '2' => 1]]]];
                break;
            case 'FM':
                $S['pat'] = [['s' => 'ACOREC', 'cot' => 1]];
                $S['ins'] = [['s' => 'ACOREC', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 2]]]];
                break;
            case 'NEWS':
                $S['ins'] = [['s' => 'MNHOJE', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]]];
                break;
            case 'G1':
                $S['dig'] = [['s' => 'G1PAT', 'ini' => '2026-12-01', 'fim' => '2026-12-15'], ['s' => 'G1BB', 'ent' => 100000]];
                break;
            case 'IMI':
                $S['avi'] = [['s' => 'IBAL', 'mat' => 'Balanço', 'col' => 2, 'cm' => 10, 'pub' => 1]];
                $S['dig'] = [['s' => 'IPOST', 'qtd' => 2]];
                $S['f']['texto'] = 'Texto aprovado pelo cliente.';
                break;
        }
        $S['ent'] = [['tipo' => 'Post em rede social', 'esp' => 'Post', 'qtd' => 1, 'area' => 'Marketing', 'brief' => 'BR-1', 'jur' => 'Não se aplica']];
        return $S;
    }

    public function testFluxoPelaApiRascunhoCalculoGeracaoEExcelDosCincoVeiculos(): void
    {
        $this->entrar('teste.exec');
        foreach (['TV', 'FM', 'NEWS', 'G1', 'IMI'] as $pf) {
            $S = $this->estado($pf);
            $r = $this->req('POST', '/api/rascunhos', ['estado' => $S]);
            self::assertSame(200, $r->status(), $r->body());
            $rid = $this->j($r)['id'];
            $c = $this->j($this->req('POST', '/api/pis/calcular', ['estado' => $S, 'rascunho' => $rid]));
            self::assertTrue($c['ok']);
            $liq = $c['r']['T']['liq'];
            $S['parc'] = [['venc' => '2027-01-29', 'val' => round($liq, 2)]];
            $c = $this->j($this->req('POST', '/api/pis/calcular', ['estado' => $S, 'rascunho' => $rid]));
            self::assertSame(0, $c['r']['pend'], $pf . ': ' . json_encode(array_filter($c['r']['C'], fn ($x) => $x['st'] !== 'OK'), JSON_UNESCAPED_UNICODE));
            $g = $this->j($this->req('POST', '/api/pis/gerar', ['estado' => $S, 'rascunho' => $rid]));
            self::assertTrue($g['ok'], $pf);
            $pi = $g['pis'][0];
            self::assertSame('Enviada', $pi['situacao']);
            self::assertStringStartsWith($pf . '-', $pi['numero']);
            self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM pi_rascunhos WHERE id = ?', [$rid]), 'rascunho apagado após gerar');

            // Excel: nome, página A4 horizontal em 1 página, fórmulas que fecham com o valor gravado
            [$arq, $nome] = ExcelPiService::gerarArquivo($pi['id']);
            self::assertSame('PI_' . $pi['numero'] . '_Cliente_Teste_Comercio_Ltda.xlsx', $nome);
            $ws = IOFactory::load($arq)->getActiveSheet();
            unlink($arq);
            self::assertSame(PageSetup::ORIENTATION_LANDSCAPE, $ws->getPageSetup()->getOrientation());
            self::assertSame(PageSetup::PAPERSIZE_A4, $ws->getPageSetup()->getPaperSize());
            self::assertSame(1, $ws->getPageSetup()->getFitToHeight());
            $achou = false;
            $textos = '';
            foreach ($ws->getRowIterator() as $row) {
                $n = $row->getRowIndex();
                $textos .= ' ' . $ws->getCell("A{$n}")->getValue();
                if ($ws->getCell("AP{$n}")->getValue() === 'VALOR LÍQUIDO') {
                    $achou = true;
                    $cel = $ws->getCell('AP' . ($n + 1));
                    self::assertStringStartsWith('=', (string) $cel->getValue(), 'líquido é fórmula');
                    self::assertEqualsWithDelta((float) Database::value('SELECT valor_liquido FROM pis WHERE id = ?', [$pi['id']]), $cel->getCalculatedValue(), 0.01, $pf);
                }
            }
            self::assertTrue($achou);
            foreach (['CAMPANHA', 'VALORES', 'PAGAMENTO E OBSERVAÇÕES', 'CLÁUSULAS', 'ENTREGAS ESPECIAIS'] as $faixa) {
                self::assertStringContainsString($faixa, $textos, "faixa {$faixa} ({$pf})");
            }
            self::assertStringContainsString('INCANCELÁVEL', $textos, 'cláusulas fixas (RN-47)');

            // ficha, impressão e download pela rota
            self::assertSame(200, $this->req('GET', '/pis/' . $pi['id'])->status());
            $imp = $this->req('GET', '/pis/' . $pi['id'] . '/imprimir');
            self::assertSame(200, $imp->status());
            self::assertStringContainsString('size: A4 landscape', $imp->body());
            $dl = $this->req('GET', '/pis/' . $pi['id'] . '/excel');
            self::assertSame(200, $dl->status());
            self::assertStringContainsString($nome, $dl->headers()['Content-Disposition']);

            // PDF: uma única página A4 paisagem
            [$arqPdf, $nomePdf] = PdfPiService::gerarArquivo((int) $pi['id']);
            $bytes = (string) file_get_contents($arqPdf);
            unlink($arqPdf);
            self::assertSame(substr($nome, 0, -5) . '.pdf', $nomePdf);
            self::assertStringStartsWith('%PDF', $bytes);
            self::assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $bytes), "PDF em uma página ({$pf})");
            $pdf = $this->req('GET', '/pis/' . $pi['id'] . '/pdf');
            self::assertSame(200, $pdf->status());
            self::assertSame('application/pdf', $pdf->headers()['Content-Type']);
            self::assertStringContainsString($nomePdf, $pdf->headers()['Content-Disposition']);
        }
        self::assertSame(5, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'pi_excel'"));
        self::assertSame(5, (int) Database::value("SELECT COUNT(*) FROM logs_auditoria WHERE acao = 'pi_pdf'"));
    }

    public function testRascunhoComPendenciaGeraArquivoRascunho(): void
    {
        $this->entrar('teste.exec');
        $S = $this->estado('FM');
        $S['f']['ac_nome'] = '';
        $g = PiService::gerar($S)['pis'][0];
        [$arq, $nome] = ExcelPiService::gerarArquivo($g['id']);
        unlink($arq);
        self::assertStringEndsWith('_RASCUNHO.xlsx', $nome);
    }

    public function testTvDivididaPelaApiENumeracaoPorAnoDoMes(): void
    {
        $this->entrar('teste.exec');
        $S = $this->estadoBase('TV', ['ini' => '2026-12-20', 'fim' => '2027-01-10']);
        $S['ins'] = [['s' => 'BPRA', 'dur' => 30, 'gm' => ['2026-12' => ['21' => 1], '2027-01' => ['4' => 1]]]];
        $g = $this->j($this->req('POST', '/api/pis/gerar', ['estado' => $S]));
        self::assertCount(2, $g['pis']);
        self::assertStringStartsWith('TV-2026-', $g['pis'][0]['numero']);
        self::assertStringStartsWith('TV-2027-', $g['pis'][1]['numero'], 'PI de janeiro numerada no ano do mês');
        self::assertSame($g['pis'][0]['numero'] . ' + ' . $g['pis'][1]['numero'], $g['grupo']);
    }

    public function testPermissoesDoGerador(): void
    {
        $this->entrar('teste.exec');
        $rid = $this->j($this->req('POST', '/api/rascunhos', ['estado' => $this->estado('FM')]))['id'];
        $g = PiService::gerar($this->fecharParcela($this->estado('FM'), 6237 + 153.3))['pis'][0];
        $this->entrar('teste.exec3');
        self::assertSame(403, $this->req('POST', '/api/rascunhos/' . $rid, ['estado' => []])->status(), 'rascunho de outra pessoa');
        self::assertSame(403, $this->req('GET', '/pis/' . $g['id'])->status(), 'PI de outra equipe');
        self::assertSame(403, $this->req('GET', '/pis/' . $g['id'] . '/excel')->status());
        $this->entrar('teste.conf');
        self::assertSame(403, $this->req('GET', '/pis/nova')->status(), 'Conferência não monta PI');
        self::assertSame(403, $this->req('POST', '/api/pis/gerar', ['estado' => $this->estado('FM')])->status());
        self::assertSame(200, $this->req('GET', '/pis/' . $g['id'])->status(), 'Conferência vê todas');
        $this->entrar('teste.exec');
        $l = $this->req('GET', '/pis', [], ['situacao' => 'Enviada', 'numero' => 'FM-']);
        self::assertSame(200, $l->status());
        self::assertStringContainsString($g['numero'], $l->body());
    }

    public function testAbrirParaCorrigirRenovarESubstituirPelaTela(): void
    {
        $this->entrar('teste.exec');
        $g = PiService::gerar($this->fecharParcela($this->estado('FM'), 6237 + 153.3))['pis'][0];
        $r = $this->req('POST', '/pis/' . $g['id'] . '/abrir/renovar');
        self::assertSame(302, $r->status());
        self::assertMatchesRegularExpression('#/pis/nova\?rascunho=\d+$#', $r->headers()['Location']);
        $rid = (int) substr($r->headers()['Location'], strrpos($r->headers()['Location'], '=') + 1);
        $tela = $this->req('GET', '/pis/nova', [], ['rascunho' => (string) $rid]);
        self::assertSame(200, $tela->status());
        self::assertStringContainsString('Renovação: PI nova, com número novo', $tela->body());
        self::assertSame(302, $this->req('POST', '/pis/' . $g['id'] . '/abrir/substituir')->status());
        self::assertSame(404, $this->req('POST', '/pis/' . $g['id'] . '/abrir/qualquer')->status());
        // cancelar pela ficha: executivo não cancela PI Enviada
        $c = $this->req('POST', '/pis/' . $g['id'] . '/acao/cancelar', [], [], ['motivo' => 'teste de cancelamento']);
        self::assertSame(403, $c->status());
        $this->entrar('teste.gerente');
        $c = $this->req('POST', '/pis/' . $g['id'] . '/acao/cancelar', [], [], ['motivo' => 'Cliente desistiu']);
        self::assertSame(302, $c->status());
        self::assertSame('Cancelada', Database::value('SELECT situacao FROM pis WHERE id = ?', [$g['id']]));
    }

    public function testSolicitarAprovacaoVaiAoGestorDoExecutivo(): void
    {
        $this->entrar('teste.diretor');
        $S = $this->estado('FM');
        $S['f']['executivo_id'] = self::$u['teste.exec'];
        $S['ins'][0]['desc'] = 10;
        $rid = $this->j($this->req('POST', '/api/rascunhos', ['estado' => $S]))['id'];
        $a = $this->j($this->req('POST', '/api/pis/aprovacao', ['estado' => $S, 'rascunho' => $rid, 'justificativa' => 'Cliente novo']));
        self::assertTrue($a['ok']);
        self::assertStringContainsString('Gerente de Teste', $a['mensagem']);
        self::assertSame(self::$u['teste.exec'], (int) Database::value('SELECT solicitante_id FROM pi_aprovacoes WHERE id = ?', [$a['id']]));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND tipo = 'aprovacao'", [self::$u['teste.gerente']]));
    }

    public function testCatalogoDoVeiculo(): void
    {
        $this->entrar('teste.exec');
        $c = $this->j($this->req('GET', '/api/catalogo', ['x' => 1], ['veiculo' => (string) $this->veiculo('IMI'), 'emissao' => '2026-11-25']));
        self::assertSame(2026, $c['ano']);
        self::assertSame('MA', $c['tabelas'][0]['c']);
        self::assertNotEmpty(array_filter($c['formatos'], fn ($f) => $f['legal'] && $f['s'] === 'IBAL'));
        self::assertNotEmpty($c['projetos'], 'Projetos 01/02 de teste valem para todas as empresas');
    }
}
