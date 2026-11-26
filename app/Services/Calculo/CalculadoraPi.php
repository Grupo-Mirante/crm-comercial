<?php

declare(strict_types=1);

namespace App\Services\Calculo;

use App\Helpers\Calendario;
use App\Validators\DocumentoValidator;

/**
 * Motor de cálculo e conferência automática da PI — porta fiel de calc() do Gerador de PI original.
 * É a única fonte de verdade dos valores: a tela (via API), o banco e o Excel usam este resultado.
 *
 * Regras implementadas aqui:
 *  RN-09 blocos por veículo · RN-11 sigla cadastrada · RN-12 TV = 30" × índice (15" com índice do programa)
 *  RN-13 rádio por duração e verba · RN-14 digital pelo modelo · RN-15 preço manual · RN-16 período
 *  RN-18/RN-19 meses e período corrido · RN-21 a RN-24 grade, dias válidos e cobrança · RN-25 GRP/impactos/CPM
 *  RN-26 patrocínio · RN-27 a RN-31 digital e publicidade legal · RN-33 a RN-37 projetos · RN-40 a RN-45 valores,
 *  aprovação, agência, parcelas, entregas e aceite · RN-61 e RN-80 documentos e carta · conferência D01–D15 e P01–P04.
 */
final class CalculadoraPi
{
    public const BLOQUEIAM = ['P02', 'P03', 'P04'];

    public function __construct(private readonly ContextoCalculo $ctx)
    {
    }

