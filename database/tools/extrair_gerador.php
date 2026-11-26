<?php

declare(strict_types=1);

/*
 * Extrator do catálogo comercial do Gerador de PI original.
 *
 * Lê os blocos de dados embutidos no HTML (DATA, PROG e DIG) e gera o seed SQL
 * database/seeds/producao/020_catalogo_2026.sql. Assim nenhum preço, programa, sigla
 * ou índice é digitado à mão: tudo vem exatamente do arquivo original.
 *
 * Uso:  php database/tools/extrair_gerador.php [caminho-do-html] [arquivo-de-saida]
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Calculo\DiasExibicao;

$root = dirname(__DIR__, 2);
$src = $argv[1] ?? $root . '/database/tools/fonte/gerador-de-pi-original.html';
$out = $argv[2] ?? $root . '/database/seeds/producao/020_catalogo_2026.sql';
const ANO = 2026;

$html = file_get_contents($src);
if ($html === false) {
    fwrite(STDERR, "Arquivo não encontrado: {$src}\n");
    exit(1);
}

/** Recorta o literal JSON que segue "const NOME=" respeitando aspas e colchetes. */
function literal(string $html, string $nome): mixed
{
    $pos = strpos($html, 'const ' . $nome . '=');
    if ($pos === false) {
        throw new RuntimeException("Bloco {$nome} não encontrado no HTML.");
    }
    $i = $pos + strlen('const ' . $nome . '=');
    $open = $html[$i];
    $close = $open === '[' ? ']' : '}';
    $depth = 0;
    $inStr = false;
    $len = strlen($html);
    for ($j = $i; $j < $len; $j++) {
        $ch = $html[$j];
        if ($inStr) {
            if ($ch === '\\') {
                $j++;
            } elseif ($ch === '"') {
                $inStr = false;
            }
            continue;
        }
        if ($ch === '"') {
            $inStr = true;
        } elseif ($ch === '[' || $ch === '{') {
            $depth++;
        } elseif ($ch === ']' || $ch === '}') {
            $depth--;
            if ($depth === 0) {
                return json_decode(substr($html, $i, $j - $i + 1), true, 512, JSON_THROW_ON_ERROR);
            }
        }
    }
    throw new RuntimeException("Bloco {$nome} incompleto.");
}

