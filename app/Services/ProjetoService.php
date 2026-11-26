<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\ValidationException;
use Closure;

/**
 * Projetos especiais vendidos por cotas.
 *  RN-32 cadastro com código, nome, prazo, empresas (ao menos uma) e cotas (nome, valor, quantidade)
 *  RN-33 venda bloqueada depois da data limite (data de hoje)
 *  RN-34 venda bloqueada quando a quantidade passa do saldo; a cota é travada no banco antes de gravar
 *  RN-35 projeto só vale nas empresas marcadas
 *  RN-36 toda PI gerada desconta (inclusive Rascunho); Cancelada/Substituída devolve; reserva de outro vendedor desconta
 *  RN-39 não reduz a quantidade abaixo do já vendido; excluir não apaga PIs
 *  RN-50 acompanhamento: Aberto, Esgotado, Encerrado (prazo)
 */
final class ProjetoService
{
    public const SITUACOES_QUE_DEVOLVEM = ['Cancelada', 'Substituída'];

    /** @return array<int,array<string,mixed>> projetos ativos (não excluídos) por id, com veículos e cotas */
    public static function ativos(): array
    {
        $out = [];
        foreach (Database::all('SELECT * FROM projetos_especiais WHERE excluido_em IS NULL AND ativo = 1 ORDER BY data_limite_venda, nome') as $p) {
            $p['veiculos'] = [];
            $p['cotas'] = [];
            $out[(int) $p['id']] = $p;
        }
        if (!$out) {
            return [];
        }
        [$in, $par] = Database::in(array_keys($out));
        foreach (Database::all("SELECT projeto_id, veiculo_id FROM projetos_empresas WHERE projeto_id IN {$in}", $par) as $r) {
            $out[(int) $r['projeto_id']]['veiculos'][] = (int) $r['veiculo_id'];
        }
        [$in, $par] = Database::in(array_keys($out));
        foreach (Database::all("SELECT * FROM projetos_cotas WHERE projeto_id IN {$in} ORDER BY ordem, id", $par) as $c) {
            $out[(int) $c['projeto_id']]['cotas'][] = $c;
        }
        return $out;
    }

