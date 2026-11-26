<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use Closure;

/**
 * RN-41 / RN-15 — desconto, bonificação ou preço manual exigem a aprovação do gestor, registrada no sistema
 * (decisão Q4 da Fase 1): o executivo solicita, o gestor recebe o aviso e aprova ou recusa.
 * Acima da alçada configurada (alcada_gestor_desconto_pct / alcada_gestor_bonificacao_valor), aprova a Diretoria (RACI).
 * A aprovação vale enquanto as condições da PI não passarem do que foi aprovado
 * (desconto % ≤ aprovado, bonificado ≤ aprovado e os mesmos preços manuais).
 */
final class AprovacaoService
{
    /** Precisa da diretoria? */
    public static function exigeDiretoria(float $descontoPct, float $bonificado): bool
    {
        $lim = Configuracao::decimal('alcada_gestor_desconto_pct');
        $limB = Configuracao::decimal('alcada_gestor_bonificacao_valor');
        return ($lim !== null && $descontoPct * 100 > $lim + 1e-9) || ($limB !== null && $bonificado > $limB + 0.005);
    }

    /**
     * @param array<string,mixed> $T totais da calculadora
     * @param list<array<string,mixed>> $manuais
     */
    public static function solicitar(?int $rascunhoId, ?int $piId, array $T, array $manuais, string $justificativa, string $resumo, ?int $executivoId = null): int
    {
        if (!$rascunhoId && !$piId) {
            throw new BusinessException('Salve a PI antes de pedir aprovação.');
        }
        Auth::user() ?? throw new HttpException(401);
        // A solicitação é registrada em nome do executivo dono da PI e vai para o gestor DELE
        // (quem clicou fica na auditoria, quando a gerência monta a PI por ele).
        $u = Database::first('SELECT id, nome, gestor_id FROM usuarios WHERE id = ?', [$executivoId ?? Auth::id()]) ?? throw new HttpException(404);
        $diretoria = self::exigeDiretoria((float) $T['descPct'], (float) $T['bonif']);
        Database::query("UPDATE pi_aprovacoes SET situacao = 'cancelada' WHERE situacao = 'pendente' AND ((rascunho_id IS NOT NULL AND rascunho_id = ?) OR (pi_id IS NOT NULL AND pi_id = ?))", [$rascunhoId, $piId]);
        $id = Database::insert('pi_aprovacoes', [
            'pi_id' => $piId, 'rascunho_id' => $rascunhoId, 'tipo' => $diretoria ? 'diretoria' : 'comercial', 'situacao' => 'pendente',
            'solicitante_id' => (int) $u['id'], 'desconto_pct' => round((float) $T['descPct'], 6), 'desconto_nominal' => round((float) $T['desc'], 2),
            'valor_bonificado' => round((float) $T['bonif'], 2), 'precos_manuais' => json_encode($manuais, JSON_UNESCAPED_UNICODE),
            'resumo' => mb_substr($resumo, 0, 500), 'justificativa' => mb_substr(trim($justificativa), 0, 500) ?: null,
        ]);
        $destinos = $diretoria ? Notificacoes::usuariosComPermissao('aprovacoes.aprovar_diretoria') : array_filter([(int) ($u['gestor_id'] ?? 0)]);
        if (!$destinos) {
            $destinos = Notificacoes::usuariosComPermissao('aprovacoes.aprovar_diretoria');
        }
        Notificacoes::enviarVarios(array_map('intval', $destinos), 'aprovacao', 'Aprovação pedida: ' . $resumo,
            sprintf('%s pediu aprovação de desconto de %s%% (R$ %s), bonificado R$ %s%s.', $u['nome'], number_format((float) $T['descPct'] * 100, 1, ',', '.'),
                number_format((float) $T['desc'], 2, ',', '.'), number_format((float) $T['bonif'], 2, ',', '.'), $manuais ? ' e ' . count($manuais) . ' preço(s) manual(is)' : ''),
            '/aprovacoes');
        Auditoria::registrar('aprovacao_solicitada', 'aprovacao', $id, $resumo, null, ['desconto_pct' => $T['descPct'], 'bonificado' => $T['bonif'], 'manuais' => $manuais, 'diretoria' => $diretoria]);
        return $id;
    }

    /** Quem pode responder: gestor do solicitante (aprovacoes.aprovar) ou diretoria (aprovacoes.aprovar_diretoria). */
    public static function podeResponder(array $a): bool
    {
        if ($a['tipo'] === 'diretoria') {
            return Auth::can('aprovacoes.aprovar_diretoria');
        }
        if (Auth::can('aprovacoes.aprovar_diretoria')) {
            return true;
        }
        if (!Auth::can('aprovacoes.aprovar')) {
            return false;
        }
        $gestor = (int) Database::value('SELECT gestor_id FROM usuarios WHERE id = ?', [(int) $a['solicitante_id']]);
        return $gestor === Auth::id();
    }

