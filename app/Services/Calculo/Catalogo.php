<?php

declare(strict_types=1);

namespace App\Services\Calculo;

use App\Core\Database;

/**
 * Catálogo comercial de um veículo (tabelas, programas, formatos, preços, esquemas e audiência), lido do MySQL.
 * Substitui os blocos DATA, PROG e DIG que ficavam no JavaScript do Gerador original.
 */
class Catalogo
{
    /** @var array<int,array<string,mixed>> */
    private array $veiculos = [];
    /** @var array<string,array<string,array<string,mixed>>> programas por "veiculo|ano" e sigla */
    private array $programas = [];
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $formatos = [];
    /** @var array<int,float>|null */
    private ?array $indices = null;

    /** @return array<string,mixed>|null */
    public function veiculo(int $id): ?array
    {
        if (!isset($this->veiculos[$id])) {
            $v = Database::first(
                'SELECT v.*, e.nome_curto AS empresa_nome, e.razao_social AS empresa_razao, e.cnpj AS empresa_cnpj, e.endereco AS empresa_endereco,
                        e.telefone AS empresa_telefone, e.inscricao_municipal AS empresa_im, e.dados_bancarios AS empresa_banco
                   FROM veiculos v JOIN empresas e ON e.id = v.empresa_id WHERE v.id = ?',
                [$id]
            );
            if (!$v) {
                return null;
            }
            $v['isTV'] = $v['tipo'] === 'TV';
            $v['tabelas'] = Database::all('SELECT id, codigo, descricao, cidade, ano_vigencia, sem_tabela FROM tabelas_precos WHERE veiculo_id = ? AND ativo = 1 ORDER BY ano_vigencia DESC, ordem', [$id]);
            $this->veiculos[$id] = $v;
        }
        return $this->veiculos[$id];
    }

    /** Ano de tabela vigente para a data (o maior ano cadastrado que não passa do ano da data). */
    public function anoTabela(int $veiculoId, ?string $data): int
    {
        $v = $this->veiculo($veiculoId);
        $anos = array_unique(array_map(fn ($t) => (int) $t['ano_vigencia'], $v['tabelas'] ?? []));
        rsort($anos);
        $ref = $data && preg_match('/^\d{4}/', $data) ? (int) substr($data, 0, 4) : (int) date('Y');
        foreach ($anos as $a) {
            if ($a <= $ref) {
                return $a;
            }
        }
        return $anos ? (int) end($anos) : $ref;
    }

    /** @return list<array<string,mixed>> tabelas/praças do ano */
    public function tabelas(int $veiculoId, int $ano): array
    {
        $v = $this->veiculo($veiculoId);
        return array_values(array_filter($v['tabelas'] ?? [], fn ($t) => (int) $t['ano_vigencia'] === $ano));
    }

    /** @return array<string,mixed>|null */
    public function tabela(int $veiculoId, int $ano, string $codigo): ?array
    {
        foreach ($this->tabelas($veiculoId, $ano) as $t) {
            if ($t['codigo'] === $codigo) {
                return $t;
            }
        }
        return null;
    }

    public function indiceDuracao(int $duracao): ?float
    {
        if ($this->indices === null) {
            $this->indices = [];
            foreach (Database::all('SELECT duracao_seg, indice FROM indices_duracao') as $r) {
                $this->indices[(int) $r['duracao_seg']] = (float) $r['indice'];
            }
        }
        return $this->indices[$duracao] ?? null;
    }

    /** @return list<int> */
    public function duracoes(): array
    {
        $this->indiceDuracao(30);
        $d = array_keys($this->indices ?? []);
        sort($d);
        return $d;
    }

    /** Sigla do texto "HORA — Hora Um" → "HORA" (como up() do Gerador). */
    public static function sigla(mixed $texto): string
    {
        $s = (string) ($texto ?? '');
        $p = mb_strpos($s, '—');
        if ($p !== false) {
            $s = mb_substr($s, 0, $p);
        }
        return mb_strtoupper(trim($s));
    }