function q(mixed $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    if (is_int($v) || is_float($v)) {
        return rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') ?: '0';
    }
    return "'" . strtr((string) $v, ["\\" => "\\\\", "'" => "''", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
}

/** "a preencher" vira NULL: o administrador completa depois (pendência registrada). */
function preencher(?string $v): ?string
{
    $v = trim((string) $v);
    return ($v === '' || stripos($v, 'a preencher') !== false) ? null : $v;
}

$DATA = literal($html, 'DATA');
$PROG = literal($html, 'PROG');
$DIG = literal($html, 'DIG');

// Ordem e IDs fixos dos veículos (ordem do prompt e do documento)
$ordem = ['Rede Mirante TV' => 1, 'Mirante FM' => 2, 'Mirante News' => 3, 'G1 Maranhão' => 4, 'Imirante' => 5];
$veic = [];
foreach ($DATA['VEIC'] as $v) {
    if (!isset($ordem[$v['n']])) {
        throw new RuntimeException('Veículo inesperado: ' . $v['n']);
    }
    $veic[$v['n']] = $v + ['id' => $ordem[$v['n']]];
}
uasort($veic, fn ($a, $b) => $a['id'] <=> $b['id']);

$sql = [];
$sql[] = "-- =====================================================================";
$sql[] = "-- Catálogo comercial " . ANO . " — GERADO AUTOMATICAMENTE por database/tools/extrair_gerador.php";
$sql[] = "-- Fonte: " . basename($src) . " (blocos DATA, PROG e DIG do Gerador de PI)";
$sql[] = "-- Não edite à mão: altere pelo CRM (Cadastros) ou gere de novo a partir da fonte.";
$sql[] = "-- =====================================================================";
$sql[] = "SET NAMES utf8mb4;";
$sql[] = "";

// Empresas emissoras (uma por veículo; campos 'a preencher' ficam vazios)
$sql[] = "-- Empresas emissoras";
foreach ($veic as $v) {
    $sql[] = sprintf(
        "INSERT INTO empresas (id, nome_curto, razao_social, cnpj, endereco, telefone, inscricao_municipal, dados_bancarios) VALUES (%d, %s, %s, %s, %s, %s, %s, %s);",
        $v['id'], q($v['n']), q(preencher($v['razao'])), q(preencher($v['cnpj'])), q(preencher($v['end'])), q(preencher($v['tel'])), q(preencher($v['im'])), q(preencher($v['banco']))
    );
}

// Veículos
$sql[] = "";
$sql[] = "-- Veículos (RN-02, RN-03, RN-09)";
foreach ($veic as $v) {
    $sql[] = sprintf(
        "INSERT INTO veiculos (id, nome, tipo, prefixo, empresa_id, titulo_pi, bloco_insercoes, bloco_patrocinio, bloco_digital, bloco_publicidade_legal, divide_pi_por_mes, clausula_6, clausula_7, texto_atendimento, ordem) VALUES (%d, %s, %s, %s, %d, %s, %d, %d, %d, %d, %d, %s, %s, %s, %d);",
        $v['id'], q($v['n']), q($v['tipo']), q($v['pref']), $v['id'], q($v['titulo']), $v['ins'] ? 1 : 0, $v['pat'] ? 1 : 0, $v['dig'] ? 1 : 0, $v['avi'] ? 1 : 0,
        $v['tipo'] === 'TV' ? 1 : 0, q($v['cl6'] ?: null), q($v['cl7'] ?: null), q($v['atend'] ?: null), $v['id']
    );
}

// Tabelas / praças
$sql[] = "";
$sql[] = "-- Tabelas e praças (RN-10)";
$tab = [];
$tid = 0;
$ordemTab = [];
foreach ($DATA['PRACAS'] as $p) {
    $tid++;
    $vid = $veic[$p['v']]['id'];
    $ordemTab[$vid] = ($ordemTab[$vid] ?? 0) + 1;
    $tab[$p['v'] . '|' . $p['c']] = $tid;
    $sql[] = sprintf(
        "INSERT INTO tabelas_precos (id, veiculo_id, codigo, descricao, cidade, ano_vigencia, sem_tabela, ordem) VALUES (%d, %d, %s, %s, %s, %d, %d, %d);",
        $tid, $vid, q($p['c']), q($p['d']), q($p['cid']), ANO, $p['c'] === 'SAT' ? 1 : 0, $ordemTab[$vid]
    );
}

// Índices de duração (TV)
$sql[] = "";
$sql[] = "-- Índices de duração sobre o 30\" (RN-12)";
foreach ($DATA['DURS'] as [$d, $i]) {
    $sql[] = sprintf("INSERT INTO indices_duracao (duracao_seg, indice) VALUES (%d, %s);", $d, q((float) $i));
}

// Listas de valores
$sql[] = "";
$sql[] = "-- Listas de opções dos formulários";
foreach ($DATA['L'] as $lista => $valores) {
    foreach (array_values($valores) as $k => $val) {
        $sql[] = sprintf("INSERT INTO listas_valores (lista, valor, ordem) VALUES (%s, %s, %d);", q($lista), q($val), $k + 1);
    }
}

// Programas, preços, esquemas e audiência
$sql[] = "";
$sql[] = "-- Programas de TV e rádio, com preços de tabela " . ANO;
$pid = 0;
$cont = ['programas' => 0, 'precos' => 0, 'esquemas' => 0, 'audiencia' => 0];
foreach ($PROG as $p) {
    $pid++;
    $v = $veic[$p['v']];
    $isTv = $v['tipo'] === 'TV';
    $dias = trim((string) ($p['d'] ?? ''));
    $mask = DiasExibicao::mascara($dias);
    $sql[] = sprintf(
        "INSERT INTO programas (id, veiculo_id, sigla, nome, dias_exibicao, dias_mascara, faixa_horaria, indice_15s) VALUES (%d, %d, %s, %s, %s, %s, %s, %s);",
        $pid, $v['id'], q(strtoupper($p['s'])), q($p['n']), q($dias !== '' ? $dias : null), q($mask), q(trim((string) ($p['f'] ?? '')) ?: null), q($isTv && isset($p['c']) ? (float) $p['c'] : null)
    );
    $cont['programas']++;
    if ($isTv) {
        foreach (($p['t'] ?? []) as $praca => $valor) {
            if ($valor === null) {
                continue;
            }
            $sql[] = sprintf("INSERT INTO precos_midia (tabela_preco_id, programa_id, tipo, valor) VALUES (%d, %d, 'base_30s', %s);", $tab[$p['v'] . '|' . $praca], $pid, q((float) $valor));
            $cont['precos']++;
        }
        foreach (($p['g'] ?? []) as $praca => $grp) {
            $sql[] = sprintf("INSERT INTO programa_audiencia (programa_id, tabela_preco_id, grp, impactos) VALUES (%d, %d, %s, %d);", $pid, $tab[$p['v'] . '|' . $praca], q((float) $grp), (int) ($p['i'][$praca] ?? 0));
            $cont['audiencia']++;
        }
    } else {
        foreach (($p['r'] ?? []) as $chave => $valor) {
            if (!preg_match('/^(\d+)(LOC|NAC)$/', $chave, $m) || $valor === null) {
                throw new RuntimeException("Chave de preço de rádio inesperada: {$chave}");
            }
            $sql[] = sprintf("INSERT INTO precos_midia (tabela_preco_id, programa_id, tipo, duracao_seg, valor) VALUES (%d, %d, 'duracao', %d, %s);", $tab[$p['v'] . '|' . $m[2]], $pid, (int) $m[1], q((float) $valor));
            $cont['precos']++;
        }
        foreach (($p['p'] ?? []) as $verba => $valor) {
            if ($valor === null) {
                continue;
            }
            $sql[] = sprintf("INSERT INTO precos_midia (tabela_preco_id, programa_id, tipo, valor) VALUES (%d, %d, 'cota_patrocinio', %s);", $tab[$p['v'] . '|' . $verba], $pid, q((float) $valor));
            $cont['precos']++;
        }
        if (isset($p['e']) && is_array($p['e'])) {
            $e = array_map('intval', array_pad($p['e'], 6, 0));
            $sql[] = sprintf("INSERT INTO programa_esquemas (programa_id, ins_30, ins_60, vinheta, flash, testemunhal, entrevista) VALUES (%d, %d, %d, %d, %d, %d, %d);", $pid, ...$e);
            $cont['esquemas']++;
        }
    }
}

// Formatos digitais e publicidade legal
$sql[] = "";
$sql[] = "-- Formatos digitais (G1 Maranhão e Imirante) e publicidade legal (Imirante) — mídia kit " . ANO;
$fid = 0;
$cont['formatos'] = 0;
foreach ($DIG as $d) {
    $fid++;
    $v = $veic[$d['v']];
    $sql[] = sprintf(
        "INSERT INTO formatos_midia (id, veiculo_id, sigla, nome, modelo, categoria, entrega_estimada, visibilidade_pct, dispositivo, local_pagina, observacao, publicidade_legal) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d);",
        $fid, $v['id'], q(strtoupper($d['s'])), q($d['n']), q($d['mod']), q($d['cat'] ?: null), q($d['ent'] !== null ? (int) $d['ent'] : null), q($d['vis'] !== null ? (float) $d['vis'] : null),
        q($d['disp'] ?: null), q($d['local'] ?: null), q($d['obs'] ?: null), $d['mod'] === 'cm/col' ? 1 : 0
    );
    $cont['formatos']++;
    if ($d['p'] !== null) {
        $tabela = $tab[$d['v'] . '|MA'] ?? throw new RuntimeException('Tabela MA ausente para ' . $d['v']);
        $sql[] = sprintf("INSERT INTO precos_midia (tabela_preco_id, formato_id, tipo, valor) VALUES (%d, %d, 'unitario', %s);", $tabela, $fid, q((float) $d['p']));
        $cont['precos']++;
    }
}

$sql[] = "";
$sql[] = sprintf('-- Totais: %d programas, %d formatos, %d preços, %d esquemas de patrocínio, %d registros de audiência.', $cont['programas'], $cont['formatos'], $cont['precos'], $cont['esquemas'], $cont['audiencia']);

if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}
file_put_contents($out, implode("\n", $sql) . "\n");
fwrite(STDOUT, sprintf("Gerado %s\n  veículos: %d · tabelas: %d · programas: %d · formatos: %d · preços: %d · esquemas: %d · audiência: %d\n",
    $out, count($veic), count($tab), $cont['programas'], $cont['formatos'], $cont['precos'], $cont['esquemas'], $cont['audiencia']));
