<?php

declare(strict_types=1);

namespace App\Services\Instalacao;

use App\Core\App;
use App\Core\SqlScript;
use PDO;

/**
 * Aplica as migrations (database/migrations/*.sql) e os dados iniciais de produção (database/seeds/producao/*.sql),
 * cada arquivo uma única vez, registrando-os na tabela migrations.
 */
final class Migrador
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function prepararControle(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            arquivo VARCHAR(160) NOT NULL,
            tipo ENUM(\'migration\',\'seed\') NOT NULL,
            aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_migrations (arquivo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
    }

    /** @return list<string> arquivos aplicados nesta execução */
    public function migrar(bool $comSeedsProducao = true, ?callable $log = null): array
    {
        $this->prepararControle();
        $feitos = array_column($this->pdo->query('SELECT arquivo FROM migrations')->fetchAll(PDO::FETCH_ASSOC), 'arquivo');
        $aplicados = [];
        $grupos = ['migration' => App::root() . '/database/migrations'];
        if ($comSeedsProducao) {
            $grupos['seed'] = App::root() . '/database/seeds/producao';
        }
        foreach ($grupos as $tipo => $dir) {
            $arquivos = glob($dir . '/*.sql') ?: [];
            sort($arquivos, SORT_STRING);
            foreach ($arquivos as $arq) {
                $nome = $tipo . '/' . basename($arq);
                if (in_array($nome, $feitos, true)) {
                    continue;
                }
                $n = SqlScript::runFile($this->pdo, $arq);
                $st = $this->pdo->prepare('INSERT INTO migrations (arquivo, tipo) VALUES (?, ?)');
                $st->execute([$nome, $tipo]);
                $aplicados[] = $nome;
                if ($log) {
                    $log(sprintf('  ✓ %s (%d comandos)', $nome, $n));
                }
            }
        }
        return $aplicados;
    }

    /** Gera database/schema.sql (estrutura completa) a partir das migrations, para consulta e instalação manual. */
    public static function gerarSchema(): string
    {
        $arquivos = glob(App::root() . '/database/migrations/*.sql') ?: [];
        sort($arquivos, SORT_STRING);
        $sql = "-- =====================================================================\n"
            . "-- CRM Comercial · Grupo Mirante — ESTRUTURA COMPLETA do banco crm_grupo_mirante\n"
            . "-- Gerado a partir de database/migrations. Para instalar, prefira o instalador (instalar.php).\n"
            . "-- Uso manual: mysql -u USUARIO -p crm_grupo_mirante < database/schema.sql\n"
            . "-- =====================================================================\n\n";
        foreach ($arquivos as $arq) {
            $sql .= "-- >>> " . basename($arq) . "\n" . file_get_contents($arq) . "\n";
        }
        $destino = App::root() . '/database/schema.sql';
        file_put_contents($destino, $sql);
        return $destino;
    }
}
