<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\ValidationException;
use App\Validators\DocumentoValidator;

/**
 * Cadastro de clientes (anunciantes).
 *  RN-61/RN-62 CPF/CNPJ (inclusive alfanumérico) validado e formatado
 *  RN-63 único por documento; ao gerar a PI, o cliente entra no cadastro ou tem os dados atualizados
 *  RN-64 busca por razão social, fantasia ou documento; excluir não altera as PIs (os dados ficam gravados na PI)
 */
final class ClienteService
{
    public const CAMPOS = ['razao_social', 'nome_fantasia', 'documento', 'inscricao_estadual', 'endereco', 'bairro', 'cidade', 'uf', 'cep', 'contato', 'telefone', 'email_faturamento', 'codigo_interno', 'situacao_cadastral'];

    /** @param array<string,mixed> $dados @return array<string,mixed> */
    public static function validar(array $dados, ?int $id): array
    {
        $doc = DocumentoValidator::verificar((string) ($dados['documento'] ?? ''));
        $d = [];
        foreach (self::CAMPOS as $c) {
            $d[$c] = trim((string) ($dados[$c] ?? '')) ?: null;
        }
        $e = [];
        if ($d['razao_social'] === null) {
            $e['razao_social'] = 'Informe a razão social (ou o nome completo, se pessoa física).';
        }
        if (!$doc['ok']) {
            $e['documento'] = $doc['vazio'] ? 'Informe o CNPJ ou CPF.' : 'CNPJ/CPF inválido: os dígitos verificadores não conferem.';
        } else {
            $dup = Database::first('SELECT id, razao_social FROM clientes WHERE documento = ? AND id <> ?', [$doc['chave'], $id ?? 0]);
            if ($dup) {
                $e['documento'] = 'Este documento já está cadastrado para ' . $dup['razao_social'] . '.';
            }
        }
        if ($d['email_faturamento'] !== null && !filter_var($d['email_faturamento'], FILTER_VALIDATE_EMAIL)) {
            $e['email_faturamento'] = 'E-mail de faturamento inválido.';
        }
        if ($d['uf'] !== null && !preg_match('/^[A-Z]{2}$/', strtoupper($d['uf']))) {
            $e['uf'] = 'UF com 2 letras.';
        }
        if ($d['cep'] !== null && !preg_match('/^\d{5}-?\d{3}$/', $d['cep'])) {
            $e['cep'] = 'CEP no formato 00000-000.';
        }
        $sit = $d['situacao_cadastral'] ?? 'Ativo';
        if (!in_array($sit, ['Ativo', 'Em análise', 'Bloqueado', 'Inativo'], true)) {
            $e['situacao_cadastral'] = 'Situação inválida.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        $d['documento'] = $doc['chave'];
        $d['documento_formatado'] = $doc['formatado'];
        $d['tipo_documento'] = $doc['tipo'];
        $d['uf'] = $d['uf'] !== null ? strtoupper($d['uf']) : null;
        $d['cep'] = $d['cep'] !== null ? substr(preg_replace('/\D/', '', $d['cep']) ?? '', 0, 5) . '-' . substr(preg_replace('/\D/', '', $d['cep']) ?? '', 5, 3) : null;
        $d['situacao_cadastral'] = $sit;
        return $d;
    }

    /** @param array<string,mixed> $dados */
    public static function salvar(?int $id, array $dados): int
    {
        $d = self::validar($dados, $id);
        if ($id) {
            $antes = Database::first('SELECT * FROM clientes WHERE id = ?', [$id]);
            if (!$antes) {
                throw new BusinessException('Cliente não encontrado.');
            }
            Database::update('clientes', $d + ['updated_by' => Auth::id()], ['id' => $id]);
            Auditoria::registrar('cliente_alterado', 'cliente', $id, 'Cliente ' . $d['razao_social'], $antes, $d);
            return $id;
        }
        $id = Database::insert('clientes', $d + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);
        Auditoria::registrar('cliente_criado', 'cliente', $id, 'Cliente ' . $d['razao_social'], null, $d);
        return $id;
    }

    /**
     * RN-63 — ao gerar a PI, o cliente entra no cadastro ou tem os dados atualizados (só com razão social e documento válido).
     * @param array<string,mixed> $f campos do formulário da PI
     */
    public static function upsertDaPi(array $f): ?int
    {
        $doc = DocumentoValidator::verificar((string) ($f['cli_cnpj'] ?? ''));
        $razao = trim((string) ($f['cli_razao'] ?? ''));
        if (!$doc['ok'] || $razao === '') {
            return null;
        }
        $dados = [
            'razao_social' => $razao, 'nome_fantasia' => $f['cli_fant'] ?? '', 'documento' => $doc['chave'], 'inscricao_estadual' => $f['cli_ie'] ?? '',
            'endereco' => $f['cli_end'] ?? '', 'bairro' => $f['cli_bairro'] ?? '', 'cidade' => $f['cli_cid'] ?? '', 'uf' => $f['cli_uf'] ?? '', 'cep' => $f['cli_cep'] ?? '',
            'contato' => $f['cli_contato'] ?? '', 'telefone' => $f['cli_tel'] ?? '', 'email_faturamento' => $f['cli_email'] ?? '', 'codigo_interno' => $f['cli_cod'] ?? '',
        ];
        $existente = Database::first('SELECT * FROM clientes WHERE documento = ?', [$doc['chave']]);
        try {
            $dados['situacao_cadastral'] = $existente['situacao_cadastral'] ?? 'Ativo';
            $d = self::validar($dados, $existente ? (int) $existente['id'] : null);
        } catch (ValidationException) {
            // Dados incompletos/inválidos (ex.: CEP digitado errado): grava só o essencial, sem bloquear a PI.
            $d = ['razao_social' => $razao, 'documento' => $doc['chave'], 'documento_formatado' => $doc['formatado'], 'tipo_documento' => $doc['tipo']];
        }
        if ($existente) {
            $novo = array_filter($d, fn ($v) => $v !== null && $v !== '');
            $mudou = array_filter($novo, fn ($v, $k) => (string) ($existente[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH);
            if ($mudou) {
                Database::update('clientes', $mudou + ['updated_by' => Auth::id(), 'ativo' => 1], ['id' => (int) $existente['id']]);
                Auditoria::registrar('cliente_atualizado_pi', 'cliente', (int) $existente['id'], 'Dados atualizados ao gerar a PI', array_intersect_key($existente, $mudou), $mudou);
            }
            return (int) $existente['id'];
        }
        $id = Database::insert('clientes', $d + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);
        Auditoria::registrar('cliente_criado_pi', 'cliente', $id, 'Cliente cadastrado ao gerar a PI', null, $d);
        return $id;
    }

    /** RN-64 — com PIs vinculadas, o cliente é desativado (os dados da PI não mudam); sem PIs, é excluído. */
    public static function excluir(int $id): string
    {
        $c = Database::first('SELECT * FROM clientes WHERE id = ?', [$id]);
        if (!$c) {
            throw new BusinessException('Cliente não encontrado.');
        }
        $usos = (int) Database::value('SELECT COUNT(*) FROM pis WHERE cliente_id = ?', [$id]) + (int) Database::value('SELECT COUNT(*) FROM reservas WHERE cliente_id = ?', [$id]);
        if ($usos) {
            Database::query("UPDATE clientes SET ativo = 0, situacao_cadastral = 'Inativo', updated_by = ? WHERE id = ?", [Auth::id(), $id]);
            Auditoria::registrar('cliente_desativado', 'cliente', $id, 'Cliente desativado (possui PIs)', $c, null);
            return 'desativado';
        }
        Database::query('DELETE FROM clientes WHERE id = ?', [$id]);
        Auditoria::registrar('cliente_excluido', 'cliente', $id, 'Cliente excluído', $c, null);
        return 'excluido';
    }

    /** @return list<array<string,mixed>> */
    public static function buscar(string $termo, int $limite = 15): array
    {
        $t = trim($termo);
        if ($t === '') {
            return [];
        }
        $doc = preg_replace('/[^0-9A-Za-z]/', '', $t) ?? '';
        return Database::all(
            'SELECT id, razao_social, nome_fantasia, documento_formatado, cidade, uf, contato, telefone, email_faturamento FROM clientes
              WHERE ativo = 1 AND (razao_social LIKE :q OR nome_fantasia LIKE :q2 OR (:doc <> \'\' AND documento LIKE :d) OR codigo_interno = :cod)
              ORDER BY razao_social LIMIT ' . max(1, min(50, $limite)),
            ['q' => '%' . $t . '%', 'q2' => '%' . $t . '%', 'doc' => $doc, 'd' => strtoupper($doc) . '%', 'cod' => $t]
        );
    }
}
