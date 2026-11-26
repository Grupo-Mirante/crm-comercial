<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\BusinessException;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Anexos da PI (aceite do cliente, briefing, aval do jurídico, comprovante de veiculação).
 * Proteção: arquivos fora da pasta pública (storage/uploads), nome aleatório, tipo conferido pelo conteúdo real
 * (não pela extensão), limite de tamanho e download só para quem pode ver a PI.
 */
final class AnexoService
{
    public const TIPOS = ['aceite' => 'Aceite do cliente', 'briefing' => 'Briefing', 'juridico' => 'Aval do jurídico', 'comprovante' => 'Comprovante de veiculação', 'carta' => 'Carta de credenciamento', 'outro' => 'Outro'];
    private const EXT = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'message/rfc822' => 'eml', 'application/vnd.ms-outlook' => 'msg', 'text/plain' => 'txt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/CDFV2' => 'msg'];

    /** @param array<string,mixed> $arquivo item de $_FILES */
    public static function enviar(int $piId, string $tipo, array $arquivo): int
    {
        $pi = PiService::obter($piId);
        if (!self::podeAnexar($pi)) {
            throw new HttpException(403, 'Você não pode anexar arquivos a esta PI.');
        }
        if (!isset(self::TIPOS[$tipo])) {
            throw new BusinessException('Escolha o tipo do anexo.');
        }
        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($arquivo['tmp_name'] ?? '')) && PHP_SAPI !== 'cli') {
            throw new BusinessException(match ((int) ($arquivo['error'] ?? 4)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que o limite do servidor.',
                UPLOAD_ERR_NO_FILE => 'Escolha o arquivo.',
                default => 'Não foi possível receber o arquivo. Tente de novo.',
            });
        }
        $tmp = (string) $arquivo['tmp_name'];
        $tamanho = (int) filesize($tmp);
        $max = (int) Config::get('app.uploads.max_mb', 10) * 1024 * 1024;
        if ($tamanho <= 0 || $tamanho > $max) {
            throw new BusinessException('O arquivo deve ter até ' . Config::get('app.uploads.max_mb', 10) . ' MB.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $permitidos = array_merge((array) Config::get('app.uploads.tipos', []), ['application/CDFV2']);
        if (!in_array($mime, $permitidos, true) || !isset(self::EXT[$mime])) {
            throw new BusinessException('Tipo de arquivo não aceito (' . $mime . '). Envie PDF, imagem (JPG/PNG), e-mail (.eml/.msg), Word ou Excel.');
        }
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . self::EXT[$mime];
        $dest = App::root() . '/storage/uploads/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0750, true);
        }
        $ok = PHP_SAPI === 'cli' ? copy($tmp, $dest) : move_uploaded_file($tmp, $dest);
        if (!$ok) {
            throw new BusinessException('Não foi possível gravar o arquivo no servidor.');
        }
        chmod($dest, 0640);
        $nome = mb_substr(preg_replace('/[^\pL\pN ._()-]+/u', '_', basename((string) ($arquivo['name'] ?? 'arquivo'))) ?: 'arquivo', 0, 200);
        $id = Database::insert('anexos', ['entidade' => 'pi', 'entidade_id' => $piId, 'tipo' => $tipo, 'nome_original' => $nome, 'caminho' => $rel, 'mime' => $mime,
            'tamanho' => $tamanho, 'sha256' => hash_file('sha256', $dest), 'enviado_por' => (int) Auth::id()]);
        PiService::historico($piId, null, null, 'Anexo incluído: ' . self::TIPOS[$tipo] . ' (' . $nome . ')');
        Auditoria::registrar('anexo_incluido', 'pi', $piId, "Anexo {$nome} ({$tipo})");
        return $id;
    }

    /** @return array{0:string,1:array<string,mixed>} caminho e registro, com permissão conferida */
    public static function abrir(int $id): array
    {
        $a = Database::first('SELECT * FROM anexos WHERE id = ? AND excluido_em IS NULL', [$id]) ?? throw new HttpException(404, 'Anexo não encontrado.');
        if ($a['entidade'] === 'pi') {
            PiService::obter((int) $a['entidade_id']);
        }
        $path = App::root() . '/storage/uploads/' . $a['caminho'];
        if (!is_file($path) || str_contains((string) $a['caminho'], '..')) {
            throw new HttpException(404, 'Arquivo não encontrado no servidor.');
        }
        return [$path, $a];
    }

    public static function excluir(int $id): void
    {
        [, $a] = self::abrir($id);
        $pi = PiService::obter((int) $a['entidade_id']);
        if ((int) $a['enviado_por'] !== Auth::id() && !Auth::isAdmin()) {
            throw new HttpException(403, 'Só quem enviou pode excluir o anexo.');
        }
        if (in_array($pi['situacao'], ['Aprovada', 'Em programação', 'Em veiculação', 'Pronta para faturamento', 'Faturada', 'Encerrada'], true) && !Auth::isAdmin()) {
            throw new BusinessException('A PI já foi aprovada: o anexo faz parte do registro e não pode ser excluído.');
        }
        Database::query('UPDATE anexos SET excluido_em = NOW() WHERE id = ?', [$id]);
        PiService::historico((int) $a['entidade_id'], null, null, 'Anexo excluído: ' . $a['nome_original']);
        Auditoria::registrar('anexo_excluido', 'pi', (int) $a['entidade_id'], 'Anexo excluído: ' . $a['nome_original']);
    }

    /** @param array<string,mixed> $pi */
    public static function podeAnexar(array $pi): bool
    {
        return PiService::podeAlterar($pi) || Auth::can('conferencia.conferir') || Auth::can('operacao.finalizar') || Auth::can('financeiro.faturar');
    }
}