    private static function num(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v)) {
            $t = trim($v);
            return is_numeric($t) ? (float) $t : 0.0;
        }
        return 0.0;
    }

    private static function txt(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function bon(?string $cob): bool
    {
        return $cob === 'Bonificado' || $cob === 'Reaplicação';
    }

    public static function r2(float $v): float
    {
        return round($v + 0.0, 2);
    }

    /**
     * @param array<string,mixed> $S estado do formulário
     * @return array<string,mixed>
     */
    public function calcular(array $S): array
    {
        $S = self::normalizar($S);
        $f = $S['f'];
        $cat = $this->ctx->catalogo;
        $vid = (int) self::num($f['veic'] ?? 0);
        $V = $vid ? $cat->veiculo($vid) : null;
        $isTV = $V && $V['tipo'] === 'TV';
        $tab = self::txt($f['tab'] ?? '');
        $ano = $V ? $cat->anoTabela($vid, self::txt($f['emissao'] ?? '') ?: null) : (int) date('Y');
        $ini = self::txt($f['ini'] ?? '') ?: null;
        $fim = self::txt($f['fim'] ?? '') ?: null;
        $months = Calendario::mesesDoPeriodo($ini, $fim);
        $multi = count($months) > 1;

        // ------------------------------------------------------------ inserções (RN-12, RN-13, RN-21 a RN-25)
        $ins = [];
        $lines = [];
        foreach (($V && $V['bloco_insercoes'] ? $S['ins'] : []) as $li => $l) {
            $p = $V ? $cat->programa($vid, $ano, $l['s'] ?? '') : null;
            $air = $p ? $p['dias'] : null;
            $dur = (int) self::num($l['dur'] ?? 0);
            $idx = null;
            $unit = null;
            if ($dur) {
                $idx = ($isTV && $dur === 15 && $p) ? ($p['indice_15s'] !== null ? (float) $p['indice_15s'] : $cat->indiceDuracao(15)) : $cat->indiceDuracao($dur);
            }
            $man = self::num($l['man'] ?? 0);
            if ($man > 0) {
                $unit = $man;
            } elseif ($p && $dur) {
                if ($isTV) {
                    $b = $p['t'][$tab] ?? null;
                    $unit = ($b && $idx) ? $b * $idx : null;
                } else {
                    $r = $p['r'][$dur . $tab] ?? null;
                    $unit = $r ?: null;
                }
            }
            $temSigla = self::txt($l['s'] ?? '') !== '';
            $nome = $temSigla ? ($p ? (string) $p['nome'] : 'Sigla não cadastrada') : '';
            $diasTxt = $p ? trim(($p['dias_exibicao'] ?? '') . ' ' . ($p['faixa_horaria'] ?? '')) : '';
            $semPreco = $temSigla && $p && $dur && $unit === null;
            $cob = self::txt($l['cob'] ?? '') ?: 'Avulsa';
            $desc = self::num($l['desc'] ?? 0);
            $agg = ['li' => $li, 'l' => $l, 'p' => $p ? self::resumoPrograma($p) : null, 'n' => 0, 'total' => 0.0, 'per' => [], 'out' => [], 'noAir' => [],
                'unit' => $unit, 'idx' => $idx, 'semPreco' => $semPreco, 'nome' => $nome, 'dias' => $diasTxt, 'grp' => 0.0];
            $rows = [];
            foreach ($months as $M) {
                $g = $l['gm'][$M['key']] ?? [];
                $n = 0;
                $out = [];
                $noAir = [];
                foreach ($g as $d => $x) {
                    $q = (int) self::num($x);
                    $n += $q;
                    if (!$q) {
                        continue;
                    }
                    $di = Calendario::diaInfo($M, $ini, $fim, (int) $d);
                    if (!$di['ok']) {
                        $out[] = (int) $d;
                    } elseif ($air !== null && !in_array($di['wd'], $air, true)) {
                        $noAir[] = (int) $d;
                    }
                }
                $bruto = $unit !== null ? $n * $unit : 0.0;
                $total = self::bon($cob) ? 0.0 : $bruto * (1 - $desc / 100);
                $imp = ($isTV && $p && !empty($p['i'][$tab])) ? $p['i'][$tab] * $n : 0;
                $grp = ($isTV && $p && !empty($p['g'][$tab])) ? $p['g'][$tab] * $n : 0.0;
                $r = ['li' => $li, 'l' => $l, 'M' => $M, 'nome' => $nome, 'dias' => $diasTxt, 'air' => $air, 'idx' => $idx, 'unit' => $unit, 'n' => $n, 'g' => $g,
                    'bruto' => $bruto, 'total' => $total, 'imp' => $imp, 'grp' => $grp, 'bonif' => self::bon($cob) ? $bruto : 0.0, 'out' => $out, 'noAir' => $noAir,
                    'semPreco' => $semPreco, 'cob' => $cob, 'desc' => $desc, 'programa_id' => $p ? (int) $p['id'] : null, 'sigla' => Catalogo::sigla($l['s'] ?? '')];
                if ($n > 0) {
                    $rows[] = $r;
                }
                $agg['n'] += $n;
                $agg['total'] += $total;
                $agg['per'][$M['key']] = $n;
                foreach ($out as $d) {
                    $agg['out'][] = $M['short'] . ' ' . $d;
                }
                foreach ($noAir as $d) {
                    $agg['noAir'][] = $M['short'] . ' ' . $d;
                }
                $agg['grp'] += $grp;
            }
            if (!$rows && ($temSigla || $dur)) {
                $rows[] = ['li' => $li, 'l' => $l, 'M' => $months[0] ?? null, 'nome' => $nome, 'dias' => $diasTxt, 'air' => $air, 'idx' => $idx, 'unit' => $unit, 'n' => 0, 'g' => [],
                    'bruto' => 0.0, 'total' => 0.0, 'imp' => 0, 'grp' => 0.0, 'bonif' => 0.0, 'out' => [], 'noAir' => [], 'semPreco' => $semPreco, 'cob' => $cob, 'desc' => $desc,
                    'programa_id' => $p ? (int) $p['id'] : null, 'sigla' => Catalogo::sigla($l['s'] ?? '')];
            }
            if ($temSigla || $agg['n']) {
                array_push($ins, ...$rows);
                $lines[] = $agg;
            }
        }

        // ------------------------------------------------------------ patrocínio de rádio (RN-26)
        $pat = [];
        foreach (($V && $V['bloco_patrocinio'] ? $S['pat'] : []) as $li => $l) {
            if (self::txt($l['s'] ?? '') === '') {
                continue;
            }
            $p = $cat->programa($vid, $ano, $l['s']);
            $cot = (int) self::num($l['cot'] ?? 0) ?: 1;
            $esq = array_map(fn ($x) => ($x ?: 0) * $cot, $p && $p['e'] ? $p['e'] : [0, 0, 0, 0, 0, 0]);
            $outras = (int) self::num($l['out'] ?? 0);
            $n = array_sum($esq) + $outras;
            $man = self::num($l['man'] ?? 0);
            $base = $man > 0 ? $man : ($p ? ($p['p'][$tab === 'NAC' ? 'NAC' : 'LOC'] ?? null) : null);
            $valor = $base ? $base * $cot : null;
            $cob = self::txt($l['cob'] ?? '') ?: 'Avulsa';
            $desc = self::num($l['desc'] ?? 0);
            $total = $valor === null ? 0.0 : (self::bon($cob) ? 0.0 : $valor * (1 - $desc / 100));
            $mk = self::txt($l['mk'] ?? '');
            $pat[] = ['li' => $li, 'l' => $l, 'nome' => $p ? (string) $p['nome'] : 'Sigla não cadastrada', 'mes' => ($multi && $mk) ? Calendario::mes($mk)['short'] : '',
                'mk' => $mk, 'dias' => $p ? trim(($p['dias_exibicao'] ?? '') . ' ' . ($p['faixa_horaria'] ?? '')) : '', 'esq' => $esq, 'outras' => $outras, 'n' => $n, 'cot' => $cot,
                'valor' => $valor, 'total' => $total, 'bonif' => (self::bon($cob) && $valor) ? $valor : 0.0, 'semPreco' => $p && $valor === null,
                'cob' => $cob, 'desc' => $desc, 'programa_id' => $p ? (int) $p['id'] : null, 'sigla' => Catalogo::sigla($l['s'])];
        }

        // ------------------------------------------------------------ digital (RN-14, RN-19, RN-27 a RN-30)
        $dig = [];
        foreach (($V && $V['bloco_digital'] ? $S['dig'] : []) as $li => $l) {
            if (self::txt($l['s'] ?? '') === '') {
                continue;
            }
            $d = $cat->formato($vid, $ano, $l['s'], 'digital');
            $man = self::num($l['man'] ?? 0);
            $unit = $man > 0 ? $man : ($d ? $d['preco'] : null);
            $entL = self::num($l['ent'] ?? 0);
            $mod = $d ? (string) $d['modelo'] : ($entL ? 'CPM' : 'Unidade');
            $qtd = self::num($l['qtd'] ?? 0) ?: 1.0;
            $ent = $entL ?: (($d && $d['modelo'] !== 'CPM') ? (float) ($d['entrega_estimada'] ?? 0) : 0.0);
            $a = Calendario::data(self::txt($l['ini'] ?? '') ?: $ini);
            $b = Calendario::data(self::txt($l['fim'] ?? '') ?: $fim);
            $dias = ($a && $b && $b >= $a) ? Calendario::diferenca($a, $b) + 1 : 0;
            $meses = 0.0;
            $mdet = [];
            if ($dias) {
                foreach (Calendario::mesesDoPeriodo(Calendario::iso($a), Calendario::iso($b)) as $M) {
                    $s = max($a, Calendario::data($M['start']));
                    $e = min($b, Calendario::data($M['end']));
                    $nd = Calendario::diferenca($s, $e) + 1;
                    $meses += $nd / $M['days'];
                    $mdet[] = "{$nd}/{$M['days']} {$M['short']}";
                }
            }
            $tabv = null;
            if ($unit !== null) {
                $tabv = match ($mod) {
                    'CPM' => $entL / 1000 * $unit,
                    'Mensal' => $unit * $qtd * $meses,
                    default => $unit * $qtd,
                };
            }
            $cob = self::txt($l['cob'] ?? '') ?: 'Pago';
            if ($cob === 'Incluso no pacote') {
                $tabv = 0.0;
            }
            $desc = self::num($l['desc'] ?? 0);
            $total = $tabv === null ? 0.0 : (($cob === 'Bonificado' || $cob === 'Incluso no pacote') ? 0.0 : $tabv * (1 - $desc / 100));
            $dig[] = ['li' => $li, 'l' => $l, 'nome' => $d ? (string) $d['nome'] : 'Sigla não cadastrada', 'mes' => '', 'mod' => $mod, 'unit' => $unit, 'qtd' => $qtd, 'ent' => $ent,
                'dias' => $dias, 'meses' => $meses, 'mdet' => $mdet, 'ini' => $a ? Calendario::iso($a) : '', 'fim' => $b ? Calendario::iso($b) : '',
                'tab' => $tabv ?: 0.0, 'total' => $total, 'bonif' => $cob === 'Bonificado' ? ($tabv ?: 0.0) : 0.0, 'semPreco' => $d && $unit === null,
                'cpmSemEnt' => $mod === 'CPM' && !($entL > 0), 'cob' => $cob, 'desc' => $desc, 'formato_id' => $d ? (int) $d['id'] : null, 'sigla' => Catalogo::sigla($l['s']),
                'formato' => $d ? ['dispositivo' => $d['dispositivo'], 'local' => $d['local_pagina'], 'visibilidade' => $d['visibilidade_pct'], 'entrega' => $d['entrega_estimada'], 'modelo' => $d['modelo']] : null];
        }

        // ------------------------------------------------------------ publicidade legal (RN-31, somente Imirante)
        $avi = [];
        foreach (($V && $V['bloco_publicidade_legal'] ? $S['avi'] : []) as $li => $l) {
            if (self::txt($l['s'] ?? '') === '' && self::txt($l['mat'] ?? '') === '') {
                continue;
            }
            $d = $cat->formato($vid, $ano, $l['s'] ?? '', 'publicidade_legal');
            $man = self::num($l['man'] ?? 0);
            $unit = $man > 0 ? $man : ($d ? $d['preco'] : null);
            $cmcol = self::num($l['col'] ?? 0) * self::num($l['cm'] ?? 0);
            $pub = (int) self::num($l['pub'] ?? 0) ?: 1;
            $tabv = $unit !== null ? $cmcol * $pub * $unit : 0.0;
            $desc = self::num($l['desc'] ?? 0);
            $temSigla = self::txt($l['s'] ?? '') !== '';
            $avi[] = ['li' => $li, 'l' => $l, 'nome' => $d ? str_replace('Publicidade legal: ', '', (string) $d['nome']) : ($temSigla ? 'Sigla não cadastrada' : ''),
                'semSigla' => !$temSigla, 'mes' => '', 'unit' => $unit, 'cmcol' => $cmcol, 'pub' => $pub, 'tab' => $tabv, 'total' => $tabv * (1 - $desc / 100),
                'semPreco' => $d && $unit === null, 'desc' => $desc, 'formato_id' => $d ? (int) $d['id'] : null, 'sigla' => Catalogo::sigla($l['s'] ?? '')];
        }

        // ------------------------------------------------------------ projetos especiais (RN-32 a RN-37)
        $pq = [];
        foreach ($S['prj'] as $l) {
            $pidp = (int) self::num($l['id'] ?? 0);
            $cid = (int) self::num($l['cota'] ?? 0);
            if ($pidp && $cid) {
                $pq[$cid] = ($pq[$cid] ?? 0) + max(1, (int) self::num($l['q'] ?? 0));
            }
        }
        $prj = [];
        foreach ($S['prj'] as $li => $l) {
            $pidp = (int) self::num($l['id'] ?? 0);
            if (!$pidp) {
                continue;
            }
            $P = $this->ctx->projetos[$pidp] ?? null;
            $c = null;
            foreach (($P['cotas'] ?? []) as $cc) {
                if ((int) $cc['id'] === (int) self::num($l['cota'] ?? 0)) {
                    $c = $cc;
                }
            }
            $q = max(1, (int) self::num($l['q'] ?? 0));
            $unit = $c ? (float) $c['valor'] : null;
            $tabv = $unit !== null ? $unit * $q : 0.0;
            $cob = self::txt($l['cob'] ?? '') ?: 'Pago';
            $bonus = $cob === 'Bonificado';
            $desc = self::num($l['desc'] ?? 0);
            $vend = ($P && $c) ? $this->ctx->ocupadas((int) $c['id']) : 0;
            $disp = $c ? max(0, (int) $c['quantidade_total'] - $vend) : 0;
            $veics = $P ? array_map('intval', $P['veiculos'] ?? []) : [];
            $prj[] = ['li' => $li, 'l' => $l, 'projeto_id' => $pidp, 'cota_id' => $c ? (int) $c['id'] : null, 'codigo' => $P['codigo'] ?? '', 'nome' => $P ? (string) $P['nome'] : 'Projeto não cadastrado',
                'cota' => $c ? (string) $c['nome'] : '', 'q' => $q, 'unit' => $unit, 'tab' => $tabv, 'total' => $bonus ? 0.0 : $tabv * (1 - $desc / 100), 'bonif' => $bonus ? $tabv : 0.0,
                'vend' => $vend, 'disp' => $disp, 'ate' => $P['data_limite_venda'] ?? null, 'veiculos' => $veics, 'cob' => $cob, 'desc' => $desc,
                'venc' => $P && $P['data_limite_venda'] && $this->ctx->hoje() > $P['data_limite_venda'],
                'excede' => $c && ($pq[(int) $c['id']] ?? 0) > $disp,
                'outroVeic' => $P && $vid && !in_array($vid, $veics, true),
                'semCota' => !$c];
        }

        // ------------------------------------------------------------ totais (RN-40, RN-42)
        $sum = fn (array $a, string $k) => array_reduce($a, fn ($x, $y) => $x + (float) ($y[$k] ?? 0), 0.0);
        $prod = self::num($f['producao'] ?? 0);
        $T = [];
        $T['insBruto'] = $sum($ins, 'bruto');
        $T['insTotal'] = $sum($ins, 'total');
        $T['nIns'] = (int) ($sum($ins, 'n') + $sum($pat, 'n'));
        $T['patTab'] = $sum($pat, 'valor');
        $T['patTotal'] = $sum($pat, 'total');
        $T['digTab'] = $sum($dig, 'tab');
        $T['digTotal'] = $sum($dig, 'total');
        $T['aviTab'] = $sum($avi, 'tab');
        $T['aviTotal'] = $sum($avi, 'total');
        $T['prjTab'] = $sum($prj, 'tab');
        $T['prjTotal'] = $sum($prj, 'total');
        $T['producao'] = $prod;
        $T['tab'] = $T['insBruto'] + $T['patTab'] + $T['digTab'] + $T['aviTab'] + $T['prjTab'] + $prod;
        $T['neg'] = $T['insTotal'] + $T['patTotal'] + $T['digTotal'] + $T['aviTotal'] + $T['prjTotal'] + $prod;
        $T['bonif'] = $sum($ins, 'bonif') + $sum($pat, 'bonif') + $sum($dig, 'bonif') + $sum($prj, 'bonif');
        $T['desc'] = $T['tab'] - $T['neg'];
        $T['descPct'] = $T['tab'] > 0 ? $T['desc'] / $T['tab'] : 0.0;
        $T['comPct'] = ($f['venda'] ?? '') === 'Agência' ? self::num($f['ag_com'] ?? 0) / 100 : 0.0;
        $T['com'] = $T['neg'] * $T['comPct'];
        $T['liq'] = $T['neg'] - $T['com'];
        $T['imp'] = (int) $sum($ins, 'imp');
        $T['grp'] = $sum($ins, 'grp');
        $T['cpm'] = $T['imp'] > 0 ? $T['insTotal'] / $T['imp'] * 1000 : 0.0;
        $parcs = array_values(array_filter($S['parc'], fn ($p) => self::txt($p['venc'] ?? '') !== '' || self::num($p['val'] ?? 0)));
        $T['parcSum'] = array_reduce($parcs, fn ($a, $p) => $a + self::num($p['val'] ?? 0), 0.0);
        $splitTV = $isTV && $multi;
        $T['qtdLinhas'] = count($ins) + count($pat) + count($dig) + count($avi) + count($prj);

        // ------------------------------------------------------------ conferência automática (RN-45, seção 8 das regras)
        $C = [];
        $ck = function (string $code, string $txt, string $st, string $fix, string $go) use (&$C) {
            $C[] = ['code' => $code, 'txt' => $txt, 'st' => $st, 'fix' => $fix, 'go' => $go];
        };
        $blank = fn (string $k) => self::txt($f[$k] ?? '') === '';
        $tabCodes = $V ? array_map(fn ($t) => $t['codigo'], $cat->tabelas($vid, $ano)) : [];
        $ck('D14', 'Veículo e tabela/praça escolhidos', !$V ? 'FALTA' : ($tab === '' ? 'FALTA' : (in_array($tab, $tabCodes, true) ? 'OK' : 'ERRO')), 'Escolha o veículo e a tabela.', 's1');
        $tipo = self::txt($f['tipo'] ?? '');
        $ck('D14', 'Tipo de PI, emissão e PI de origem (renovação, alteração, cancelamento ou cancela/substitui)',
            ($blank('tipo') || $blank('emissao')) ? 'FALTA' : ((preg_match('/Renova|Altera|substitui|Cancelamento/u', $tipo) && $blank('origem')) ? 'FALTA' : 'OK'),
            'Renovação, alteração e cancela/substitui são PIs novas, com o nº da PI de origem.', 's1');
        $ck('D15', 'Executivo, matrícula e gestor', ($blank('exec') || $blank('mat') || $blank('gestor')) ? 'FALTA' : 'OK', 'Complete o cadastro do executivo (matrícula e gestor) com o administrador.', 's2');
        $ck('D01', 'Cliente completo (razão social, CNPJ/CPF, endereço, cidade, UF, contato, telefone, e-mail)',
            array_filter(['cli_razao', 'cli_cnpj', 'cli_end', 'cli_cid', 'cli_uf', 'cli_contato', 'cli_tel', 'cli_email'], $blank) ? 'FALTA' : 'OK', 'Preencha os campos com * do cliente.', 's3');
        $dc = DocumentoValidator::verificar(self::txt($f['cli_cnpj'] ?? ''));
        if (!$dc['vazio']) {
            $ck('D01', ($dc['tipo'] ?? 'CNPJ/CPF') . ' do cliente válido', $dc['ok'] ? 'OK' : 'ERRO', 'Dígitos verificadores não conferem: confira o número (CNPJ com 14 caracteres ou CPF com 11 dígitos).', 's3');
        }
        $agencia = ($f['venda'] ?? '') === 'Agência';
        if ($agencia && !$blank('ag_cnpj')) {
            $ck('D08', 'CNPJ da agência válido', DocumentoValidator::cnpjValido(self::txt($f['ag_cnpj'])) ? 'OK' : 'ERRO', 'Dígitos verificadores do CNPJ da agência não conferem.', 's3');
        }
        if ($agencia && !$blank('ag_val') && !$blank('emissao') && self::txt($f['ag_val']) < self::txt($f['emissao'])) {
            $ck('D08', 'Carta de credenciamento da agência no prazo', 'ERRO', 'A carta de credenciamento da agência venceu antes da emissão: peça a carta atualizada.', 's3'); // RN-80
        }
        $ck('D08', 'Agência e comissão (venda por agência)', $blank('venda') ? 'FALTA' : (($agencia && ($blank('ag_nome') || $blank('ag_cnpj') || $blank('ag_carta') || !(self::num($f['ag_com'] ?? 0) > 0))) ? 'FALTA' : 'OK'),
            'Informe nome, CNPJ, comissão e carta de credenciamento da agência.', 's3');
        $di = Calendario::data($ini);
        $dfim = Calendario::data($fim);
        $ck('D02', 'Período da campanha (início ≤ fim)', (!$di || !$dfim) ? 'FALTA' : ($dfim < $di ? 'ERRO' : 'OK'), 'Informe início e fim; o fim não pode ser antes do início.', 's4');
        $ck('—', 'Título, produto e praça de cobrança', ($blank('titulo') || $blank('produto') || $blank('pcob')) ? 'FALTA' : 'OK', 'Complete o bloco Campanha.', 's4');
        $ck('—', 'Mídia preenchida', (count($ins) + count($pat) + count($dig) + count($avi) + count($prj)) ? 'OK' : 'FALTA', 'Inclua ao menos uma linha de mídia.', 's5');
        $semSigla = array_filter([...$ins, ...$pat, ...$dig, ...$avi], fn ($x) => $x['nome'] === 'Sigla não cadastrada' || !empty($x['semSigla']));
        $ck('D11', 'Siglas cadastradas', $semSigla ? 'ERRO' : 'OK', 'Escolha a sigla na lista (programa, formato ou tipo de publicação).', 's5');
        $ck('D12', 'Duração em todas as linhas de inserção', array_filter($lines, fn ($x) => self::txt($x['l']['s'] ?? '') !== '' && !self::num($x['l']['dur'] ?? 0)) ? 'FALTA' : 'OK', 'Escolha a duração.', 's5');
        $ck('D03', 'Grade preenchida em todas as linhas de inserção', array_filter($lines, fn ($x) => self::txt($x['l']['s'] ?? '') !== '' && !$x['n']) ? 'FALTA' : 'OK', 'Informe a quantidade por dia na grade.', 's5');
        $ck('D02', 'Inserções só em dias dentro do período da campanha', array_filter($lines, fn ($x) => $x['out']) ? 'ERRO' : 'OK', 'Há inserções fora do período (início a fim): ajuste as datas ou a grade.', 's5');
        $ck('D03', 'Inserções só nos dias em que o programa vai ao ar', array_filter($lines, fn ($x) => $x['noAir']) ? 'ERRO' : 'OK', 'Há inserções em dias sem exibição do programa.', 's5');
        $ck('D05', 'Preço encontrado para todas as linhas', array_filter([...$lines, ...$pat, ...$dig, ...$avi], fn ($x) => !empty($x['semPreco'])) ? 'FALTA' : 'OK',
            'Sem preço na tabela (ou "sob consulta"): informe o preço manual aprovado pelo gestor.', 's5');
        $ck('D05', 'Impressões informadas nos formatos vendidos por CPM', array_filter($dig, fn ($x) => $x['cpmSemEnt']) ? 'FALTA' : 'OK', 'Formato CPM: informe a entrega em impressões.', 's5');
        $ck('D05', 'Publicidade legal com colunas e altura (cm)', array_filter($avi, fn ($x) => self::txt($x['l']['s'] ?? '') !== '' && !$x['cmcol']) ? 'FALTA' : 'OK', 'Informe colunas × cm do material.', 's5');
        if ($multi && !$isTV && $pat) {
            $ck('—', 'Patrocínio com o mês de cada linha', array_filter($pat, fn ($x) => $x['mk'] === '') ? 'FALTA' : 'OK', 'Período em mais de um mês: escolha o mês de cada linha de patrocínio.', 's5');
        }
        $ck('D02', 'Período válido nos formatos digitais mensais', array_filter($dig, fn ($x) => $x['mod'] === 'Mensal' && !$x['dias']) ? 'FALTA' : 'OK', 'Informe início e fim (da campanha ou da linha).', 's5');
        if ($prj) {
            $ck('P01', 'Projeto especial: cota escolhida', array_filter($prj, fn ($x) => $x['semCota']) ? 'FALTA' : 'OK', 'Escolha o projeto e a cota na lista.', 's5');
            $ck('P02', 'Projeto especial dentro do prazo de venda', array_filter($prj, fn ($x) => $x['venc']) ? 'ERRO' : 'OK', 'Prazo de venda do projeto encerrado: a cota não pode mais ser vendida.', 's5');
            $ck('P03', 'Cotas disponíveis no projeto', array_filter($prj, fn ($x) => $x['excede']) ? 'ERRO' : 'OK', 'Quantidade maior que as cotas ainda disponíveis.', 's5');
            $ck('P04', 'Projeto válido para a empresa desta PI', array_filter($prj, fn ($x) => $x['outroVeic']) ? 'ERRO' : 'OK', 'Esse projeto não vale para esta empresa: veja em Projetos onde ele pode ser vendido.', 's5');
        }
        $projBlock = (bool) array_filter($prj, fn ($x) => $x['venc'] || $x['excede'] || $x['outroVeic']);

        // RN-41: desconto, bonificação ou preço manual exigem aprovação do gestor
        $manuais = [];
        foreach (['ins' => $S['ins'], 'pat' => $S['pat'], 'dig' => $S['dig'], 'avi' => $S['avi']] as $b => $ls) {
            foreach ($ls as $i => $l) {
                if (self::num($l['man'] ?? 0) > 0) {
                    $manuais[] = ['bloco' => $b, 'linha' => $i, 'sigla' => Catalogo::sigla($l['s'] ?? ''), 'preco' => self::r2(self::num($l['man']))];
                }
            }
        }
        $needGs = $T['desc'] > 0.005 || (bool) $manuais;
        $aprov = null;
        if ($needGs && $this->ctx->aprovacao) {
            $aprov = ($this->ctx->aprovacao)($T, $manuais);
            $st = $aprov['ok'] ? 'OK' : 'FALTA';
            $fix = $aprov['situacao'] === 'pendente' ? 'Aprovação solicitada: aguarde o gestor aprovar.' : ($aprov['situacao'] === 'recusada' ? 'O gestor recusou a aprovação: revise os valores ou peça de novo.' : 'Peça a aprovação do gestor (botão "Solicitar aprovação").');
        } else {
            $st = !$needGs ? 'OK' : (($blank('gs_nome') || $blank('gs_data')) ? 'FALTA' : 'OK');
            $fix = 'Registre a aprovação do gestor.';
        }
        $ck('D06', 'Desconto, bonificação ou preço manual aprovados pelo gestor', $st, $fix, 's6');
        $ck('D07', $splitTV ? 'Forma de pagamento (uma parcela por PI mensal)' : 'Pagamento: forma e parcelas fechando com o líquido',
            $blank('forma') ? 'FALTA' : ($splitTV ? 'OK' : (!$parcs ? 'FALTA' : ((abs($T['parcSum'] - self::r2($T['liq'])) < 0.01 && !array_filter($parcs, fn ($p) => self::txt($p['venc'] ?? '') === '')) ? 'OK' : 'ERRO'))),
            'A soma das parcelas precisa ser igual ao valor líquido a faturar, e toda parcela precisa de vencimento.', 's6');
        $ent = array_values(array_filter($S['ent'], fn ($e) => self::txt($e['tipo'] ?? '') !== ''));
        $ck('D09', 'Entregas especiais com área e nº do briefing', array_filter($ent, fn ($e) => self::txt($e['area'] ?? '') === '' || self::txt($e['brief'] ?? '') === '') ? 'FALTA' : 'OK', 'Abra o briefing no Marketing e informe a área responsável.', 's6');
        $ck('D13', 'Promoção/sorteio com aval do jurídico', array_filter($ent, fn ($e) => preg_match('/sorteio/i', (string) $e['tipo']) && ($e['jur'] ?? '') !== 'Aprovado') ? 'FALTA' : 'OK', 'Sorteio só com aval do jurídico (Lei 5.768/1971).', 's6');
        $ck('D10', 'Aceite do cliente (nome e data)', ($blank('ac_nome') || $blank('ac_data')) ? 'FALTA' : 'OK', 'Informe quem aceitou e quando (assinatura ou e-mail de aceite anexado).', 's6');

        $pend = count(array_filter($C, fn ($c) => $c['st'] !== 'OK'));
        return [
            'V' => $V ? self::resumoVeiculo($V) : null, 'isTV' => $isTV, 'tab' => $tab, 'ano' => $ano, 'months' => $months, 'multi' => $multi, 'splitTV' => $splitTV,
            'ins' => $ins, 'lines' => $lines, 'pat' => $pat, 'dig' => $dig, 'avi' => $avi, 'prj' => $prj, 'projBlock' => $projBlock,
            'T' => $T, 'C' => $C, 'pend' => $pend, 'parcs' => $parcs, 'ent' => $ent, 'needGs' => $needGs, 'manuais' => $manuais, 'aprovacao' => $aprov,
        ];
    }

    /**
     * Garante a estrutura do estado (listas presentes, grade por mês).
     * @param array<string,mixed> $S
     * @return array<string,mixed>
     */
    public static function normalizar(array $S): array
    {
        $S['f'] = is_array($S['f'] ?? null) ? $S['f'] : [];
        foreach (['ins', 'pat', 'dig', 'avi', 'prj', 'ent', 'parc'] as $k) {
            $S[$k] = is_array($S[$k] ?? null) ? array_values(array_filter($S[$k], 'is_array')) : [];
        }
        foreach ($S['ins'] as &$l) {
            $l['gm'] = is_array($l['gm'] ?? null) ? $l['gm'] : [];
            foreach ($l['gm'] as $mk => $g) {
                if (!is_array($g) || !preg_match('/^\d{4}-\d{2}$/', (string) $mk)) {
                    unset($l['gm'][$mk]);
                }
            }
        }
        unset($l);
        return $S;
    }

    /** @param array<string,mixed> $p @return array<string,mixed> */
    private static function resumoPrograma(array $p): array
    {
        return ['id' => (int) $p['id'], 'sigla' => $p['sigla'], 'nome' => $p['nome'], 'dias_exibicao' => $p['dias_exibicao'], 'faixa' => $p['faixa_horaria'], 'dias' => $p['dias']];
    }

    /** @param array<string,mixed> $V @return array<string,mixed> */
    private static function resumoVeiculo(array $V): array
    {
        unset($V['tabelas']);
        return $V;
    }

    /** RN-45/RN-51: há pendência? (conta também as não bloqueantes) */
    public static function temBloqueio(array $R): bool
    {
        return (bool) $R['projBlock'];
    }
}
