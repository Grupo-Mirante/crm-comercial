<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * RN-03 — número no formato PREFIXO-ANO-SEQUÊNCIA (TV-2026-0001), sequência anual por veículo, que nunca se repete.
 * RN-04 — dois vendedores ao mesmo tempo não recebem o mesmo número: a linha da sequência é travada com
 *         SELECT … FOR UPDATE dentro da MESMA transação que grava a PI. Se a gravação falhar, o ROLLBACK devolve
 *         o número (sem buracos). A chave única de pis.numero é a última barreira.
 */
final class NumeracaoService
{
    /**
     * Reserva o próximo número. DEVE ser chamado dentro de uma transação.
     * @return array{numero:string,ano:int,sequencial:int}
     */
    public static function proximo(int $veiculoId, int $ano): array
    {
        $pdo = Database::connection();
        if (!$pdo->inTransaction()) {
            throw new \LogicException('A numeração da PI só pode ser reservada dentro de uma transação.');
        }
        $prefixo = (string) Database::value('SELECT prefixo FROM veiculos WHERE id = ?', [$veiculoId]);
        if ($prefixo === '') {
            throw new \InvalidArgumentException('Veículo inválido para numeração.');
        }
        if (!Database::value('SELECT 1 FROM sequencias_pi WHERE veiculo_id = ? AND ano = ?', [$veiculoId, $ano])) {
            Database::query('INSERT IGNORE INTO sequencias_pi (veiculo_id, ano, ultimo_numero) VALUES (?, ?, 0)', [$veiculoId, $ano]);
        }
        $ultimo = (int) Database::value('SELECT ultimo_numero FROM sequencias_pi WHERE veiculo_id = ? AND ano = ? FOR UPDATE', [$veiculoId, $ano]);
        // Segurança extra: nunca abaixo do maior número já gravado (ex.: importação manual).
        $maior = (int) Database::value('SELECT COALESCE(MAX(sequencial), 0) FROM pis WHERE veiculo_id = ? AND ano = ?', [$veiculoId, $ano]);
        $n = max($ultimo, $maior) + 1;
        Database::query('UPDATE sequencias_pi SET ultimo_numero = ? WHERE veiculo_id = ? AND ano = ?', [$n, $veiculoId, $ano]);
        return ['numero' => self::formatar($prefixo, $ano, $n), 'ano' => $ano, 'sequencial' => $n];
    }

    /**
     * Cria a linha da sequência fora da transação de geração (evita disputa de inserção entre vendedores
     * no primeiro número do ano). Pode ser chamada quantas vezes for preciso.
     */
    public static function prepararSequencia(int $veiculoId, int $ano): void
    {
        if (!Database::connection()->inTransaction()) {
            Database::query('INSERT IGNORE INTO sequencias_pi (veiculo_id, ano, ultimo_numero) VALUES (?, ?, 0)', [$veiculoId, $ano]);
        }
    }

    public static function formatar(string $prefixo, int $ano, int $n): string
    {
        return sprintf('%s-%04d-%s', $prefixo, $ano, str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    public const PREFIXO_PROJETO = 'PROJ';

    /**
     * Próximo código de projeto especial (PROJ-2026-0001). Sequência anual que recomeça em 0001 a cada ano
     * e nunca se repete: a linha do ano é travada com SELECT … FOR UPDATE dentro da MESMA transação que grava
     * o projeto; se a gravação falhar, o ROLLBACK devolve o número. A chave única de projetos_especiais.codigo
     * é a última barreira. DEVE ser chamado dentro de uma transação.
     */
    public static function proximoProjeto(int $ano): string
    {
        if (!Database::connection()->inTransaction()) {
            throw new \LogicException('O código do projeto só pode ser reservado dentro de uma transação.');
        }
        if (!Database::value('SELECT 1 FROM sequencias_projeto WHERE ano = ?', [$ano])) {
            Database::query('INSERT IGNORE INTO sequencias_projeto (ano, ultimo_numero) VALUES (?, 0)', [$ano]);
        }
        $ultimo = (int) Database::value('SELECT ultimo_numero FROM sequencias_projeto WHERE ano = ? FOR UPDATE', [$ano]);
        // Segurança extra: nunca abaixo do maior código já gravado no ano (ex.: cadastro manual no banco).
        $prefixo = self::PREFIXO_PROJETO . '-' . sprintf('%04d', $ano) . '-';
        $maior = (int) Database::value(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)), 0) FROM projetos_especiais WHERE codigo LIKE ? AND codigo REGEXP '^[A-Z]+-[0-9]{4}-[0-9]+$'",
            [strlen($prefixo) + 1, $prefixo . '%']
        );
        $n = max($ultimo, $maior) + 1;
        Database::query('UPDATE sequencias_projeto SET ultimo_numero = ? WHERE ano = ?', [$n, $ano]);
        return self::formatar(self::PREFIXO_PROJETO, $ano, $n);
    }

    /** Cria a linha da sequência do ano fora da transação (evita disputa de inserção no primeiro projeto do ano). */
    public static function prepararSequenciaProjeto(int $ano): void
    {
        if (!Database::connection()->inTransaction()) {
            Database::query('INSERT IGNORE INTO sequencias_projeto (ano, ultimo_numero) VALUES (?, 0)', [$ano]);
        }
    }

    /** @return array{prefixo:string,ano:int,sequencial:int}|null */
    public static function decompor(string $numero): ?array
    {
        if (!preg_match('/^([A-Z0-9]+)-(\d{4})-(\d{4,})$/', $numero, $m)) {
            return null;
        }
        return ['prefixo' => $m[1], 'ano' => (int) $m[2], 'sequencial' => (int) $m[3]];
    }
}