    public static function responder(int $id, bool $aprovar, string $resposta): void
    {
        Database::transaction(function () use ($id, $aprovar, $resposta) {
            $a = Database::first('SELECT * FROM pi_aprovacoes WHERE id = ? FOR UPDATE', [$id]);
            if (!$a || $a['situacao'] !== 'pendente') {
                throw new BusinessException('Esta solicitação não está mais pendente.');
            }
            if (!self::podeResponder($a)) {
                throw new HttpException(403, 'Você não pode responder a esta aprovação (alçada ou equipe).');
            }
            if ((int) $a['solicitante_id'] === Auth::id()) {
                throw new BusinessException('Quem pede não pode aprovar a própria solicitação.');
            }
            if (!$aprovar && trim($resposta) === '') {
                throw new BusinessException('Diga o motivo da recusa.');
            }
            $u = Auth::user();
            Database::query('UPDATE pi_aprovacoes SET situacao = ?, aprovador_id = ?, aprovador_nome = ?, resposta = ?, respondida_em = NOW() WHERE id = ?',
                [$aprovar ? 'aprovada' : 'recusada', (int) $u['id'], (string) $u['nome'], mb_substr(trim($resposta), 0, 500) ?: null, $id]);
            Notificacoes::enviar((int) $a['solicitante_id'], 'aprovacao', ($aprovar ? 'Aprovado: ' : 'Recusado: ') . $a['resumo'],
                ($aprovar ? 'O gestor aprovou as condições comerciais.' : 'O gestor recusou: ' . $resposta), $a['pi_id'] ? '/pis/' . $a['pi_id'] : '/pis/nova?rascunho=' . $a['rascunho_id']);
            Auditoria::registrar($aprovar ? 'aprovacao_aprovada' : 'aprovacao_recusada', 'aprovacao', $id, (string) $a['resumo'], ['situacao' => 'pendente'], ['situacao' => $aprovar ? 'aprovada' : 'recusada', 'resposta' => $resposta]);
        });
    }

    /**
     * Verificador usado pela calculadora: a aprovação mais recente do rascunho/PI cobre as condições atuais?
     * @return Closure(array<string,mixed>,list<array<string,mixed>>):array{ok:bool,nome:?string,data:?string,situacao:string,id:?int}
     */
    public static function verificador(?int $rascunhoId, array $piIds): Closure
    {
        $piIds = array_values(array_filter(array_map('intval', $piIds)));
        return function (array $T, array $manuais) use ($rascunhoId, $piIds): array {
            [$in, $par] = Database::in($piIds ?: [0], 'pi');
            $par['r'] = $rascunhoId ?? 0;
            $lista = Database::all("SELECT * FROM pi_aprovacoes WHERE situacao IN ('pendente','aprovada','recusada') AND (rascunho_id = :r OR pi_id IN {$in}) ORDER BY id DESC", $par);
            $ultima = $lista[0] ?? null;
            foreach ($lista as $a) {
                if ($a['situacao'] !== 'aprovada') {
                    continue;
                }
                if (self::cobre($a, $T, $manuais)) {
                    return ['ok' => true, 'nome' => (string) $a['aprovador_nome'], 'data' => substr((string) $a['respondida_em'], 0, 10), 'situacao' => 'aprovada', 'id' => (int) $a['id']];
                }
            }
            return ['ok' => false, 'nome' => null, 'data' => null, 'situacao' => $ultima['situacao'] ?? 'nenhuma', 'id' => $ultima ? (int) $ultima['id'] : null];
        };
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $T @param list<array<string,mixed>> $manuais */
    public static function cobre(array $a, array $T, array $manuais): bool
    {
        if ((float) $T['descPct'] > (float) $a['desconto_pct'] + 0.000001) {
            return false;
        }
        if ((float) $T['bonif'] > (float) $a['valor_bonificado'] + 0.005) {
            return false;
        }
        $aprovados = [];
        foreach (json_decode((string) ($a['precos_manuais'] ?? '[]'), true) ?: [] as $m) {
            $aprovados[] = $m['sigla'] . '|' . number_format((float) $m['preco'], 2, '.', '');
        }
        foreach ($manuais as $m) {
            $k = $m['sigla'] . '|' . number_format((float) $m['preco'], 2, '.', '');
            $pos = array_search($k, $aprovados, true);
            if ($pos === false) {
                return false;
            }
            unset($aprovados[$pos]);
        }
        return true;
    }

    /** Liga as aprovações do rascunho à PI gerada. @param list<int> $piIds */
    public static function vincularPi(int $rascunhoId, array $piIds): void
    {
        if (!$piIds) {
            return;
        }
        Database::query('UPDATE pi_aprovacoes SET pi_id = ? WHERE rascunho_id = ? AND pi_id IS NULL', [$piIds[0], $rascunhoId]);
    }
}
