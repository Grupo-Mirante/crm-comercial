<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Helpers\Calendario;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * RN-46 / RN-47 — Excel da PI: uma página A4 na horizontal, faixas na ordem do documento (identificação, comercial,
 * cliente e agência, campanha, inserções, patrocínios, digital, projetos especiais, publicidade legal, entregas especiais,
 * valores, pagamento, cláusulas e assinaturas), com FÓRMULAS que recalculam se alguém alterar quantidade ou desconto.
 * Porta de buildXlsx() do Gerador original, a partir da versão gravada da PI (não muda se a tabela de preços mudar depois).
 * Nome do arquivo: PI_NÚMERO_CLIENTE.xlsx, com _RASCUNHO quando há pendência.
 */
final class ExcelPiService
{
    private const NAVY = '1F3A5F';
    private const LAB = 'E7EBF1';
    private const LINE = 'B7C0CC';
    private const MON = '#,##0.00;-#,##0.00;"-"';
    private const RS = '"R$" #,##0.00;-"R$" #,##0.00;"-"';
    private const PCT = '0.0%;-0.0%;"-"';
    private const INT = '#,##0;-#,##0;"-"';

    private Worksheet $ws;
    private int $r = 1;

    /** @return array{0:string,1:string} caminho temporário e nome do arquivo */
    public static function gerarArquivo(int $piId): array
    {
        $pi = Database::first('SELECT * FROM pis WHERE id = ?', [$piId]) ?? throw new HttpException(404);
        $v = Database::first('SELECT * FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [$piId]) ?? throw new HttpException(404, 'PI sem versão gravada.');
        $R = json_decode((string) $v['resultado'], true) ?: [];
        $snap = json_decode((string) $pi['snapshot'], true) ?: [];
        $ss = (new self())->montar($R, $snap);
        $nome = self::nomeArquivo((string) $pi['numero'], (string) (($snap['f']['cli_fant'] ?? '') ?: ($snap['f']['cli_razao'] ?? '')), (int) $R['pend'] > 0);
        $dir = App::root() . '/storage/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $arq = $dir . '/' . bin2hex(random_bytes(8)) . '.xlsx';
        (new Xlsx($ss))->save($arq);
        $ss->disconnectWorksheets();
        return [$arq, $nome];
    }

    /** RN-46 — PI_NÚMERO_CLIENTE.xlsx ou PI_NÚMERO_CLIENTE_RASCUNHO.xlsx. */
    public static function nomeArquivo(string $numero, string $cliente, bool $rascunho): string
    {
        return 'PI_' . $numero . '_' . slug_arquivo($cliente !== '' ? $cliente : 'cliente', 30) . ($rascunho ? '_RASCUNHO' : '') . '.xlsx';
    }

    private static function col(int $n): string
    {
        return Coordinate::stringFromColumnIndex($n);
    }

    private static function num(mixed $v): float
    {
        return round((float) ($v ?? 0), 2);
    }

    /** @param array<string,mixed> $o */
    private function put(string $rng, mixed $v, array $o = []): void
    {
        [$a, $b] = array_pad(explode(':', $rng), 2, null);
        if ($b && $a !== $b) {
            $this->ws->mergeCells($rng);
        }
        $cell = $this->ws->getCell($a);
        if ($v === '' || $v === null) {
            $cell->setValue(null);
        } elseif (is_array($v) && isset($v['f'])) {
            $cell->setValue('=' . $v['f']);
        } elseif (is_string($v)) {
            // texto sempre como texto: um nome digitado como "=HIPERLINK(...)" não vira fórmula (injeção em planilha)
            $cell->setValueExplicit($v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        } else {
            $cell->setValue($v);
        }
        $st = $this->ws->getStyle($rng);
        $st->getFont()->setName('Arial')->setSize($o['size'] ?? 7.5)->setBold(!empty($o['bold']))->setItalic(!empty($o['italic']))->getColor()->setRGB($o['color'] ?? '000000');
        $st->getAlignment()->setHorizontal($o['h'] ?? Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(($o['wrap'] ?? true) !== false)->setShrinkToFit(!empty($o['shrink']));
        if (isset($o['fmt'])) {
            $st->getNumberFormat()->setFormatCode($o['fmt']);
        }
        if (($o['border'] ?? true) !== false) {
            $st->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINE);
        }
        if (isset($o['fill'])) {
            $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($o['fill']);
        }
    }

    private function lab(string $rng, string $t): void
    {
        $this->put($rng, $t, ['size' => 6.5, 'bold' => true, 'color' => '404040', 'fill' => self::LAB]);
    }

    /** @param array<string,mixed> $o */
    private function val(string $rng, mixed $v, array $o = []): void
    {
        $this->put($rng, $v, $o + ['size' => 8]);
    }

    private function band(string $t): void
    {
        $this->put("A{$this->r}:AR{$this->r}", $t, ['size' => 8, 'bold' => true, 'color' => 'FFFFFF', 'fill' => self::NAVY]);
        $this->ws->getRowDimension($this->r)->setRowHeight(12);
        $this->r++;
    }

    /** @param array<string,mixed> $o */
    private function th(string $rng, mixed $t, array $o = []): void
    {
        $this->put($rng, $t, $o + ['size' => 6.5, 'bold' => true, 'color' => 'FFFFFF', 'fill' => self::NAVY, 'h' => Alignment::HORIZONTAL_CENTER]);
    }

    /** @param list<array{0:string,1:string,2:string,3:mixed,4?:array<string,mixed>}> $lista */
    private function fields(array $lista): void
    {
        foreach ($lista as $it) {
            $this->lab("{$it[0]}{$this->r}:{$it[1]}{$this->r}", $it[2]);
        }
        $this->ws->getRowDimension($this->r)->setRowHeight(9.5);
        $this->r++;
        foreach ($lista as $it) {
            $this->val("{$it[0]}{$this->r}:{$it[1]}{$this->r}", $it[3], $it[4] ?? []);
        }
        $this->ws->getRowDimension($this->r)->setRowHeight(12.5);
        $this->r++;
    }

    private function subTotal(string $label, int $r0, int $r1, string $key, ?int $nIns, array $T): void
    {
        $r = $this->r;
        $this->put("A{$r}:AK{$r}", $label, ['size' => 6.5, 'bold' => true, 'fill' => self::LAB]);
        $this->put("AL{$r}", $nIns === null ? '' : ['f' => "SUM(AL{$r0}:AL{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'center', 'fmt' => self::INT]);
        $this->put("AM{$r}", '', ['fill' => self::LAB]);
        $this->put("AN{$r}", ['f' => "SUM(AN{$r0}:AN{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => self::MON]);
        $this->put("AO{$r}", '', ['fill' => self::LAB]);
        $this->put("AP{$r}", ['f' => "SUM(AP{$r0}:AP{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => self::MON]);
        $this->put("AQ{$r}:AR{$r}", '', ['fill' => self::LAB]);
    }

    /**
     * @param array<string,mixed> $R resultado do cálculo gravado na versão da PI
     * @param array<string,mixed> $snap emissora, cláusulas e campos do formulário como saíram na PI
     */
    public function montar(array $R, array $snap): Spreadsheet
    {
        $f = $snap['f'] ?? [];
        $E = $snap['emissora'] ?? [];
        $T = $R['T'] ?? [];
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('CRM Comercial · Grupo Mirante')->setTitle('PI ' . ($f['num'] ?? ''))->setCompany('Grupo Mirante de Comunicação');
        $this->ws = $ws = $ss->getActiveSheet();
        $ws->setTitle('PI');
        $ws->setShowGridlines(false);
        $W = [11, 26, 17, 6, 6, 10];
        for ($i = 0; $i < 31; $i++) {
            $W[] = 3.3;
        }
        array_push($W, 6, 10.5, 11.5, 6.5, 12, 10, 7.5);
        foreach ($W as $i => $w) {
            $ws->getColumnDimension(self::col($i + 1))->setWidth($w);
        }
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(7.5);

        // ------------------------------------------------ cabeçalho
        $logo = App::root() . '/public/assets/img/logo-grupo-mirante-azul.png';
        $iniTitulo = 'A';
        if (is_file($logo)) {
            $d = new Drawing();
            $d->setName('Grupo Mirante')->setPath($logo)->setHeight(16)->setCoordinates('A1')->setOffsetX(2)->setOffsetY(3)->setWorksheet($ws);
            $iniTitulo = 'C';
        }
        $this->put("{$iniTitulo}1:AF1", 'GRUPO MIRANTE DE COMUNICAÇÃO  ·  PEDIDO DE INSERÇÃO E AUTORIZAÇÃO DE PUBLICIDADE', ['size' => 11, 'bold' => true, 'color' => self::NAVY, 'border' => false]);
        $this->put('AG1:AR1', $E['titulo'] ?? '', ['size' => 11, 'bold' => true, 'color' => 'C00000', 'h' => 'right', 'border' => false]);
        $ws->getRowDimension(1)->setRowHeight(20);
        $linhaEmissora = implode('  ·  ', array_filter([$E['razao'] ?? null, !empty($E['cnpj']) ? 'CNPJ ' . $E['cnpj'] : null, $E['endereco'] ?? null, !empty($E['telefone']) ? 'Tel. ' . $E['telefone'] : null, !empty($E['im']) ? 'Inscr. Mun. ' . $E['im'] : null]));
        $this->put('A2:AF2', $linhaEmissora, ['size' => 6.5, 'color' => '595959', 'border' => false]);
        $pend = (int) ($R['pend'] ?? 0);
        $this->put('AG2:AR2', $pend ? "RASCUNHO: {$pend} pendência(s) na conferência — não enviar ao cliente" : 'Conferida automaticamente: sem pendências',
            ['size' => 6.5, 'bold' => true, 'color' => $pend ? 'C00000' : '375623', 'h' => 'right', 'border' => false]);
        $this->r = 3;
        $tabDesc = (string) Database::value('SELECT t.descricao FROM tabelas_precos t WHERE t.codigo = ? AND t.veiculo_id = ? AND t.ano_vigencia = ?', [$R['tab'] ?? '', (int) ($f['veic'] ?? 0), (int) ($R['ano'] ?? 0)]);
        $this->fields([['A', 'B', 'Nº DA PI', $f['num'] ?? '', ['bold' => true, 'size' => 9]], ['C', 'C', 'Nº DO RP', $f['rp'] ?? ''],
            ['D', 'F', 'TIPO DE PI', ($f['tipo'] ?? '') . (!empty($f['parte']) ? " (PI {$f['parte']} da campanha)" : '')], ['G', 'N', 'PI DE ORIGEM', $f['origem'] ?? ''],
            ['O', 'U', 'DATA DE EMISSÃO', data_br($f['emissao'] ?? ''), ['h' => 'center']], ['V', 'AD', 'TABELA / PRAÇA', ($R['tab'] ?? '') . ($tabDesc ? ' · ' . $tabDesc : '')],
            ['AE', 'AL', 'EXECUTIVO DE CONTAS', $f['exec'] ?? ''], ['AM', 'AN', 'MATRÍCULA', $f['mat'] ?? '', ['h' => 'center']], ['AO', 'AR', 'GESTOR COMERCIAL', $f['gestor'] ?? '']]);
        $ag = ($f['venda'] ?? '') === 'Agência';
        $r = $this->r;
        $this->put("A{$r}:U{$r}", 'AGÊNCIA' . ($ag ? '' : '  (venda direta)'), ['size' => 8, 'bold' => true, 'color' => 'FFFFFF', 'fill' => self::NAVY]);
        $this->put("V{$r}:AR{$r}", 'CLIENTE (ANUNCIANTE)', ['size' => 8, 'bold' => true, 'color' => 'FFFFFF', 'fill' => self::NAVY]);
        $ws->getRowDimension($r)->setRowHeight(12);
        $this->r++;
        $this->fields([['A', 'B', 'AGÊNCIA', $ag ? ($f['ag_nome'] ?? '') : ''], ['C', 'C', 'CNPJ', $ag ? ($f['ag_cnpj'] ?? '') : ''],
            ['D', 'F', 'COMISSÃO', $ag && (float) ($f['ag_com'] ?? 0) ? (float) $f['ag_com'] / 100 : '', ['fmt' => self::PCT, 'h' => 'center']], ['G', 'U', 'CONTATO / E-MAIL', $ag ? ($f['ag_contato'] ?? '') : ''],
            ['V', 'AL', 'RAZÃO SOCIAL', $f['cli_razao'] ?? '', ['bold' => true]], ['AM', 'AN', 'CNPJ / CPF', $f['cli_cnpj'] ?? ''], ['AO', 'AR', 'NOME FANTASIA', $f['cli_fant'] ?? '']]);
        $this->fields([['A', 'B', 'ENDEREÇO', $ag ? ($f['ag_end'] ?? '') : ''], ['C', 'C', 'CIDADE / UF', $ag ? ($f['ag_cid'] ?? '') : ''], ['D', 'F', 'TELEFONE', $ag ? ($f['ag_tel'] ?? '') : ''],
            ['G', 'N', 'CARTA DE CREDENCIAMENTO', $ag ? ($f['ag_carta'] ?? '') : ''], ['O', 'U', 'VALIDADE', $ag ? data_br($f['ag_val'] ?? '') : '', ['h' => 'center']],
            ['V', 'AL', 'ENDEREÇO', implode(' · ', array_filter([$f['cli_end'] ?? '', $f['cli_bairro'] ?? '']))], ['AM', 'AN', 'CEP', $f['cli_cep'] ?? '', ['h' => 'center']], ['AO', 'AR', 'INSCRIÇÃO ESTADUAL', $f['cli_ie'] ?? '']]);
        $this->fields([['A', 'U', 'OBSERVAÇÃO', ''], ['V', 'AE', 'CIDADE / UF', implode(' - ', array_filter([$f['cli_cid'] ?? '', $f['cli_uf'] ?? '']))], ['AF', 'AL', 'CONTATO', $f['cli_contato'] ?? ''],
            ['AM', 'AN', 'TELEFONES', $f['cli_tel'] ?? ''], ['AO', 'AR', 'E-MAIL DE FATURAMENTO', $f['cli_email'] ?? '']]);

        // ------------------------------------------------ campanha
        $this->band('CAMPANHA');
        $months = $R['months'] ?? [];
        $multi = !empty($R['multi']);
        $this->fields([['A', 'B', 'TÍTULO', $f['titulo'] ?? '', ['bold' => true]], ['C', 'F', 'PRODUTO / PROJETO', $f['produto'] ?? ''], ['G', 'N', 'INÍCIO', data_br($f['ini'] ?? ''), ['h' => 'center']],
            ['O', 'V', 'FIM', data_br($f['fim'] ?? ''), ['h' => 'center']], ['W', 'AD', $multi ? 'MESES' : 'MÊS / ANO', implode(' · ', array_map(fn ($M) => $multi ? $M['short'] : $M['label'], $months)), ['h' => 'center']],
            ['AE', 'AK', 'PRAZO DE PAGAMENTO', !empty($f['prazo']) ? $f['prazo'] . ' dias' : '', ['h' => 'center']], ['AL', 'AN', 'PRAÇA DE COBRANÇA', $f['pcob'] ?? ''], ['AO', 'AR', 'FORMA DE PAGAMENTO', $f['forma'] ?? '']]);
        $sub = [];

        // ------------------------------------------------ inserções
        $ins = $R['ins'] ?? [];
        if ($ins) {
            $this->band('COMPRA DE INSERÇÕES' . ($multi ? '  ·  uma linha por mês (o mês está na descrição)' : (isset($months[0]) ? '  ·  grade de ' . $months[0]['label'] : '')));
            $h1 = $this->r;
            $h2 = $h1 + 1;
            $one = !$multi ? ($months[0] ?? null) : null;
            foreach ([['A', 'SIGLA'], ['B', $multi ? 'PROGRAMA · MÊS' : 'PROGRAMA'], ['C', 'DIAS · FAIXA'], ['D', 'DUR (")'], ['E', 'ÍNDICE'], ['F', 'COBRANÇA'], ['AL', 'Nº INS.'], ['AM', 'UNITÁRIO'], ['AN', 'BRUTO'], ['AO', '% DESC.'], ['AP', 'TOTAL C/ DESC.'], ['AQ', 'TOT. IMPACTO'], ['AR', 'GRP']] as [$c, $t]) {
                $this->th("{$c}{$h1}:{$c}{$h2}", $t);
            }
            for ($d = 1; $d <= 31; $d++) {
                $di = $one ? Calendario::diaInfo($one, $f['ini'] ?? null, $f['fim'] ?? null, $d) : ['exists' => false];
                $this->th(self::col(6 + $d) . $h1, $di['exists'] ? $di['letter'] : '', ['size' => 6]);
                $this->th(self::col(6 + $d) . $h2, $d, ['size' => 6]);
            }
            $ws->getRowDimension($h1)->setRowHeight(10);
            $ws->getRowDimension($h2)->setRowHeight(10);
            $this->r += 2;
            $r0 = $this->r;
            foreach ($ins as $x) {
                $r = $this->r;
                $l = $x['l'] ?? [];
                $M = $x['M'] ?? null;
                $bonif = in_array($x['cob'] ?? 'Avulsa', ['Bonificado', 'Reaplicação'], true);
                $this->val("A{$r}", $x['sigla'] ?? '', ['bold' => true]);
                $this->val("B{$r}", ($x['nome'] ?? '') . ($multi && $M ? ' · ' . $M['short'] : ''), ['shrink' => true, 'wrap' => false]);
                $this->val("C{$r}", $x['dias'] ?? '', ['size' => 6.5, 'shrink' => true, 'wrap' => false]);
                $this->val("D{$r}", (int) ($l['dur'] ?? 0) ?: null, ['h' => 'center']);
                $this->val("E{$r}", $x['idx'], ['h' => 'center', 'fmt' => '0.0##']);
                $this->val("F{$r}", $x['cob'] ?? 'Avulsa', ['size' => 6.5, 'h' => 'center']);
                $g = (array) ($x['g'] ?? []);
                for ($d = 1; $d <= 31; $d++) {
                    $c = self::col(6 + $d) . $r;
                    $q = (int) ($g[$d] ?? $g[(string) $d] ?? 0);
                    $this->val($c, $q ?: null, ['h' => 'center', 'size' => 7]);
                    if ($M) {
                        $di = Calendario::diaInfo($M, $f['ini'] ?? null, $f['fim'] ?? null, $d);
                        $fill = $ws->getStyle($c)->getFill();
                        if (!$di['exists']) {
                            $fill->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('8C8C8C');
                        } elseif (!$di['ok']) {
                            $fill->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
                        } elseif (is_array($x['air'] ?? null) && !in_array($di['wd'], $x['air'], true)) {
                            $fill->setFillType(Fill::FILL_PATTERN_LIGHTGRAY)->getStartColor()->setRGB('BFBFBF');
                        } elseif ($di['weekend']) {
                            $fill->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
                        }
                    }
                }
                $this->val("AL{$r}", ['f' => "SUM(G{$r}:AK{$r})"], ['h' => 'center', 'fmt' => self::INT]);
                $this->val("AM{$r}", $x['unit'] !== null ? self::num($x['unit']) : 'Sem preço', ['h' => 'right', 'fmt' => self::MON, 'color' => $x['unit'] === null ? 'C00000' : '000000']);
                $this->val("AN{$r}", ['f' => "IF(ISNUMBER(AM{$r}),AL{$r}*AM{$r},0)"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AO{$r}", (float) ($x['desc'] ?? 0) / 100 ?: null, ['h' => 'center', 'fmt' => self::PCT]);
                $this->val("AP{$r}", ['f' => $bonif ? '0' : "AN{$r}*(1-N(AO{$r}))"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AQ{$r}", $x['imp'] ?: null, ['h' => 'right', 'fmt' => self::INT, 'size' => 7]);
                $this->val("AR{$r}", $x['grp'] ?: null, ['h' => 'right', 'fmt' => '0.0', 'size' => 7]);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
            $r1 = $this->r - 1;
            $r = $this->r;
            $this->put("A{$r}:F{$r}", 'Nº DE INSERÇÕES POR DIA / SUBTOTAL', ['size' => 6.5, 'bold' => true, 'fill' => self::LAB]);
            for ($d = 1; $d <= 31; $d++) {
                $c = self::col(6 + $d);
                $this->put("{$c}{$r}", ['f' => "SUM({$c}{$r0}:{$c}{$r1})"], ['size' => 6.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'center', 'fmt' => '0;-0;""']);
            }
            $this->put("AL{$r}", ['f' => "SUM(AL{$r0}:AL{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'center', 'fmt' => self::INT]);
            $this->put("AM{$r}", '', ['fill' => self::LAB]);
            $this->put("AN{$r}", ['f' => "SUM(AN{$r0}:AN{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => self::MON]);
            $this->put("AO{$r}", '', ['fill' => self::LAB]);
            $this->put("AP{$r}", ['f' => "SUM(AP{$r0}:AP{$r1})"], ['size' => 7.5, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => self::MON]);
            $this->put("AQ{$r}", ($T['imp'] ?? 0) ?: null, ['size' => 7, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => self::INT]);
            $this->put("AR{$r}", ($T['grp'] ?? 0) ?: null, ['size' => 7, 'bold' => true, 'fill' => self::LAB, 'h' => 'right', 'fmt' => '0.0']);
            $sub['ins'] = $r;
            $this->r++;
        }

        // ------------------------------------------------ patrocínios
        $pat = $R['pat'] ?? [];
        if ($pat) {
            $this->band('COMPRA DE PATROCÍNIOS');
            $r = $this->r;
            foreach ([['A', 'SIGLA'], ['B', $multi ? 'PATROCÍNIO · MÊS' : 'PATROCÍNIO'], ['C', 'DIAS · FAIXA'], ['D', 'COTAS'], ['E:F', 'COBRANÇA']] as [$c, $t]) {
                $this->th(str_contains($c, ':') ? str_replace(':', "{$r}:", $c) . $r : $c . $r, $t);
            }
            $E7 = [['G', 'J', '30"'], ['K', 'N', '60"'], ['O', 'R', 'VINHETA'], ['S', 'V', 'FLASH'], ['W', 'Z', 'TESTEMUNHAL'], ['AA', 'AD', 'ENTREVISTA'], ['AE', 'AK', 'OUTROS']];
            foreach ($E7 as [$a, $b, $t]) {
                $this->th("{$a}{$r}:{$b}{$r}", $t);
            }
            foreach ([['AL', 'Nº INS.'], ['AM', 'VALOR DA COTA'], ['AN', 'VALOR TABELA'], ['AO', '% DESC.'], ['AP', 'TOTAL C/ DESC.']] as [$c, $t]) {
                $this->th("{$c}{$r}", $t);
            }
            $this->th("AQ{$r}:AR{$r}", 'OBSERVAÇÃO');
            $ws->getRowDimension($r)->setRowHeight(10);
            $this->r++;
            $r0 = $this->r;
            foreach ($pat as $x) {
                $r = $this->r;
                $bonif = in_array($x['cob'] ?? 'Avulsa', ['Bonificado', 'Reaplicação'], true);
                $this->val("A{$r}", $x['sigla'] ?? '', ['bold' => true]);
                $this->val("B{$r}", ($x['nome'] ?? '') . (!empty($x['mes']) ? ' · ' . $x['mes'] : ''), ['shrink' => true, 'wrap' => false]);
                $this->val("C{$r}", $x['dias'] ?? '', ['size' => 6.5, 'shrink' => true, 'wrap' => false]);
                $this->val("D{$r}", $x['cot'], ['h' => 'center']);
                $this->val("E{$r}:F{$r}", $x['cob'] ?? 'Avulsa', ['size' => 6.5, 'h' => 'center']);
                foreach ($E7 as $k => [$a, $b]) {
                    $this->val("{$a}{$r}:{$b}{$r}", $k < 6 ? (($x['esq'][$k] ?? 0) ?: null) : (($x['outras'] ?? 0) ?: null), ['h' => 'center']);
                }
                $this->val("AL{$r}", ['f' => "SUM(G{$r}:AK{$r})"], ['h' => 'center', 'fmt' => self::INT]);
                $this->val("AM{$r}", $x['valor'] !== null ? self::num($x['valor'] / max(1, $x['cot'])) : 'Sem preço', ['h' => 'right', 'fmt' => self::MON, 'color' => $x['valor'] === null ? 'C00000' : '000000']);
                $this->val("AN{$r}", ['f' => "IF(ISNUMBER(AM{$r}),AM{$r}*D{$r},0)"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AO{$r}", (float) ($x['desc'] ?? 0) / 100 ?: null, ['h' => 'center', 'fmt' => self::PCT]);
                $this->val("AP{$r}", ['f' => $bonif ? '0' : "AN{$r}*(1-N(AO{$r}))"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AQ{$r}:AR{$r}", $x['l']['obs'] ?? '', ['size' => 6.5]);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
            $this->subTotal('SUBTOTAL PATROCÍNIOS', $r0, $this->r - 1, 'pat', 1, $T);
            $sub['pat'] = $this->r;
            $this->r++;
        }

        // ------------------------------------------------ digital
        $dig = $R['dig'] ?? [];
        if ($dig) {
            $this->band('PATROCÍNIO E MÍDIA DIGITAL  ·  formatos mensais: valor proporcional aos dias de cada mês do período');
            $r = $this->r;
            foreach ([['A', 'A', 'SIGLA'], ['B', 'C', 'FORMATO'], ['D', 'F', 'DISPOSITIVO'], ['G', 'Q', 'LOCAL / PÁGINA'], ['R', 'U', 'VISIB.'], ['V', 'AD', 'PERÍODO'], ['AE', 'AK', 'MODELO · COBRANÇA'],
                         ['AL', 'AL', 'DIAS / QTD / IMPR.'], ['AM', 'AM', 'MENSAL / UNIT. / CPM'], ['AN', 'AN', 'VALOR TABELA'], ['AO', 'AO', '% DESC.'], ['AP', 'AP', 'TOTAL C/ DESC.'], ['AQ', 'AR', 'OBSERVAÇÃO']] as [$a, $b, $t]) {
                $this->th("{$a}{$r}:{$b}{$r}", $t);
            }
            $ws->getRowDimension($r)->setRowHeight(10);
            $this->r++;
            $r0 = $this->r;
            foreach ($dig as $x) {
                $r = $this->r;
                $d = $x['formato'] ?? [];
                $mod = $x['mod'];
                $cob = $x['cob'] ?? 'Pago';
                $this->val("A{$r}", $x['sigla'] ?? '', ['bold' => true, 'size' => 7]);
                $this->val("B{$r}:C{$r}", $x['nome'] ?? '', ['size' => 7, 'shrink' => true, 'wrap' => false]);
                $this->val("D{$r}:F{$r}", $d['dispositivo'] ?? '', ['size' => 6.5, 'h' => 'center']);
                $this->val("G{$r}:Q{$r}", $d['local'] ?? '', ['size' => 6.5, 'shrink' => true, 'wrap' => false]);
                $this->val("R{$r}:U{$r}", !empty($d['visibilidade']) ? (float) $d['visibilidade'] / 100 : null, ['h' => 'center', 'fmt' => '0%']);
                $this->val("V{$r}:AD{$r}", implode(' a ', array_filter([data_br($x['ini']), data_br($x['fim'])])) . ($mod === 'Mensal' && $x['dias'] ? " ({$x['dias']} dias)" : ''), ['h' => 'center', 'size' => 7]);
                $desc = $mod === 'Mensal' ? sprintf('Mensal × %s cota(s) × %s mês (%s)', numero($x['qtd']), rtrim(rtrim(number_format((float) $x['meses'], 4, ',', ''), '0'), ','), implode(' + ', $x['mdet'] ?? [])) : $mod;
                $this->val("AE{$r}:AK{$r}", $desc . ' · ' . $cob, ['h' => 'center', 'size' => 6, 'shrink' => true, 'wrap' => false]);
                $this->val("AL{$r}", $mod === 'CPM' ? ((float) ($x['ent'] ?? 0) ?: null) : ($mod === 'Mensal' ? $x['dias'] : $x['qtd']), ['h' => 'center', 'fmt' => self::INT, 'size' => 7]);
                $this->val("AM{$r}", $x['unit'] !== null ? self::num($x['unit']) : 'Sob consulta', ['h' => 'right', 'fmt' => self::MON, 'color' => $x['unit'] === null ? 'C00000' : '000000']);
                $form = $cob === 'Incluso no pacote' ? '0' : ($mod === 'CPM' ? "IF(ISNUMBER(AM{$r}),AL{$r}/1000*AM{$r},0)" : ($mod === 'Mensal' ? sprintf('IF(ISNUMBER(AM%d),AM%d*%s*%s,0)', $r, $r, (string) (float) $x['qtd'], rtrim(rtrim(number_format((float) $x['meses'], 6, '.', ''), '0'), '.') ?: '0') : "IF(ISNUMBER(AM{$r}),AL{$r}*AM{$r},0)"));
                $this->val("AN{$r}", ['f' => $form], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AO{$r}", (float) ($x['desc'] ?? 0) / 100 ?: null, ['h' => 'center', 'fmt' => self::PCT]);
                $this->val("AP{$r}", ['f' => ($cob === 'Bonificado' || $cob === 'Incluso no pacote') ? '0' : "AN{$r}*(1-N(AO{$r}))"], ['h' => 'right', 'fmt' => self::MON]);
                $obs = implode(' · ', array_filter([$x['l']['obs'] ?? '', ($mod !== 'CPM' && !empty($d['entrega'])) ? 'entrega est. ' . numero($d['entrega']) : '']));
                $this->val("AQ{$r}:AR{$r}", $obs, ['size' => 6]);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
            $this->subTotal('SUBTOTAL DIGITAL', $r0, $this->r - 1, 'dig', null, $T);
            $sub['dig'] = $this->r;
            $this->r++;
        }

        // ------------------------------------------------ projetos especiais
        $prj = $R['prj'] ?? [];
        if ($prj) {
            $this->band('PROJETOS ESPECIAIS  ·  venda por cotas');
            $r = $this->r;
            foreach ([['A', 'A', 'CÓDIGO'], ['B', 'C', 'PROJETO'], ['D', 'F', 'COTA'], ['G', 'U', 'VENDA ATÉ · VÁLIDO PARA'], ['V', 'AD', 'PERÍODO DA CAMPANHA'], ['AE', 'AK', 'COBRANÇA'],
                         ['AL', 'AL', 'COTAS'], ['AM', 'AM', 'VALOR DA COTA'], ['AN', 'AN', 'VALOR TABELA'], ['AO', 'AO', '% DESC.'], ['AP', 'AP', 'TOTAL C/ DESC.'], ['AQ', 'AR', 'OBSERVAÇÃO']] as [$a, $b, $t]) {
                $this->th("{$a}{$r}:{$b}{$r}", $t);
            }
            $ws->getRowDimension($r)->setRowHeight(10);
            $this->r++;
            $r0 = $this->r;
            $nomesVeic = [];
            foreach (Database::all('SELECT id, nome FROM veiculos') as $vv) {
                $nomesVeic[(int) $vv['id']] = $vv['nome'];
            }
            foreach ($prj as $x) {
                $r = $this->r;
                $this->val("A{$r}", $x['codigo'] ?? '', ['bold' => true, 'size' => 7]);
                $this->val("B{$r}:C{$r}", $x['nome'], ['size' => 7, 'shrink' => true, 'wrap' => false]);
                $this->val("D{$r}:F{$r}", $x['cota'], ['h' => 'center', 'size' => 7]);
                $this->val("G{$r}:U{$r}", data_br($x['ate'] ?? '') . ' · ' . implode(', ', array_map(fn ($i) => $nomesVeic[$i] ?? $i, $x['veiculos'] ?? [])), ['h' => 'center', 'size' => 6, 'shrink' => true, 'wrap' => false]);
                $this->val("V{$r}:AD{$r}", implode(' a ', array_filter([data_br($f['ini'] ?? ''), data_br($f['fim'] ?? '')])), ['h' => 'center', 'size' => 7]);
                $this->val("AE{$r}:AK{$r}", $x['cob'] ?? 'Pago', ['h' => 'center', 'size' => 7]);
                $this->val("AL{$r}", $x['q'], ['h' => 'center', 'fmt' => self::INT, 'size' => 7]);
                $this->val("AM{$r}", $x['unit'] !== null ? self::num($x['unit']) : null, ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AN{$r}", ['f' => "N(AL{$r})*N(AM{$r})"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AO{$r}", (float) ($x['desc'] ?? 0) / 100 ?: null, ['h' => 'center', 'fmt' => self::PCT]);
                $this->val("AP{$r}", ['f' => ($x['cob'] ?? '') === 'Bonificado' ? '0' : "AN{$r}*(1-N(AO{$r}))"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AQ{$r}:AR{$r}", $x['l']['obs'] ?? '', ['size' => 6]);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
            $this->subTotal('SUBTOTAL PROJETOS ESPECIAIS', $r0, $this->r - 1, 'prj', null, $T);
            $sub['prj'] = $this->r;
            $this->r++;
        }

        // ------------------------------------------------ publicidade legal
        $avi = $R['avi'] ?? [];
        if ($avi) {
            $this->band('PUBLICIDADE LEGAL E AVISOS  ·  valor = colunas × cm × publicações × R$ por cm/col');
            $r = $this->r;
            foreach ([['A', 'A', 'SIGLA'], ['B', 'C', 'MATERIAL / TÍTULO'], ['D', 'F', 'TIPO'], ['G', 'K', 'CÓDIGO'], ['L', 'U', 'COLUNAS × CM'], ['V', 'AK', 'DATAS DE PUBLICAÇÃO'],
                         ['AL', 'AL', 'PUBLIC.'], ['AM', 'AM', 'R$ CM/COL'], ['AN', 'AN', 'VALOR TABELA'], ['AO', 'AO', '% DESC.'], ['AP', 'AP', 'NEGOCIADO'], ['AQ', 'AR', 'CM/COL TOTAL']] as [$a, $b, $t]) {
                $this->th("{$a}{$r}:{$b}{$r}", $t);
            }
            $ws->getRowDimension($r)->setRowHeight(10);
            $this->r++;
            $r0 = $this->r;
            foreach ($avi as $x) {
                $r = $this->r;
                $l = $x['l'] ?? [];
                $this->val("A{$r}", $x['sigla'] ?? '', ['bold' => true, 'size' => 7]);
                $this->val("B{$r}:C{$r}", $l['mat'] ?? '', ['size' => 7]);
                $this->val("D{$r}:F{$r}", $x['nome'], ['h' => 'center', 'size' => 6.5]);
                $this->val("G{$r}:K{$r}", $l['cod'] ?? '', ['size' => 7]);
                $this->val("L{$r}:U{$r}", ((float) ($l['col'] ?? 0)) . ' col × ' . ((float) ($l['cm'] ?? 0)) . ' cm', ['h' => 'center']);
                $this->val("V{$r}:AK{$r}", $l['datas'] ?? '', ['size' => 7]);
                $this->val("AL{$r}", $x['pub'], ['h' => 'center', 'fmt' => self::INT]);
                $this->val("AM{$r}", $x['unit'] !== null ? self::num($x['unit']) : 'Sem preço', ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AQ{$r}:AR{$r}", $x['cmcol'], ['h' => 'center', 'fmt' => '0.##']);
                $this->val("AN{$r}", ['f' => "IF(ISNUMBER(AM{$r}),AQ{$r}*AL{$r}*AM{$r},0)"], ['h' => 'right', 'fmt' => self::MON]);
                $this->val("AO{$r}", (float) ($x['desc'] ?? 0) / 100 ?: null, ['h' => 'center', 'fmt' => self::PCT]);
                $this->val("AP{$r}", ['f' => "AN{$r}*(1-N(AO{$r}))"], ['h' => 'right', 'fmt' => self::MON]);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
            $this->subTotal('SUBTOTAL PUBLICIDADE LEGAL', $r0, $this->r - 1, 'avi', null, $T);
            $sub['avi'] = $this->r;
            $this->r++;
            if (!empty($f['texto'])) {
                $this->put("A{$this->r}:AR{$this->r}", 'TEXTO DO ANÚNCIO: ' . $f['texto'], ['size' => 6.5]);
                $ws->getRowDimension($this->r)->setRowHeight(min(60, 10 + (int) ceil(mb_strlen((string) $f['texto']) / 260) * 8));
                $this->r++;
            }
        }

        // ------------------------------------------------ entregas especiais
        $ent = $R['ent'] ?? [];
        if ($ent) {
            $this->band('ENTREGAS ESPECIAIS');
            $r = $this->r;
            foreach ([['A', 'A', 'ENTREGA'], ['B', 'C', 'ESPECIFICAÇÃO'], ['D', 'F', 'QTD'], ['G', 'U', 'ÁREA RESPONSÁVEL'], ['V', 'AK', 'Nº DO BRIEFING'], ['AL', 'AR', 'AVAL DO JURÍDICO']] as [$a, $b, $t]) {
                $this->th("{$a}{$r}:{$b}{$r}", $t);
            }
            $this->r++;
            foreach ($ent as $e) {
                $r = $this->r;
                $this->val("A{$r}", $e['tipo'] ?? '', ['size' => 6.5]);
                $this->val("B{$r}:C{$r}", $e['esp'] ?? '', ['size' => 6.5]);
                $this->val("D{$r}:F{$r}", $e['qtd'] ?? '', ['h' => 'center']);
                $this->val("G{$r}:U{$r}", $e['area'] ?? '');
                $this->val("V{$r}:AK{$r}", $e['brief'] ?? '', ['h' => 'center']);
                $this->val("AL{$r}:AR{$r}", $e['jur'] ?? '', ['h' => 'center']);
                $ws->getRowDimension($r)->setRowHeight(11.5);
                $this->r++;
            }
        }

        // ------------------------------------------------ valores
        $this->band('VALORES');
        $vr = $this->r + 1;
        $chaves = array_values(array_filter(['ins', 'pat', 'dig', 'prj', 'avi'], fn ($k) => isset($sub[$k])));
        $tabF = implode('+', array_map(fn ($x) => "N({$x})", array_merge(array_map(fn ($k) => 'AN' . $sub[$k], $chaves), ["A{$vr}"])));
        $negF = implode('+', array_map(fn ($x) => "N({$x})", array_merge(array_map(fn ($k) => 'AP' . $sub[$k], $chaves), ["A{$vr}"])));
        $comPct = (float) ($T['comPct'] ?? 0);
        $comTxt = rtrim(rtrim(number_format($comPct, 6, '.', ''), '0'), '.');
        $comTxt = $comTxt === '' ? '0' : $comTxt;
        $this->fields([['A', 'B', 'PRODUÇÃO / TAXAS', self::num($T['producao'] ?? 0) ?: null, ['fmt' => self::MON, 'h' => 'right']],
            ['C', 'F', 'VALOR TOTAL DE TABELA', ['f' => $tabF], ['fmt' => self::RS, 'h' => 'right']],
            ['G', 'N', 'BONIFICADO', self::num($T['bonif'] ?? 0) ?: null, ['fmt' => self::MON, 'h' => 'right']],
            ['O', 'V', 'DESCONTO NOMINAL', ['f' => "C{$vr}-AE{$vr}"], ['fmt' => self::MON, 'h' => 'right']],
            ['W', 'AD', 'DESCONTO MÉDIO', ['f' => "IF(C{$vr}>0,O{$vr}/C{$vr},0)"], ['fmt' => self::PCT, 'h' => 'center']],
            ['AE', 'AM', 'VALOR NEGOCIADO', ['f' => $negF], ['fmt' => self::RS, 'h' => 'right', 'bold' => true, 'size' => 9]],
            ['AN', 'AO', 'COMISSÃO AGÊNCIA', ['f' => "AE{$vr}*{$comTxt}"], ['fmt' => self::MON, 'h' => 'right']],
            ['AP', 'AR', 'VALOR LÍQUIDO', ['f' => "AE{$vr}-AN{$vr}"], ['fmt' => self::RS, 'h' => 'right', 'bold' => true, 'size' => 9]]]);
        if (!empty($R['isTV']) && !empty($T['imp'])) {
            $this->put("A{$this->r}:AR{$this->r}", sprintf('Impacto total: %s   ·   GRP total: %s   ·   CPM: R$ %s', numero($T['imp']), number_format((float) $T['grp'], 1, ',', '.'), number_format((float) $T['cpm'], 2, ',', '.')),
                ['size' => 6.5, 'border' => false, 'color' => '595959']);
            $this->r++;
        }

        // ------------------------------------------------ pagamento e observações
        $this->band('PAGAMENTO E OBSERVAÇÕES');
        $parcs = $R['parcs'] ?? [];
        $parcTxt = implode('    ', array_map(fn ($p, $i) => ($i + 1) . 'ª: ' . data_br($p['venc'] ?? '') . ' — R$ ' . number_format((float) ($p['val'] ?? 0), 2, ',', '.'), $parcs, array_keys($parcs)));
        $obs = (string) ($f['obs'] ?? '');
        if (!empty($f['gs_nome'])) {
            $obs = trim($obs . ($obs !== '' ? ' · ' : '') . 'Desconto/bonificação aprovados por ' . $f['gs_nome'] . ' em ' . data_br($f['gs_data'] ?? ''));
        }
        $this->fields([['A', 'U', 'PARCELAS (' . count($parcs) . ')  ·  sem data definida, vence no último dia útil do mês seguinte a cada mês de veiculação', $parcTxt, ['size' => 7]],
            ['V', 'AR', 'OBSERVAÇÕES', $obs, ['size' => 7]]]);
        if (mb_strlen($obs) > 120 || mb_strlen($parcTxt) > 120) {
            $ws->getRowDimension($this->r - 1)->setRowHeight(22);
        }

        // ------------------------------------------------ cláusulas (RN-47)
        $this->band('CLÁUSULAS');
        $cl = $snap['clausulas'] ?? [];
        $linhas = [];
        for ($i = 0; $i < count($cl); $i += 2) {
            $linhas[] = trim(($cl[$i] ?? '') . '  ' . ($cl[$i + 1] ?? ''));
        }
        $linhas[] = implode('  ', array_filter([$E['cl6'] ?? '', $E['cl7'] ?? '']));
        $linhas[] = (string) ($E['atendimento'] ?? '');
        foreach (array_filter($linhas) as $t) {
            $this->put("A{$this->r}:AR{$this->r}", $t, ['size' => 6, 'border' => false]);
            $ws->getRowDimension($this->r)->setRowHeight(mb_strlen($t) > 330 ? 17 : 9);
            $this->r++;
        }
        $this->r++;
        $cidade = (string) (Database::value('SELECT cidade FROM tabelas_precos WHERE codigo = ? AND veiculo_id = ? AND ano_vigencia = ?', [$R['tab'] ?? '', (int) ($f['veic'] ?? 0), (int) ($R['ano'] ?? 0)]) ?: Configuracao::texto('praca_padrao_cidade', 'São Luís - MA'));
        $this->put("A{$this->r}:C{$this->r}", $cidade . ', ' . (data_br($f['emissao'] ?? '') ?: '____/____/______'), ['size' => 7.5, 'border' => false]);
        $this->put("D{$this->r}:AR{$this->r}", 'AUTORIZAMOS O PRESENTE CONTRATO CONFORME ESPECIFICAÇÕES E CLÁUSULAS AQUI CONTIDAS.', ['size' => 7, 'bold' => true, 'border' => false, 'h' => 'center']);
        $this->r += 2;
        $sig = [['A', 'C', 'CLIENTE' . (!empty($f['ac_nome']) ? ' — ' . $f['ac_nome'] : '')], ['G', 'U', $ag ? 'AGÊNCIA — ' . ($f['ag_nome'] ?? '') : 'AGÊNCIA'],
            ['W', 'AL', 'EXECUTIVO DE CONTAS — ' . ($f['exec'] ?? '')], ['AN', 'AR', 'GESTOR COMERCIAL — ' . ($f['gestor'] ?? '')]];
        foreach ($sig as [$a, $b]) {
            $ws->getStyle("{$a}{$this->r}:{$b}{$this->r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        }
        $ws->getRowDimension($this->r)->setRowHeight(16);
        $this->r++;
        foreach ($sig as [$a, $b, $t]) {
            $this->put("{$a}{$this->r}:{$b}{$this->r}", $t, ['size' => 6.5, 'border' => false, 'h' => 'center']);
        }

        // ------------------------------------------------ página: A4 horizontal, 1 página
        $ps = $ws->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(1)
            ->setHorizontalCentered(true)->setPrintArea('A1:AR' . $this->r);
        $ws->getPageMargins()->setLeft(0.25)->setRight(0.25)->setTop(0.3)->setBottom(0.3)->setHeader(0.1)->setFooter(0.1);
        $ws->getHeaderFooter()->setOddFooter('&L&6Gerado pelo CRM Comercial do Grupo Mirante&R&6Página &P de &N');
        $ws->setSelectedCell('A1');
        return $ss;
    }
}
