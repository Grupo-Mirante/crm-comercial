<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\BusinessException;
use App\Core\Config;
use RuntimeException;

/**
 * Backup e restauração do banco (mysqldump / mysql) e dos anexos.
 * A senha do MySQL vai num arquivo temporário de opções (permissão 600), nunca na linha de comando.
 * Arquivos: storage/backups/crm_AAAAMMDD_HHMMSS.sql.gz (banco) e crm_anexos_AAAAMMDD_HHMMSS.tar.gz (anexos).
 */
final class BackupService
{
    public static function pasta(): string
    {
        $d = (string) Config::get('app.backup.dir', '') ?: App::root() . '/storage/backups';
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
        return rtrim($d, '/');
    }

    private static function opcoes(): string
    {
        $c = Config::get('database');
        $arq = tempnam(sys_get_temp_dir(), 'crmdb');
        $txt = "[client]\nuser=\"" . addcslashes((string) $c['username'], '"\\') . "\"\npassword=\"" . addcslashes((string) $c['password'], '"\\') . "\"\n";
        $txt .= !empty($c['socket']) ? 'socket="' . addcslashes((string) $c['socket'], '"\\') . "\"\n" : 'host="' . $c['host'] . "\"\nport=" . (int) $c['port'] . "\n";
        file_put_contents($arq, $txt);
        chmod($arq, 0600);
        return $arq;
    }

