<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\Calculo\CalculadoraPi;
use App\Services\Calculo\Catalogo;
use App\Services\Calculo\ContextoCalculo;

/**
 * Reservas de 24 horas.
 *  RN-65 reserva tudo o que está na PI em montagem (cotas, programas e dias da grade, patrocínios, formatos digitais);
 *        exige veículo, cliente e ao menos uma linha
 *  RN-66 cota reservada sai da disponibilidade dos outros na hora, com as mesmas travas da venda (prazo, empresa, saldo)
 *  RN-67 vale 24 horas a partir de quando foi feita; atualizar não renova o prazo
 *  RN-68 espaço reservado por outro vendedor aparece como AVISO (não bloqueia: não há limite de capacidade por programa)
 *  RN-69 gerar a PI converte a reserva em venda; o dono pode liberar antes
 *  RN-70 lista de reservas ativas; só o dono abre ou libera (e o administrador, para casos de afastamento — decisão Q10)
 */
final class ReservaService
{
    /**
     * @param array<string,mixed> $S estado do formulário
     * @return array<string,mixed> reserva gravada
     */
    public static function reservar(array $S, ?int $rascunhoId): array
    {
        if (!Auth::can('reservas.criar')) {
            throw new HttpException(403, 'Seu perfil não faz reservas.');
        }
        $S = CalculadoraPi::normalizar($S);
        $f = $S['f'];
        $vid = (int) ($f['veic'] ?? 0);
        if (!$vid) {
            throw new BusinessException('Escolha o veículo antes de reservar.');
        }
        if (trim((string) ($f['cli_razao'] ?? '')) === '') {
            throw new BusinessException('Informe o cliente (razão social) antes de reservar.');
        }
        $resId = (int) ($f['res'] ?? 0) ?: null;
        $existente = $resId ? Database::first("SELECT * FROM reservas WHERE id = ? AND situacao = 'ativa' AND expira_em > NOW()", [$resId]) : null;
        if ($existente && (int) $existente['usuario_id'] !== Auth::id()) {
            throw new HttpException(403, 'Só o dono pode alterar esta reserva.');
        }
        return Database::transaction(function () use ($S, $f, $vid, $existente, $rascunhoId) {
            $cotas = array_map(fn ($l) => (int) ($l['cota'] ?? 0), $S['prj']);
            ProjetoService::travarCotas($cotas);
            $ctx = new ContextoCalculo(new Catalogo(), ProjetoService::ativos(), ProjetoService::ocupadasFn([], $existente ? (int) $existente['id'] : null));
            $R = (new CalculadoraPi($ctx))->calcular($S);
            $itens = self::itens($S, $R);
            if (!$itens) {
                throw new BusinessException('Inclua ao menos uma linha de mídia ou cota de projeto para reservar.');
            }
            if ($R['projBlock']) {
                $msgs = array_map(fn ($c) => $c['fix'], array_filter($R['C'], fn ($c) => in_array($c['code'], CalculadoraPi::BLOQUEIAM, true) && $c['st'] !== 'OK'));
                throw new BusinessException('Reserva bloqueada: ' . implode(' ', $msgs), 'reserva_bloqueada');
            }
            $resumo = mb_substr(implode(' · ', array_map(fn ($i) => $i['descricao'], $itens)), 0, 600);
            $estado = json_encode($S, JSON_UNESCAPED_UNICODE);
            $horas = Configuracao::inteiro('reserva_validade_horas', 24);
            $cliente = ClienteService::upsertDaPi($f);
            if ($existente) {
                $id = (int) $existente['id'];
                Database::query('UPDATE reservas SET veiculo_id = ?, cliente_id = ?, cliente_nome = ?, data_inicio = ?, data_fim = ?, resumo_itens = ?, estado_formulario = ? WHERE id = ?',
                    [$vid, $cliente, trim((string) $f['cli_razao']), ($f['ini'] ?? '') ?: null, ($f['fim'] ?? '') ?: null, $resumo, $estado, $id]);
                Database::query('DELETE FROM reservas_itens WHERE reserva_id = ?', [$id]);
            } else {
                $codigo = 'R' . strtoupper(base_convert((string) time(), 10, 36)) . strtoupper(bin2hex(random_bytes(2)));
                $id = Database::insert('reservas', [
                    'codigo' => $codigo, 'usuario_id' => Auth::id(), 'veiculo_id' => $vid, 'cliente_id' => $cliente, 'cliente_nome' => trim((string) $f['cli_razao']),
                    'data_inicio' => ($f['ini'] ?? '') ?: null, 'data_fim' => ($f['fim'] ?? '') ?: null,
                    'reservada_em' => date('Y-m-d H:i:s'), 'expira_em' => date('Y-m-d H:i:s', time() + $horas * 3600),
                    'situacao' => 'ativa', 'resumo_itens' => $resumo, 'estado_formulario' => $estado,
                ]);
            }
            foreach ($itens as $i) {
                $dias = $i['dias_json'] ?? null;
                unset($i['dias_json']);
                Database::insert('reservas_itens', ['reserva_id' => $id] + $i + ['dias' => $dias]);
            }
            $reg = Database::first('SELECT * FROM reservas WHERE id = ?', [$id]);
            Auditoria::registrar($existente ? 'reserva_atualizada' : 'reserva_criada', 'reserva', $id, ($existente ? 'Reserva atualizada (prazo mantido): ' : 'Reserva de 24 h: ') . $resumo);
            if ($rascunhoId) {
                Database::query('UPDATE pi_rascunhos SET estado = JSON_SET(estado, \'$.f.res\', ?) WHERE id = ? AND usuario_id = ?', [$id, $rascunhoId, Auth::id()]);
            }
            return $reg;
        }, 4);
    }