    /** @return array<string,array<string,mixed>> programas do veículo (chave = sigla) com preços do ano */
    public function programas(int $veiculoId, int $ano): array
    {
        $k = $veiculoId . '|' . $ano;
        if (!isset($this->programas[$k])) {
            $lista = [];
            foreach (Database::all('SELECT * FROM programas WHERE veiculo_id = ? AND ativo = 1 ORDER BY nome', [$veiculoId]) as $p) {
                $p['dias'] = DiasExibicao::daMascara($p['dias_mascara'] !== null ? (int) $p['dias_mascara'] : null);
                $p['t'] = [];
                $p['r'] = [];
                $p['p'] = [];
                $p['e'] = null;
                $p['g'] = [];
                $p['i'] = [];
                $lista[(int) $p['id']] = $p;
            }
            if ($lista) {
                [$in, $params] = Database::in(array_keys($lista));
                $params['ano'] = $ano;
                foreach (Database::all(
                    "SELECT pm.programa_id, pm.tipo, pm.duracao_seg, pm.valor, t.codigo FROM precos_midia pm
                       JOIN tabelas_precos t ON t.id = pm.tabela_preco_id WHERE pm.programa_id IN {$in} AND t.ano_vigencia = :ano AND t.ativo = 1",
                    $params
                ) as $r) {
                    $pid = (int) $r['programa_id'];
                    $v = (float) $r['valor'];
                    match ($r['tipo']) {
                        'base_30s' => $lista[$pid]['t'][$r['codigo']] = $v,
                        'duracao' => $lista[$pid]['r'][$r['duracao_seg'] . $r['codigo']] = $v,
                        'cota_patrocinio' => $lista[$pid]['p'][$r['codigo']] = $v,
                        default => null,
                    };
                }
                [$in2, $p2] = Database::in(array_keys($lista));
                foreach (Database::all("SELECT * FROM programa_esquemas WHERE programa_id IN {$in2}", $p2) as $r) {
                    $lista[(int) $r['programa_id']]['e'] = [(int) $r['ins_30'], (int) $r['ins_60'], (int) $r['vinheta'], (int) $r['flash'], (int) $r['testemunhal'], (int) $r['entrevista']];
                }
                [$in3, $p3] = Database::in(array_keys($lista));
                $p3['ano'] = $ano;
                foreach (Database::all(
                    "SELECT a.programa_id, a.grp, a.impactos, t.codigo FROM programa_audiencia a JOIN tabelas_precos t ON t.id = a.tabela_preco_id
                      WHERE a.programa_id IN {$in3} AND t.ano_vigencia = :ano",
                    $p3
                ) as $r) {
                    $lista[(int) $r['programa_id']]['g'][$r['codigo']] = (float) $r['grp'];
                    $lista[(int) $r['programa_id']]['i'][$r['codigo']] = (int) $r['impactos'];
                }
            }
            $porSigla = [];
            foreach ($lista as $p) {
                $porSigla[mb_strtoupper((string) $p['sigla'])] = $p;
            }
            $this->programas[$k] = $porSigla;
        }
        return $this->programas[$k];
    }

    /** @return array<string,mixed>|null */
    public function programa(int $veiculoId, int $ano, mixed $texto): ?array
    {
        $s = self::sigla($texto);
        return $s === '' ? null : ($this->programas($veiculoId, $ano)[$s] ?? null);
    }

    /** @return array<string,array<string,mixed>> formatos digitais e de publicidade legal (chave = sigla) */
    public function formatos(int $veiculoId, int $ano): array
    {
        $k = $veiculoId . '|' . $ano;
        if (!isset($this->formatos[$k])) {
            $lista = [];
            foreach (Database::all(
                'SELECT f.*, (SELECT pm.valor FROM precos_midia pm JOIN tabelas_precos t ON t.id = pm.tabela_preco_id
                               WHERE pm.formato_id = f.id AND pm.tipo = \'unitario\' AND t.ano_vigencia = ? AND t.ativo = 1 ORDER BY t.ordem LIMIT 1) AS preco
                   FROM formatos_midia f WHERE f.veiculo_id = ? AND f.ativo = 1 ORDER BY f.nome',
                [$ano, $veiculoId]
            ) as $f) {
                $f['preco'] = $f['preco'] !== null ? (float) $f['preco'] : null;
                $lista[mb_strtoupper((string) $f['sigla'])] = $f;
            }
            $this->formatos[$k] = $lista;
        }
        return $this->formatos[$k];
    }

    /**
     * @param 'digital'|'publicidade_legal' $tipo
     * @return array<string,mixed>|null
     */
    public function formato(int $veiculoId, int $ano, mixed $texto, string $tipo): ?array
    {
        $s = self::sigla($texto);
        if ($s === '') {
            return null;
        }
        $f = $this->formatos($veiculoId, $ano)[$s] ?? null;
        if ($f === null) {
            return null;
        }
        $legal = (int) $f['publicidade_legal'] === 1;
        return ($tipo === 'publicidade_legal') === $legal ? $f : null;
    }

    /**
     * RN-11 — aceita a sigla ou o nome do programa/formato: nome exato, ou parte do nome (com 4+ letras) quando só um combina.
     * @param 'ins'|'pat'|'dig'|'avi' $bloco
     */
    public function resolverSigla(int $veiculoId, int $ano, string $bloco, string $texto): string
    {
        $s = self::sigla($texto);
        $isFormato = $bloco === 'dig' || $bloco === 'avi';
        if ($isFormato) {
            $lista = array_filter($this->formatos($veiculoId, $ano), fn ($f) => ((int) $f['publicidade_legal'] === 1) === ($bloco === 'avi'));
        } else {
            $lista = $this->programas($veiculoId, $ano);
        }
        if (isset($lista[$s])) {
            return $s;
        }
        $n = normaliza($texto);
        foreach ($lista as $sig => $item) {
            if (normaliza((string) $item['nome']) === $n) {
                return (string) $sig;
            }
        }
        if (mb_strlen($n) >= 4) {
            $hits = array_keys(array_filter($lista, fn ($item) => str_contains(normaliza((string) $item['nome']), $n)));
            if (count($hits) === 1) {
                return (string) $hits[0];
            }
        }
        return $s;
    }
}
