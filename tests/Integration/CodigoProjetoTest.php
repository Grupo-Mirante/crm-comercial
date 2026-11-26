<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SqlScript;
use App\Services\Auth;
use App\Services\NumeracaoService;
use App\Services\ProjetoService;
use Tests\TestCase;

/** Código automático dos projetos especiais: PROJ-ANO-SEQUÊNCIA, sequência anual, sem repetir. */
final class CodigoProjetoTest extends TestCase
{
    private function req(string $method, string $path): Response
    {
        $_SESSION = ['_csrf' => 'tok'];
        Auth::reset();
        Auth::entrar(Database::first('SELECT * FROM usuarios WHERE login = ?', ['teste.admin']));
        Auth::reset();
        return App::handle(new Request($method, $path, [], ['_token' => 'tok'], [],
            ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'phpunit']));
    }

    /** @param array<string,mixed> $extra */
    private function novo(array $extra = []): int
    {
        $this->como('teste.admin');
        return ProjetoService::salvar(null, $extra + ['nome' => 'Projeto Código', 'data_limite_venda' => date('Y-m-d', strtotime('+10 days')),
            'veiculos' => [$this->veiculo('FM')], 'cotas' => [['nome' => 'Ouro', 'valor' => '1000', 'quantidade_total' => 1]]]);
    }

    private function codigo(int $id): string
    {
        return (string) Database::value('SELECT codigo FROM projetos_especiais WHERE id = ?', [$id]);
    }

    public function testCodigoGeradoNoPadraoEmSequencia(): void
    {
        $a = $this->codigo($this->novo());
        $b = $this->codigo($this->novo());
        $ano = date('Y');
        self::assertMatchesRegularExpression("/^PROJ-{$ano}-\\d{4}$/", $a);
        self::assertMatchesRegularExpression("/^PROJ-{$ano}-\\d{4}$/", $b);
        self::assertSame((int) substr($a, -4) + 1, (int) substr($b, -4), 'o segundo recebe o número seguinte');
    }

    public function testCodigoDigitadoEIgnorado(): void
    {
        $id = $this->novo(['codigo' => 'VERAO27']);
        self::assertStringStartsWith('PROJ-', $this->codigo($id));
    }

    public function testEdicaoNaoMudaOCodigo(): void
    {
        $id = $this->novo();
        $cod = $this->codigo($id);
        $cota = (int) Database::value('SELECT id FROM projetos_cotas WHERE projeto_id = ?', [$id]);
        ProjetoService::salvar($id, ['codigo' => 'OUTRO', 'nome' => 'Projeto Renomeado', 'data_limite_venda' => date('Y-m-d', strtotime('+12 days')),
            'veiculos' => [$this->veiculo('FM')], 'cotas' => [['id' => $cota, 'nome' => 'Ouro', 'valor' => '1000', 'quantidade_total' => 2]]]);
        self::assertSame($cod, $this->codigo($id));
        self::assertSame('Projeto Renomeado', Database::value('SELECT nome FROM projetos_especiais WHERE id = ?', [$id]));
    }

    public function testSequenciaRecomecaACadaAno(): void
    {
        self::assertSame('PROJ-2098-0001', NumeracaoService::proximoProjeto(2098));
        self::assertSame('PROJ-2098-0002', NumeracaoService::proximoProjeto(2098));
        self::assertSame('PROJ-2099-0001', NumeracaoService::proximoProjeto(2099), 'ano novo começa do 0001');
        self::assertSame('PROJ-2098-0003', NumeracaoService::proximoProjeto(2098));
    }

    public function testNuncaFicaAbaixoDoMaiorCodigoGravado(): void
    {
        Database::insert('projetos_especiais', ['codigo' => 'PROJ-2097-0041', 'nome' => 'Cadastrado à mão', 'data_limite_venda' => '2097-12-31']);
        self::assertSame('PROJ-2097-0042', NumeracaoService::proximoProjeto(2097));
    }

    public function testCodigoApareceNaTelaDeEdicaoSomenteLeitura(): void
    {
        $id = $this->novo();
        $html = $this->req('GET', '/projetos/' . $id . '/editar')->body();
        self::assertStringContainsString($this->codigo($id), $html);
        self::assertDoesNotMatchRegularExpression('/name="codigo"/', $html, 'o código não é mais um campo editável');
    }

    /** A migration 002 converte os projetos já cadastrados (ano de cadastro + ordem) e guarda o código antigo. */
    public function testMigracaoConverteProjetosExistentes(): void
    {
        Database::query('DELETE FROM projetos_cotas');
        Database::query('DELETE FROM projetos_especiais');
        $ins = fn (string $cod, string $em) => Database::insert('projetos_especiais', ['codigo' => $cod, 'nome' => $cod, 'data_limite_venda' => '2026-12-31', 'created_at' => $em]);
        $a = $ins('VERAO25', '2025-01-10 10:00:00');
        $b = $ins('PROJ-2026-0001', '2026-03-01 10:00:00');
        $c = $ins('NATAL', '2026-02-01 10:00:00');
        $d = $ins('CARNAVAL', '2025-06-01 10:00:00');
        Database::query('UPDATE projetos_especiais SET codigo_anterior = NULL');

        // aplica só os comandos de dados da migration (estrutura já existe; DDL faria commit da transação do teste)
        $sql = SqlScript::split((string) file_get_contents(App::root() . '/database/migrations/002_codigo_automatico_projetos.sql'));
        $dml = array_values(array_filter($sql, fn ($s) => preg_match('/^\s*UPDATE\b/i', $s) === 1));
        self::assertCount(3, $dml);
        foreach ($dml as $s) {
            Database::connection()->exec($s);
        }

        $r = fn (int $id) => Database::first('SELECT codigo, codigo_anterior FROM projetos_especiais WHERE id = ?', [$id]);
        self::assertSame(['codigo' => 'PROJ-2025-0001', 'codigo_anterior' => 'VERAO25'], $r($a));
        self::assertSame(['codigo' => 'PROJ-2025-0002', 'codigo_anterior' => 'CARNAVAL'], $r($d));
        self::assertSame(['codigo' => 'PROJ-2026-0001', 'codigo_anterior' => 'NATAL'], $r($c));
        self::assertSame(['codigo' => 'PROJ-2026-0002', 'codigo_anterior' => 'PROJ-2026-0001'], $r($b), 'ordem de cadastro manda, mesmo para quem já parecia estar no padrão');
    }
}