    /**
     * Itens que a reserva segura.
     * @param array<string,mixed> $S @param array<string,mixed> $R
     * @return list<array<string,mixed>>
     */
    public static function itens(array $S, array $R): array
    {
        $out = [];
        foreach ($R['prj'] as $x) {
            if ($x['cota_id']) {
                $out[] = ['tipo' => 'cota', 'projeto_cota_id' => $x['cota_id'], 'quantidade' => $x['q'], 'sigla' => $x['codigo'], 'descricao' => "{$x['nome']} {$x['cota']} ×{$x['q']}"];
            }
        }
        foreach ($R['lines'] as $x) {
            if (!$x['n'] || !$x['p']) {
                continue;
            }
            $dias = [];
            foreach (($x['l']['gm'] ?? []) as $mk => $g) {
                $ds = array_values(array_map('intval', array_keys(array_filter((array) $g, fn ($q) => (int) $q > 0))));
                if ($ds) {
                    sort($ds);
                    $dias[$mk] = $ds;
                }
            }
            $out[] = ['tipo' => 'insercao', 'programa_id' => $x['p']['id'], 'sigla' => $x['p']['sigla'], 'quantidade' => $x['n'], 'descricao' => "{$x['p']['sigla']} {$x['n']} ins.",
                'data_inicio' => ($S['f']['ini'] ?? '') ?: null, 'data_fim' => ($S['f']['fim'] ?? '') ?: null, '__dias' => $dias];
        }
        foreach ($R['pat'] as $x) {
            if ($x['programa_id']) {
                $out[] = ['tipo' => 'patrocinio', 'programa_id' => $x['programa_id'], 'sigla' => $x['sigla'], 'quantidade' => $x['cot'], 'descricao' => "{$x['sigla']} patroc.",
                    'mes_ref' => $x['mk'] ?: null, 'data_inicio' => ($S['f']['ini'] ?? '') ?: null, 'data_fim' => ($S['f']['fim'] ?? '') ?: null];
            }
        }
        foreach ($R['dig'] as $x) {
            if ($x['formato_id']) {
                $out[] = ['tipo' => 'digital', 'formato_id' => $x['formato_id'], 'sigla' => $x['sigla'], 'quantidade' => (int) max(1, $x['qtd']), 'descricao' => $x['sigla'],
                    'data_inicio' => $x['ini'] ?: null, 'data_fim' => $x['fim'] ?: null];
            }
        }
        // dias da grade vão em JSON
        return array_map(function ($i) {
            if (isset($i['__dias'])) {
                $i['dias_json'] = json_encode($i['__dias']);
                unset($i['__dias']);
            }
            return $i;
        }, $out);
    }

