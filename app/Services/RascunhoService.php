<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Services\Calculo\CalculadoraPi;
use App\Validators\DocumentoValidator;

/**
 * PI em montagem ("Em negociação"): salvamento automático do formulário, ainda sem número.
 * Cada rascunho é só de quem o criou. Ao gerar a PI, o rascunho é apagado e o estado passa para a PI.
 */
final class RascunhoService
{
    /** Estado inicial de uma Nova PI (como blank() do Gerador original). @return array<string,mixed> */
    public static function estadoInicial(?int $clienteId = null): array
    {
        $f = ['tipo' => 'Nova', 'emissao' => date('Y-m-d'), 'venda' => 'Direta', 'pcob' => 'São Luís', 'cli_uf' => 'MA', 'forma' => 'Boleto', 'prazo' => '30'];
        if ($clienteId) {
            $c = Database::first('SELECT * FROM clientes WHERE id = ? AND ativo = 1', [$clienteId]);
            if ($c) {
                $f += ['cli_razao' => $c['razao_social'], 'cli_fant' => $c['nome_fantasia'], 'cli_cnpj' => $c['documento_formatado'], 'cli_ie' => $c['inscricao_estadual'],
                    'cli_end' => $c['endereco'], 'cli_bairro' => $c['bairro'], 'cli_cid' => $c['cidade'], 'cli_cep' => $c['cep'], 'cli_contato' => $c['contato'],
                    'cli_tel' => $c['telefone'], 'cli_email' => $c['email_faturamento'], 'cli_cod' => $c['codigo_interno']];
                $f['cli_uf'] = $c['uf'] ?: 'MA';
            }
        }
        [$S] = PiService::aplicarExecutivo(['f' => $f]);
        return CalculadoraPi::normalizar($S + ['ins' => [['gm' => []]]]);
    }

    /** @return array<string,mixed> */
    public static function obter(int $id): array
    {
        $r = Database::first('SELECT * FROM pi_rascunhos WHERE id = ?', [$id]);
        if (!$r) {
            throw new HttpException(404, 'Rascunho não encontrado (talvez a PI já tenha sido gerada).');
        }
        if ((int) $r['usuario_id'] !== Auth::id()) {
            throw new HttpException(403, 'Este rascunho é de outra pessoa.');
        }
        $r['estado'] = json_decode((string) $r['estado'], true) ?: [];
        return $r;
    }

    /** @param array<string,mixed> $S */
    public static function criar(array $S): int
    {
        if (!Auth::can('pis.criar')) {
            throw new HttpException(403);
        }
        $S = self::limpar($S);
        return Database::insert('pi_rascunhos', self::colunas($S) + ['usuario_id' => (int) Auth::id(), 'modo' => 'nova', 'estado' => json_encode($S, JSON_UNESCAPED_UNICODE)]);
    }

    /** @param array<string,mixed> $S */
    public static function salvar(int $id, array $S): void
    {
        self::obter($id);
        $S = self::limpar($S);
        Database::update('pi_rascunhos', self::colunas($S) + ['estado' => json_encode($S, JSON_UNESCAPED_UNICODE)], ['id' => $id]);
    }

    public static function excluir(int $id): void
    {
        $r = self::obter($id);
        Database::query('DELETE FROM pi_rascunhos WHERE id = ?', [$id]);
        Auditoria::registrar('rascunho_descartado', 'rascunho', $id, 'PI em montagem descartada: ' . ($r['cliente_nome'] ?: 'sem cliente'));
    }

    /** @return list<array<string,mixed>> */
    public static function meus(): array
    {
        return Database::all(
            'SELECT r.id, r.modo, r.cliente_nome, r.titulo, r.updated_at, r.pi_id, v.nome AS veiculo, p.numero
               FROM pi_rascunhos r LEFT JOIN veiculos v ON v.id = r.veiculo_id LEFT JOIN pis p ON p.id = r.pi_id
              WHERE r.usuario_id = ? ORDER BY r.updated_at DESC LIMIT 50',
            [(int) Auth::id()]
        );
    }

    /**
     * Limita o tamanho e garante a estrutura (o estado vem do navegador).
     * @param array<string,mixed> $S
     * @return array<string,mixed>
     */
    public static function limpar(array $S): array
    {
        $S = CalculadoraPi::normalizar($S);
        $limites = ['ins' => 60, 'pat' => 40, 'dig' => 60, 'avi' => 40, 'prj' => 20, 'ent' => 30, 'parc' => 60];
        foreach ($limites as $k => $max) {
            if (count($S[$k]) > $max) {
                throw new BusinessException("Limite de {$max} linhas no bloco {$k}.");
            }
        }
        foreach ($S['f'] as $k => $v) {
            if (is_string($v) && mb_strlen($v) > 4000) {
                $S['f'][$k] = mb_substr($v, 0, 4000);
            }
        }
        if (strlen((string) json_encode($S)) > 2_000_000) {
            throw new BusinessException('Formulário grande demais.');
        }
        return $S;
    }

    /** @param array<string,mixed> $S @return array<string,mixed> */
    private static function colunas(array $S): array
    {
        $vid = (int) ($S['f']['veic'] ?? 0);
        return [
            'veiculo_id' => $vid && Database::value('SELECT id FROM veiculos WHERE id = ?', [$vid]) ? $vid : null,
            'cliente_nome' => mb_substr(trim((string) ($S['f']['cli_razao'] ?? '')), 0, 160) ?: null,
            'titulo' => mb_substr(trim((string) ($S['f']['titulo'] ?? '')), 0, 160) ?: null,
        ];
    }

    /** Formata documentos válidos (RN-62) no estado. @param array<string,mixed> $S @return array<string,mixed> */
    public static function formatarDocumentos(array $S): array
    {
        foreach (['cli_cnpj', 'ag_cnpj'] as $k) {
            $d = DocumentoValidator::verificar((string) ($S['f'][$k] ?? ''));
            if ($d['ok']) {
                $S['f'][$k] = $d['formatado'];
            }
        }
        return $S;
    }
}
