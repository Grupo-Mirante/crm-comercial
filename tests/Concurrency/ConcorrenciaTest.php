<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Core\Database;
use App\Services\Auth;
use App\Services\Instalacao\SeedTeste;
use App\Services\ProjetoService;
use PHPUnit\Framework\TestCase;

/**
 * RN-04 — dois (ou mais) vendedores gerando ao mesmo tempo não recebem o mesmo número.
 * RN-34 — duas pessoas não vendem a mesma última cota.
 * Cada "pessoa" é um processo PHP separado, com conexão própria ao MySQL, disparando no mesmo instante.
 */
final class ConcorrenciaTest extends TestCase
{
    /** @param list<list<string>> $comandos @return list<array<string,mixed>> */
    private function emParalelo(array $comandos): array
    {
        $procs = [];
        foreach ($comandos as $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__ . '/worker.php'], $args);
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, getenv());
            self::assertIsResource($p);
            $procs[] = [$p, $pipes];
        }
        $out = [];
        foreach ($procs as [$p, $pipes]) {
            $txt = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($p);
            $j = json_decode((string) $txt, true);
            self::assertIsArray($j, "Saída inesperada do processo: {$txt} {$err}");
            self::assertArrayNotHasKey('erro', $j, (string) ($j['erro'] ?? ''));
            $out[] = $j;
        }
        return $out;
    }

    public function testNumeracaoSimultaneaNuncaRepete(): void
    {
        $fm = (int) Database::value("SELECT id FROM veiculos WHERE prefixo = 'FM'");
        Database::query('DELETE FROM sequencias_pi WHERE veiculo_id = ? AND ano = 2099', [$fm]);
        $barreira = (string) (microtime(true) + 1.5);
        $r = $this->emParalelo(array_fill(0, 8, ['numero', (string) $fm, '2099', $barreira]));
        $nums = array_column($r, 'numero');
        sort($nums);
        self::assertCount(8, array_unique($nums), 'nenhum número repetido');
        self::assertSame(array_map(fn ($i) => sprintf('FM-2099-%04d', $i), range(1, 8)), $nums, 'sequência sem buracos');
        Database::query('DELETE FROM sequencias_pi WHERE veiculo_id = ? AND ano = 2099', [$fm]);
    }

    public function testDuasPessoasDisputandoAUltimaCota(): void
    {
        $u = SeedTeste::usuarios();
        $fm = (int) Database::value("SELECT id FROM veiculos WHERE prefixo = 'FM'");
        Auth::como($u['teste.admin']);
        $pid = ProjetoService::salvar(null, ['codigo' => 'CONC' . random_int(100, 999), 'nome' => 'Projeto Concorrência', 'data_limite_venda' => date('Y-m-d', strtotime('+3 days')),
            'veiculos' => [$fm], 'cotas' => [['nome' => 'Ouro', 'valor' => 5000, 'quantidade_total' => 1]]]);
        $cota = (int) Database::value('SELECT id FROM projetos_cotas WHERE projeto_id = ?', [$pid]);
        $estado = ['f' => ['veic' => $fm, 'tab' => 'LOC', 'tipo' => 'Nova', 'emissao' => date('Y-m-d'), 'venda' => 'Direta', 'cli_razao' => 'Cliente Teste Comércio Ltda.',
            'cli_cnpj' => '11.222.333/0001-81', 'titulo' => 'Concorrência', 'ini' => '2026-12-01', 'fim' => '2026-12-31'],
            'prj' => [['id' => $pid, 'cota' => $cota, 'q' => 1]]];
        $b64 = base64_encode((string) json_encode($estado));
        $barreira = (string) (microtime(true) + 1.5);
        $r = $this->emParalelo([
            ['gerar', (string) $u['teste.exec'], $barreira, $b64],
            ['gerar', (string) $u['teste.exec2'], $barreira, $b64],
            ['gerar', (string) $u['teste.vend'], $barreira, $b64],
        ]);
        $ok = array_values(array_filter($r, fn ($x) => $x['ok']));
        $bloq = array_values(array_filter($r, fn ($x) => !$x['ok']));
        self::assertCount(1, $ok, 'só uma pessoa leva a última cota: ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        self::assertCount(2, $bloq);
        foreach ($bloq as $b) {
            self::assertSame('projeto_bloqueado', $b['codigo']);
        }
        self::assertSame([$cota => 1], array_intersect_key(ProjetoService::vendidas(), [$cota => 1]));
        // limpeza (dados comitados pelos processos)
        Database::query('DELETE FROM pis WHERE id = ?', [$ok[0]['id']]);
        Database::query('DELETE FROM projetos_cotas WHERE projeto_id = ?', [$pid]);
        Database::query('DELETE FROM projetos_especiais WHERE id = ?', [$pid]);
        Auth::como(null);
    }

    public function testGeracoesSimultaneasDeVendedoresDiferentes(): void
    {
        $u = SeedTeste::usuarios();
        $news = (int) Database::value("SELECT id FROM veiculos WHERE prefixo = 'NEWS'");
        $estado = ['f' => ['veic' => $news, 'tab' => 'LOC', 'tipo' => 'Nova', 'emissao' => date('Y-m-d'), 'venda' => 'Direta', 'cli_razao' => 'Cliente Teste Comércio Ltda.',
            'cli_cnpj' => '11.222.333/0001-81', 'ini' => '2026-12-01', 'fim' => '2026-12-31'], 'ins' => [['s' => 'MNHOJE', 'dur' => 30, 'gm' => ['2026-12' => ['1' => 1]]]]];
        $b64 = base64_encode((string) json_encode($estado));
        $barreira = (string) (microtime(true) + 1.5);
        $r = $this->emParalelo(array_map(fn ($l) => ['gerar', (string) $u[$l], $barreira, $b64], ['teste.exec', 'teste.exec2', 'teste.vend', 'teste.exec3']));
        self::assertCount(4, array_filter($r, fn ($x) => $x['ok']));
        $nums = array_column($r, 'numero');
        self::assertCount(4, array_unique($nums), 'quatro PIs, quatro números diferentes');
        [$in, $par] = Database::in(array_column($r, 'id'));
        Database::query("DELETE FROM pis WHERE id IN {$in}", $par);
    }
}