    /** Gera o backup do banco. @return string caminho do arquivo */
    public static function gerar(string $origem = 'manual'): string
    {
        $db = (string) Config::get('database.database');
        $destino = self::pasta() . '/crm_' . date('Ymd_His') . '.sql.gz';
        $opc = self::opcoes();
        try {
            $cmd = [(string) Config::get('app.backup.mysqldump', 'mysqldump'), '--defaults-extra-file=' . $opc, '--single-transaction', '--quick', '--routines', '--triggers', '--events',
                '--hex-blob', '--default-character-set=utf8mb4', '--no-tablespaces', '--set-gtid-purged=OFF', $db];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) {
                throw new RuntimeException('Não foi possível executar o mysqldump. Confira MYSQLDUMP_PATH no .env.');
            }
            $gz = gzopen($destino, 'wb6');
            if ($gz === false) {
                throw new RuntimeException('Sem permissão de escrita na pasta de backup: ' . self::pasta());
            }
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 1 << 16);
                if ($chunk !== false && $chunk !== '') {
                    gzwrite($gz, $chunk);
                }
            }
            gzclose($gz);
            $erro = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
            if ($code !== 0) {
                // Versões antigas do mysqldump não conhecem --set-gtid-purged: tenta de novo sem ele
                if (str_contains((string) $erro, 'set-gtid-purged')) {
                    @unlink($destino);
                    return self::gerarSemGtid($origem, $opc, $db);
                }
                @unlink($destino);
                throw new RuntimeException('mysqldump falhou: ' . trim((string) $erro));
            }
        } finally {
            if (is_file($opc)) {
                @unlink($opc);
            }
        }
        chmod($destino, 0640);
        self::limpar();
        self::registrar($origem, $destino);
        return $destino;
    }

    private static function gerarSemGtid(string $origem, string $opcIgnorado, string $db): string
    {
        $destino = self::pasta() . '/crm_' . date('Ymd_His') . '.sql.gz';
        $opc = self::opcoes();
        try {
            $cmd = [(string) Config::get('app.backup.mysqldump', 'mysqldump'), '--defaults-extra-file=' . $opc, '--single-transaction', '--quick', '--routines', '--triggers', '--hex-blob', '--default-character-set=utf8mb4', $db];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $gz = gzopen($destino, 'wb6');
            while (!feof($pipes[1])) {
                gzwrite($gz, (string) fread($pipes[1], 1 << 16));
            }
            gzclose($gz);
            $erro = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($proc) !== 0) {
                @unlink($destino);
                throw new RuntimeException('mysqldump falhou: ' . trim((string) $erro));
            }
        } finally {
            @unlink($opc);
        }
        self::registrar($origem, $destino);
        return $destino;
    }

    /** Registra o backup (sem impedir o backup se o banco estiver vazio ou danificado — caso típico antes de restaurar). */
    private static function registrar(string $origem, string $destino): void
    {
        try {
            Configuracao::definir('backup_ultima_execucao', date('Y-m-d H:i:s'));
            Auditoria::registrar('backup_gerado', 'backup', basename($destino), "Backup do banco ({$origem}): " . basename($destino) . ' · ' . number_format(filesize($destino) / 1048576, 2, ',', '.') . ' MB');
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Backup gerado, mas não foi possível registrá-lo no banco: ' . $e->getMessage(), ['arquivo' => basename($destino)]);
        }
    }

    /** Compacta os anexos (storage/uploads). */
    public static function gerarAnexos(): string
    {
        $destino = self::pasta() . '/crm_anexos_' . date('Ymd_His') . '.tar.gz';
        $proc = proc_open(['tar', '-czf', $destino, '-C', App::root() . '/storage', 'uploads'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $erro = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proc) !== 0) {
            throw new RuntimeException('Não foi possível compactar os anexos: ' . trim((string) $erro));
        }
        Auditoria::registrar('backup_anexos', 'backup', basename($destino), 'Backup dos anexos: ' . basename($destino));
        return $destino;
    }

    /** Restaura um backup do banco (somente pelo console, com confirmação). */
    public static function restaurar(string $arquivo): void
    {
        $caminho = self::caminho(basename($arquivo));
        $opc = self::opcoes();
        try {
            $proc = proc_open([(string) Config::get('app.backup.mysql', 'mysql'), '--defaults-extra-file=' . $opc, '--default-character-set=utf8mb4', (string) Config::get('database.database')],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) {
                throw new RuntimeException('Não foi possível executar o cliente mysql.');
            }
            $gz = gzopen($caminho, 'rb');
            while (!gzeof($gz)) {
                fwrite($pipes[0], (string) gzread($gz, 1 << 16));
            }
            gzclose($gz);
            fclose($pipes[0]);
            $erro = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($proc) !== 0) {
                throw new RuntimeException('Restauração falhou: ' . trim((string) $erro));
            }
        } finally {
            @unlink($opc);
        }
        try {
            Auditoria::registrar('backup_restaurado', 'backup', basename($caminho), 'Banco restaurado a partir de ' . basename($caminho), null, null, null, 'console');
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Banco restaurado, mas sem registro de auditoria: ' . $e->getMessage());
        }
    }

    /** @return list<array{nome:string,tamanho:int,data:string,tipo:string}> */
    public static function listar(): array
    {
        $out = [];
        foreach (glob(self::pasta() . '/crm_*.{sql.gz,tar.gz}', GLOB_BRACE) ?: [] as $f) {
            $out[] = ['nome' => basename($f), 'tamanho' => (int) filesize($f), 'data' => date('Y-m-d H:i:s', (int) filemtime($f)), 'tipo' => str_ends_with($f, '.tar.gz') ? 'Anexos' : 'Banco de dados'];
        }
        usort($out, fn ($a, $b) => strcmp($b['data'], $a['data']));
        return $out;
    }

    public static function caminho(string $nome): string
    {
        if (!preg_match('/^crm_(anexos_)?\d{8}_\d{6}\.(sql|tar)\.gz$/', $nome)) {
            throw new BusinessException('Arquivo de backup inválido.');
        }
        $p = self::pasta() . '/' . $nome;
        if (!is_file($p)) {
            throw new BusinessException('Backup não encontrado.');
        }
        return $p;
    }

    /** Apaga backups mais antigos que a retenção configurada (BACKUP_RETENCAO_DIAS). */
    public static function limpar(): int
    {
        $dias = (int) Config::get('app.backup.retencao_dias', 30);
        if ($dias < 1) {
            return 0;
        }
        $n = 0;
        foreach (glob(self::pasta() . '/crm_*.gz') ?: [] as $f) {
            if (filemtime($f) < time() - $dias * 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