    /**
     * Cotas vendidas em PIs ativas, por cota (exceto as PIs informadas — a própria PI sendo gerada de novo, RN-36).
     * @param list<int> $excluirPis
     * @return array<int,int>
     */
    public static function vendidas(array $excluirPis = []): array
    {
        [$in, $par] = Database::in($excluirPis ?: [0], 'ex');
        [$inS, $parS] = Database::in(self::SITUACOES_QUE_DEVOLVEM, 'st');
        $rows = Database::all(
            "SELECT i.projeto_cota_id, SUM(i.quantidade) q FROM pi_itens i JOIN pis p ON p.id = i.pi_id
              WHERE i.bloco = 'projeto' AND i.projeto_cota_id IS NOT NULL AND p.situacao NOT IN {$inS} AND p.id NOT IN {$in}
              GROUP BY i.projeto_cota_id",
            $par + $parS
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['projeto_cota_id']] = (int) $r['q'];
        }
        return $out;
    }

    /** Cotas reservadas (24 h) e ainda válidas, por cota, exceto a reserva informada (RN-66). @return array<int,int> */
    public static function reservadas(?int $excluirReserva = null): array
    {
        $rows = Database::all(
            "SELECT ri.projeto_cota_id, SUM(ri.quantidade) q FROM reservas_itens ri JOIN reservas r ON r.id = ri.reserva_id
              WHERE ri.tipo = 'cota' AND r.situacao = 'ativa' AND r.expira_em > NOW() AND r.id <> ?
              GROUP BY ri.projeto_cota_id",
            [$excluirReserva ?? 0]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['projeto_cota_id']] = (int) $r['q'];
        }
        return $out;
    }

    /** @param list<int> $excluirPis @return Closure(int):int */
    public static function ocupadasFn(array $excluirPis = [], ?int $excluirReserva = null): Closure
    {
        $v = self::vendidas($excluirPis);
        $r = self::reservadas($excluirReserva);
        return fn (int $cotaId): int => ($v[$cotaId] ?? 0) + ($r[$cotaId] ?? 0);
    }

    /**
     * RN-34 — trava as cotas no banco (ordem fixa por id, evitando deadlock). Usar dentro de transação.
     * @param list<int> $cotaIds
     */
    public static function travarCotas(array $cotaIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $cotaIds))));
        if (!$ids) {
            return;
        }
        sort($ids);
        [$in, $par] = Database::in($ids);
        Database::all("SELECT id FROM projetos_cotas WHERE id IN {$in} ORDER BY id FOR UPDATE", $par);
    }

    /**
     * Situação de um projeto (RN-50).
     * @param array<string,mixed> $P
     * @param array<int,int> $vendidas
     * @param array<int,int> $reservadas
     * @return array<string,mixed>
     */
    public static function situacao(array $P, array $vendidas, array $reservadas, ?string $hoje = null): array
    {
        $cotas = [];
        $total = $vend = $res = $disp = 0;
        $faltaValor = 0.0;
        foreach ($P['cotas'] as $c) {
            $q = (int) $c['quantidade_total'];
            $v = $vendidas[(int) $c['id']] ?? 0;
            $r = $reservadas[(int) $c['id']] ?? 0;
            $d = max(0, $q - $v - $r);
            $cotas[] = ['id' => (int) $c['id'], 'nome' => $c['nome'], 'valor' => (float) $c['valor'], 'total' => $q, 'vendidas' => $v, 'reservadas' => $r, 'disponiveis' => $d];
            $total += $q;
            $vend += min($v, $q);
            $res += $r;
            $disp += $d;
            $faltaValor += $d * (float) $c['valor'];
        }
        $venc = $P['data_limite_venda'] && ($hoje ?? date('Y-m-d')) > $P['data_limite_venda'];
        $esg = $total > 0 && $vend >= $total;
        return [
            'cotas' => $cotas, 'total' => $total, 'vendidas' => $vend, 'reservadas' => $res, 'disponiveis' => $disp,
            'vencido' => $venc, 'esgotado' => $esg, 'aberto' => !$venc && $disp > 0,
            'situacao' => $venc ? 'Encerrado (prazo)' : ($esg ? 'Esgotado' : ($disp ? 'Aberto' : 'Reservado')),
            'falta_vender' => $venc ? 0.0 : $faltaValor, 'nao_vendido' => $venc ? $faltaValor : 0.0,
        ];
    }

    /**
     * Cadastro/edição (RN-32, RN-39).
     * @param array<string,mixed> $dados
     */
    public static function salvar(?int $id, array $dados): int
    {
        $codigo = mb_strtoupper(trim((string) ($dados['codigo'] ?? '')));
        $nome = trim((string) ($dados['nome'] ?? ''));
        $limite = data_iso((string) ($dados['data_limite_venda'] ?? ''));
        $veiculos = array_values(array_unique(array_map('intval', (array) ($dados['veiculos'] ?? []))));
        $cotas = [];
        foreach ((array) ($dados['cotas'] ?? []) as $c) {
            if (!is_array($c) || trim((string) ($c['nome'] ?? '')) === '') {
                continue;
            }
            $cotas[] = [
                'id' => (int) ($c['id'] ?? 0),
                'nome' => trim((string) $c['nome']),
                'valor' => valor_decimal($c['valor'] ?? 0),
                'quantidade_total' => (int) ($c['quantidade_total'] ?? 0),
            ];
        }
        $e = [];
        if (!preg_match('/^[A-Z0-9_-]{2,20}$/', $codigo)) {
            $e['codigo'] = 'Código com 2 a 20 caracteres (letras, números, hífen).';
        } elseif (Database::value('SELECT id FROM projetos_especiais WHERE codigo = ? AND id <> ?', [$codigo, $id ?? 0])) {
            $e['codigo'] = 'Já existe projeto com este código.';
        }
        if ($nome === '') {
            $e['nome'] = 'Informe o nome do projeto.';
        }
        if ($limite === '') {
            $e['data_limite_venda'] = 'Informe a data limite de venda.';
        }
        if (!$veiculos) {
            $e['veiculos'] = 'Marque ao menos uma empresa em que o projeto vale (RN-32).';
        }
        if (!$cotas) {
            $e['cotas'] = 'Cadastre ao menos uma cota (nome, valor e quantidade).';
        }
        $nomes = [];
        foreach ($cotas as $i => $c) {
            if ($c['quantidade_total'] < 1) {
                $e["cotas.{$i}"] = "Cota {$c['nome']}: quantidade mínima 1.";
            }
            if ($c['valor'] < 0) {
                $e["cotas.{$i}"] = "Cota {$c['nome']}: valor inválido.";
            }
            if (isset($nomes[mb_strtolower($c['nome'])])) {
                $e["cotas.{$i}"] = "Cota {$c['nome']} repetida.";
            }
            $nomes[mb_strtolower($c['nome'])] = true;
        }
        if ($e) {
            throw new ValidationException($e);
        }
        return Database::transaction(function () use ($id, $codigo, $nome, $limite, $veiculos, $cotas, $dados) {
            $antes = $id ? Database::first('SELECT * FROM projetos_especiais WHERE id = ?', [$id]) : null;
            $reg = ['codigo' => $codigo, 'nome' => $nome, 'descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null, 'data_limite_venda' => $limite, 'ativo' => isset($dados['ativo']) ? (int) (bool) $dados['ativo'] : 1, 'updated_by' => Auth::id()];
            if ($id) {
                if (!$antes || $antes['excluido_em'] !== null) {
                    throw new BusinessException('Projeto não encontrado.');
                }
                Database::update('projetos_especiais', $reg, ['id' => $id]);
            } else {
                $id = Database::insert('projetos_especiais', $reg + ['created_by' => Auth::id()]);
            }
            Database::query('DELETE FROM projetos_empresas WHERE projeto_id = ?', [$id]);
            foreach ($veiculos as $v) {
                Database::insert('projetos_empresas', ['projeto_id' => $id, 'veiculo_id' => $v]);
            }
            $existentes = [];
            foreach (Database::all('SELECT * FROM projetos_cotas WHERE projeto_id = ? FOR UPDATE', [$id]) as $c) {
                $existentes[(int) $c['id']] = $c;
            }
            $vendidas = self::vendidas();
            $reservadas = self::reservadas();
            $mantidas = [];
            foreach ($cotas as $ordem => $c) {
                if ($c['id'] && isset($existentes[$c['id']])) {
                    $usado = ($vendidas[$c['id']] ?? 0) + ($reservadas[$c['id']] ?? 0);
                    if ($c['quantidade_total'] < $usado) {
                        throw new BusinessException("Cota {$c['nome']}: não é possível reduzir para {$c['quantidade_total']}; já há {$usado} vendida(s) ou reservada(s) (RN-39).");
                    }
                    Database::update('projetos_cotas', ['nome' => $c['nome'], 'valor' => $c['valor'], 'quantidade_total' => $c['quantidade_total'], 'ordem' => $ordem], ['id' => $c['id']]);
                    $mantidas[] = $c['id'];
                } else {
                    $mantidas[] = Database::insert('projetos_cotas', ['projeto_id' => $id, 'nome' => $c['nome'], 'valor' => $c['valor'], 'quantidade_total' => $c['quantidade_total'], 'ordem' => $ordem]);
                }
            }
            foreach ($existentes as $cid => $c) {
                if (!in_array($cid, $mantidas, true)) {
                    $usos = (int) Database::value('SELECT COUNT(*) FROM pi_itens WHERE projeto_cota_id = ?', [$cid]) + (int) Database::value('SELECT COUNT(*) FROM reservas_itens WHERE projeto_cota_id = ?', [$cid]);
                    if ($usos) {
                        throw new BusinessException("A cota {$c['nome']} já foi vendida ou reservada e não pode ser removida; reduza a quantidade ou encerre o projeto.");
                    }
                    Database::query('DELETE FROM projetos_cotas WHERE id = ?', [$cid]);
                }
            }
            Auditoria::registrar($antes ? 'projeto_alterado' : 'projeto_criado', 'projeto', $id, "Projeto {$codigo} — {$nome}", $antes, $reg + ['veiculos' => $veiculos, 'cotas' => $cotas]);
            return (int) $id;
        });
    }

    /** RN-39 — exclusão lógica: some das listas de venda, mas as PIs geradas continuam intactas. */
    public static function excluir(int $id): void
    {
        $p = Database::first('SELECT * FROM projetos_especiais WHERE id = ? AND excluido_em IS NULL', [$id]);
        if (!$p) {
            throw new BusinessException('Projeto não encontrado.');
        }
        Database::query('UPDATE projetos_especiais SET excluido_em = NOW(), ativo = 0, updated_by = ? WHERE id = ?', [Auth::id(), $id]);
        Auditoria::registrar('projeto_excluido', 'projeto', $id, "Projeto {$p['codigo']} excluído (PIs mantidas)", $p, null);
    }
}
