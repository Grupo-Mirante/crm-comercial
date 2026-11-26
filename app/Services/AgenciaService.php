<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\ValidationException;
use App\Validators\DocumentoValidator;

/**
 * Cadastro de agências.
 *  RN-42 venda por agência exige nome, CNPJ, % de comissão e carta de credenciamento; comissão = negociado × %
 *  RN-79 único por CNPJ; ao gerar a PI, a agência entra ou é atualizada no cadastro
 *  RN-80 carta vencida na data de emissão = D08 (a PI sai como Rascunho)
 */
final class AgenciaService
{
    /** @param array<string,mixed> $dados @return array<string,mixed> */
    public static function validar(array $dados, ?int $id): array
    {
        $doc = DocumentoValidator::verificar((string) ($dados['cnpj'] ?? ''));
        $campos = ['razao_social', 'carta_numero', 'endereco', 'cidade', 'uf', 'telefone', 'contato', 'email'];
        $d = [];
        foreach ($campos as $c) {
            $d[$c] = trim((string) ($dados[$c] ?? '')) ?: null;
        }
        $d['comissao_padrao'] = valor_decimal($dados['comissao_padrao'] ?? 0);
        $val = trim((string) ($dados['carta_validade'] ?? ''));
        $d['carta_validade'] = $val !== '' ? (data_iso($val) ?: null) : null;
        $e = [];
        if ($d['razao_social'] === null) {
            $e['razao_social'] = 'Informe a razão social da agência.';
        }
        if (!$doc['ok'] || $doc['tipo'] !== 'CNPJ') {
            $e['cnpj'] = $doc['vazio'] ? 'Informe o CNPJ da agência.' : 'CNPJ inválido: os dígitos verificadores não conferem.';
        } else {
            $dup = Database::first('SELECT id, razao_social FROM agencias WHERE cnpj = ? AND id <> ?', [$doc['chave'], $id ?? 0]);
            if ($dup) {
                $e['cnpj'] = 'Este CNPJ já está cadastrado para ' . $dup['razao_social'] . '.';
            }
        }
        if ($d['comissao_padrao'] < 0 || $d['comissao_padrao'] > 100) {
            $e['comissao_padrao'] = 'Comissão entre 0 e 100%.';
        }
        if ($val !== '' && $d['carta_validade'] === null) {
            $e['carta_validade'] = 'Data inválida (DD/MM/AAAA).';
        }
        if ($d['email'] !== null && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'E-mail inválido.';
        }
        if ($d['uf'] !== null && !preg_match('/^[A-Za-z]{2}$/', $d['uf'])) {
            $e['uf'] = 'UF com 2 letras.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        $d['cnpj'] = $doc['chave'];
        $d['cnpj_formatado'] = $doc['formatado'];
        $d['uf'] = $d['uf'] !== null ? strtoupper($d['uf']) : null;
        return $d;
    }

    /** @param array<string,mixed> $dados */
    public static function salvar(?int $id, array $dados): int
    {
        $d = self::validar($dados, $id);
        if ($id) {
            $antes = Database::first('SELECT * FROM agencias WHERE id = ?', [$id]);
            if (!$antes) {
                throw new BusinessException('Agência não encontrada.');
            }
            Database::update('agencias', $d + ['updated_by' => Auth::id()], ['id' => $id]);
            Auditoria::registrar('agencia_alterada', 'agencia', $id, 'Agência ' . $d['razao_social'], $antes, $d);
            return $id;
        }
        $id = Database::insert('agencias', $d + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);
        Auditoria::registrar('agencia_criada', 'agencia', $id, 'Agência ' . $d['razao_social'], null, $d);
        return $id;
    }

    /** RN-79 — upsert ao gerar a PI. @param array<string,mixed> $f */
    public static function upsertDaPi(array $f): ?int
    {
        if (($f['venda'] ?? '') !== 'Agência') {
            return null;
        }
        $doc = DocumentoValidator::verificar((string) ($f['ag_cnpj'] ?? ''));
        $nome = trim((string) ($f['ag_nome'] ?? ''));
        if (!$doc['ok'] || $doc['tipo'] !== 'CNPJ' || $nome === '') {
            return null;
        }
        [$cid, $uf] = array_pad(array_map('trim', explode('/', (string) ($f['ag_cid'] ?? ''), 2)), 2, '');
        [$contato, $email] = self::separarContato((string) ($f['ag_contato'] ?? ''));
        $dados = ['razao_social' => $nome, 'cnpj' => $doc['chave'], 'comissao_padrao' => $f['ag_com'] ?? 0, 'carta_numero' => $f['ag_carta'] ?? '', 'carta_validade' => $f['ag_val'] ?? '',
            'endereco' => $f['ag_end'] ?? '', 'cidade' => $cid, 'uf' => $uf, 'telefone' => $f['ag_tel'] ?? '', 'contato' => $contato, 'email' => $email];
        $existente = Database::first('SELECT * FROM agencias WHERE cnpj = ?', [$doc['chave']]);
        try {
            $d = self::validar($dados, $existente ? (int) $existente['id'] : null);
        } catch (ValidationException) {
            $d = ['razao_social' => $nome, 'cnpj' => $doc['chave'], 'cnpj_formatado' => $doc['formatado'], 'comissao_padrao' => valor_decimal($f['ag_com'] ?? 0)];
        }
        if ($existente) {
            $novo = array_filter($d, fn ($v) => $v !== null && $v !== '');
            $mudou = array_filter($novo, fn ($v, $k) => (string) ($existente[$k] ?? '') !== (string) $v && !(is_numeric($v) && is_numeric($existente[$k] ?? null) && (float) $v === (float) $existente[$k]), ARRAY_FILTER_USE_BOTH);
            if ($mudou) {
                Database::update('agencias', $mudou + ['updated_by' => Auth::id(), 'ativo' => 1], ['id' => (int) $existente['id']]);
                Auditoria::registrar('agencia_atualizada_pi', 'agencia', (int) $existente['id'], 'Dados atualizados ao gerar a PI', array_intersect_key($existente, $mudou), $mudou);
            }
            return (int) $existente['id'];
        }
        $id = Database::insert('agencias', $d + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);
        Auditoria::registrar('agencia_criada_pi', 'agencia', $id, 'Agência cadastrada ao gerar a PI', null, $d);
        return $id;
    }

    /** "Fulano · fulano@agencia.com" → [contato, e-mail]. @return array{0:string,1:string} */
    public static function separarContato(string $txt): array
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $txt, $m)) {
            $contato = trim(str_replace($m[0], '', $txt), " ·-,;|");
            return [$contato, $m[0]];
        }
        return [trim($txt), ''];
    }

    public static function excluir(int $id): string
    {
        $a = Database::first('SELECT * FROM agencias WHERE id = ?', [$id]);
        if (!$a) {
            throw new BusinessException('Agência não encontrada.');
        }
        if ((int) Database::value('SELECT COUNT(*) FROM pis WHERE agencia_id = ?', [$id])) {
            Database::query('UPDATE agencias SET ativo = 0, updated_by = ? WHERE id = ?', [Auth::id(), $id]);
            Auditoria::registrar('agencia_desativada', 'agencia', $id, 'Agência desativada (possui PIs)', $a, null);
            return 'desativada';
        }
        Database::query('DELETE FROM agencias WHERE id = ?', [$id]);
        Auditoria::registrar('agencia_excluida', 'agencia', $id, 'Agência excluída', $a, null);
        return 'excluida';
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
            'SELECT id, razao_social, cnpj_formatado, comissao_padrao, carta_numero, carta_validade, endereco, cidade, uf, telefone, contato, email FROM agencias
              WHERE ativo = 1 AND (razao_social LIKE :q OR (:doc <> \'\' AND cnpj LIKE :d)) ORDER BY razao_social LIMIT ' . max(1, min(50, $limite)),
            ['q' => '%' . $t . '%', 'doc' => $doc, 'd' => strtoupper($doc) . '%']
        );
    }
}
