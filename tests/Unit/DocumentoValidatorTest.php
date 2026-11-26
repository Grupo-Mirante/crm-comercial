<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\DocumentoValidator;
use PHPUnit\Framework\TestCase;

/** RN-61 / RN-62 — exemplos do documento e casos de borda. */
final class DocumentoValidatorTest extends TestCase
{
    public function testExemplosDoDocumento(): void
    {
        self::assertTrue(DocumentoValidator::valido('11.222.333/0001-81'));
        self::assertFalse(DocumentoValidator::valido('11.222.333/0001-80'));
        self::assertTrue(DocumentoValidator::valido('12.ABC.345/01DE-35'), 'CNPJ alfanumérico da Receita');
    }

    public function testFormataAutomaticamente(): void
    {
        $r = DocumentoValidator::verificar('11222333000181');
        self::assertSame('11.222.333/0001-81', $r['formatado']);
        self::assertSame('CNPJ', $r['tipo']);
        self::assertSame('11222333000181', $r['chave']);
        $a = DocumentoValidator::verificar('12abc34501de35');
        self::assertTrue($a['ok']);
        self::assertSame('12.ABC.345/01DE-35', $a['formatado']);
    }

    public function testCpf(): void
    {
        $r = DocumentoValidator::verificar('529.982.247-25');
        self::assertTrue($r['ok']);
        self::assertSame('CPF', $r['tipo']);
        self::assertSame('529.982.247-25', DocumentoValidator::verificar('52998224725')['formatado']);
        self::assertFalse(DocumentoValidator::valido('529.982.247-24'));
        self::assertFalse(DocumentoValidator::valido('111.111.111-11'), 'repetidos são inválidos');
    }

    public function testInvalidosEVazio(): void
    {
        self::assertTrue(DocumentoValidator::verificar('')['vazio']);
        self::assertFalse(DocumentoValidator::valido('00.000.000/0000-00'));
        self::assertFalse(DocumentoValidator::valido('123'));
        self::assertFalse(DocumentoValidator::valido('12.ABC.345/01DE-3X'), 'dígitos verificadores precisam ser números');
        self::assertFalse(DocumentoValidator::cnpjValido('529.982.247-25'), 'CPF não serve como CNPJ de agência');
    }
}
