<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\SqlScript;
use PHPUnit\Framework\TestCase;

/** O instalador executa os scripts comando a comando; ";" dentro de textos não pode quebrar o comando. */
final class SqlScriptTest extends TestCase
{
    public function testSeparaRespeitandoAspasEComentarios(): void
    {
        $sql = "-- comentário; com ponto e vírgula\nINSERT INTO t VALUES ('a; b', 'c''d');\n/* bloco; */ SELECT 1;\nSELECT \"x;y\" # fim; \n;";
        $cmds = SqlScript::split($sql);
        self::assertCount(3, $cmds);
        self::assertSame("INSERT INTO t VALUES ('a; b', 'c''d')", $cmds[0]);
        self::assertSame('SELECT 1', $cmds[1]);
        self::assertSame('SELECT "x;y"', $cmds[2]);
    }
}
