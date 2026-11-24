<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyCnpjValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LegacyCnpjValidatorTest extends TestCase
{
    public function testAcceptsOfficialAlphanumericCnpjExample(): void
    {
        self::assertSame('12ABC34501DE35', LegacyCnpjValidator::validate('12ABC34501DE35'));
    }

    public function testRejectsAlphanumericCnpjWithInvalidCheckDigits(): void
    {
        foreach (['12ABC34501DE36', '12ABC34501DF35'] as $cnpj) {
            try {
                LegacyCnpjValidator::validate($cnpj);
                self::fail('CNPJ alfanumérico com dígitos verificadores incorretos deveria ser rejeitado.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
