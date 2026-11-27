<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\View;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF da PI: uma única página A4 na horizontal, com o mesmo conteúdo do Excel e da impressão (identificação, agência e
 * cliente, campanha, inserções, patrocínios, digital, projetos, publicidade legal, entregas, valores, pagamento, cláusulas
 * e assinaturas), a partir da versão gravada da PI. Layout inspirado nas planilhas-modelo dos veículos, em versão mais limpa.
 *
 * Para caber sempre em uma página, o documento é renderizado em escala 1 e, se passar de uma página, renderizado de novo
 * em escalas menores (fontes e espaçamentos proporcionais) até caber. Nome do arquivo: PI_NÚMERO_CLIENTE.pdf, com
 * _RASCUNHO quando há pendência.
 */
final class PdfPiService
{
    /** Escalas tentadas, da maior para a menor. A última é usada mesmo que ainda não caiba. */
    public const ESCALAS = [1.0, 0.92, 0.85, 0.78, 0.72, 0.66, 0.6, 0.55, 0.5, 0.45];

    /** @return array{0:string,1:string} caminho do arquivo temporário e nome para download */
    public static function gerarArquivo(int $piId): array
    {
        $pi = Database::first('SELECT * FROM pis WHERE id = ?', [$piId]) ?? throw new HttpException(404);
        $v = Database::first('SELECT * FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [$piId]) ?? throw new HttpException(404, 'PI sem versão gravada.');
        $R = json_decode((string) $v['resultado'], true) ?: [];
        $snap = json_decode((string) $pi['snapshot'], true) ?: [];
        [$pdf] = self::renderizar($pi, $R, $snap);
        $nome = self::nomeArquivo((string) $pi['numero'], (string) (($snap['f']['cli_fant'] ?? '') ?: ($snap['f']['cli_razao'] ?? '')), (int) ($R['pend'] ?? 0) > 0);
        $dir = App::root() . '/storage/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $arq = $dir . '/' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($arq, $pdf);
        return [$arq, $nome];
    }

    /** PI_NÚMERO_CLIENTE.pdf ou PI_NÚMERO_CLIENTE_RASCUNHO.pdf (mesma regra do Excel, RN-46). */
    public static function nomeArquivo(string $numero, string $cliente, bool $rascunho): string
    {
        return 'PI_' . $numero . '_' . slug_arquivo($cliente !== '' ? $cliente : 'cliente', 30) . ($rascunho ? '_RASCUNHO' : '') . '.pdf';
    }

    /**
     * Renderiza o PDF reduzindo a escala até caber em uma página.
     *
     * @param array<string,mixed> $pi
     * @param array<string,mixed> $R
     * @param array<string,mixed> $snap
     * @param string|null $cidade cidade da assinatura (padrão: configuração praca_padrao_cidade)
     * @return array{0:string,1:int,2:float} bytes do PDF, número de páginas e escala usada
     */
    public static function renderizar(array $pi, array $R, array $snap, ?string $cidade = null): array
    {
        $raiz = App::root();
        $cidade ??= Configuracao::texto('praca_padrao_cidade', 'São Luís - MA');
        $logo = $raiz . '/public/assets/img/logo-grupo-mirante-azul.png';
        $dados = ['pi' => $pi, 'R' => $R, 'snap' => $snap, 'cidade' => $cidade, 'logo' => is_file($logo) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logo)) : null];
        $pdf = '';
        $paginas = 0;
        $z = 1.0;
        foreach (self::ESCALAS as $z) {
            $html = View::render('pis/pdf', $dados + ['z' => $z], null);
            [$pdf, $paginas] = self::dompdf($html, $raiz);
            if ($paginas <= 1) {
                break;
            }
        }
        return [$pdf, $paginas, $z];
    }

    /** @return array{0:string,1:int} */
    private static function dompdf(string $html, string $raiz): array
    {
        $tmp = $raiz . '/storage/cache/dompdf';
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0750, true);
        }
        $o = new Options();
        $o->setIsRemoteEnabled(false);
        $o->setIsPhpEnabled(false);
        $o->setIsJavascriptEnabled(false);
        $o->setChroot([$raiz . '/public/assets']);
        $o->setDefaultFont('Helvetica');
        $o->setDpi(96);
        if (is_dir($tmp) && is_writable($tmp)) {
            $o->setTempDir($tmp);
            $o->setFontCache($tmp);
        }
        $d = new Dompdf($o);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper('A4', 'landscape');
        $d->render();
        $paginas = $d->getCanvas()->get_page_count();
        return [(string) $d->output(), $paginas];
    }
}