    /**
     * RN-68 — avisos de espaço reservado por outro vendedor (mesmo programa e dia, mesmo patrocínio no período/mês,
     * mesmo formato digital em período que se cruza). Não bloqueia.
     * @param array<string,mixed> $S @param array<string,mixed> $R
     * @return list<array<string,mixed>>
     */
    public static function conflitos(array $S, array $R): array
    {
        $vid = (int) ($S['f']['veic'] ?? 0);
        if (!$vid) {
            return [];
        }
        $proprio = (int) ($S['f']['res'] ?? 0);
        $outras = Database::all(
            "SELECT ri.*, r.id AS rid, r.usuario_id, r.cliente_nome, r.expira_em, u.nome AS executivo FROM reservas_itens ri JOIN reservas r ON r.id = ri.reserva_id JOIN usuarios u ON u.id = r.usuario_id
              WHERE r.situacao = 'ativa' AND r.expira_em > NOW() AND r.veiculo_id = ? AND r.id <> ? AND ri.tipo <> 'cota'",
            [$vid, $proprio]
        );
        if (!$outras) {
            return [];
        }
        $meus = self::itens($S, $R);
        $sobrepoe = fn ($a1, $b1, $a2, $b2) => $a1 && $b1 && $a2 && $b2 && $a1 <= $b2 && $a2 <= $b1;
        $res = [];
        foreach ($meus as $e) {
            foreach ($outras as $o) {
                if ($o['tipo'] !== $e['tipo'] || strtoupper((string) $o['sigla']) !== strtoupper((string) $e['sigla'])) {
                    continue;
                }
                $hit = '';
                if ($e['tipo'] === 'insercao') {
                    $md = json_decode((string) ($e['dias_json'] ?? '{}'), true) ?: [];
                    $od = json_decode((string) ($o['dias'] ?? '{}'), true) ?: [];
                    $ds = [];
                    foreach ($md as $mk => $arr) {
                        foreach (array_intersect($arr, $od[$mk] ?? []) as $d) {
                            $ds[] = $d . '/' . substr((string) $mk, 5);
                        }
                    }
                    if ($ds) {
                        $hit = 'dias ' . implode(', ', array_slice($ds, 0, 8)) . (count($ds) > 8 ? '…' : '');
                    }
                } elseif ($e['tipo'] === 'patrocinio') {
                    if (($e['mes_ref'] ?? null) && $o['mes_ref'] ? $e['mes_ref'] === $o['mes_ref'] : $sobrepoe($e['data_inicio'] ?? null, $e['data_fim'] ?? null, $o['data_inicio'], $o['data_fim'])) {
                        $hit = 'mesmo período';
                    }
                } elseif ($sobrepoe($e['data_inicio'] ?? null, $e['data_fim'] ?? null, $o['data_inicio'], $o['data_fim'])) {
                    $hit = data_br($o['data_inicio']) . ' a ' . data_br($o['data_fim']);
                }
                if ($hit !== '') {
                    $res[] = ['sigla' => $e['sigla'], 'onde' => $hit, 'executivo' => $o['executivo'], 'cliente' => $o['cliente_nome'], 'ate' => $o['expira_em'], 'reserva_id' => (int) $o['rid']];
                }
            }
        }
        return $res;
    }

    public static function liberar(int $id): void
    {
        $r = Database::first('SELECT * FROM reservas WHERE id = ?', [$id]);
        if (!$r || $r['situacao'] !== 'ativa') {
            throw new BusinessException('Reserva não encontrada ou já encerrada.');
        }
        if ((int) $r['usuario_id'] !== Auth::id() && !Auth::isAdmin()) {
            throw new HttpException(403, 'Só o dono libera a própria reserva (RN-70).');
        }
        Database::query("UPDATE reservas SET situacao = 'liberada', encerrada_em = NOW(), encerrada_por = ? WHERE id = ? AND situacao = 'ativa'", [Auth::id(), $id]);
        Database::query("UPDATE pi_rascunhos SET estado = JSON_SET(estado, '$.f.res', '') WHERE JSON_UNQUOTE(JSON_EXTRACT(estado, '$.f.res')) = ?", [(string) $id]);
        Auditoria::registrar('reserva_liberada', 'reserva', $id, 'Reserva liberada antes do prazo: ' . $r['resumo_itens']);
    }

    /** RN-69 — a reserva vira venda (chamado dentro da transação de geração da PI). */
    public static function converter(int $id, int $piId): void
    {
        Database::query("UPDATE reservas SET situacao = 'convertida', encerrada_em = NOW(), encerrada_por = ?, pi_id = ? WHERE id = ? AND situacao = 'ativa'", [Auth::id(), $piId, $id]);
    }

    /** Rotina automática (cron): marca como expiradas as reservas vencidas. */
    public static function expirarVencidas(): int
    {
        $ids = array_map('intval', array_column(Database::all("SELECT id FROM reservas WHERE situacao = 'ativa' AND expira_em <= NOW()"), 'id'));
        if (!$ids) {
            return 0;
        }
        [$in, $par] = Database::in($ids);
        $n = Database::query("UPDATE reservas SET situacao = 'expirada', encerrada_em = NOW() WHERE situacao = 'ativa' AND id IN {$in}", $par)->rowCount();
        foreach ($ids as $id) {
            Auditoria::registrar('reserva_expirada', 'reserva', $id, 'Reserva expirou após 24 horas sem PI gerada', null, null, null, 'sistema');
        }
        return $n;
    }

    /** @return list<array<string,mixed>> reservas ativas, com tempo restante */
    public static function ativas(): array
    {
        $rows = Database::all(
            "SELECT r.*, v.nome AS veiculo, u.nome AS executivo, TIMESTAMPDIFF(MINUTE, NOW(), r.expira_em) AS minutos_restantes
               FROM reservas r JOIN veiculos v ON v.id = r.veiculo_id JOIN usuarios u ON u.id = r.usuario_id
              WHERE r.situacao = 'ativa' AND r.expira_em > NOW() ORDER BY r.expira_em"
        );
        foreach ($rows as &$r) {
            $m = max(0, (int) $r['minutos_restantes']);
            $r['tempo_restante'] = $m >= 60 ? intdiv($m, 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'min' : $m . ' min';
            $r['meu'] = (int) $r['usuario_id'] === Auth::id();
        }
        return $rows;
    }
}
