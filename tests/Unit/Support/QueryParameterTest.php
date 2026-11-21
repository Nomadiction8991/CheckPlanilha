<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QueryParameter;
use Illuminate\Http\Request;
use Tests\TestCase;

final class QueryParameterTest extends TestCase
{
    public function testIntegerValueUsesDefaultForArrayValues(): void
    {
        self::assertSame(0, QueryParameter::integerValue([7]));
    }

    public function testIntegerValueAcceptsAnIntegerStringWithLeadingZeroes(): void
    {
        self::assertSame(7, QueryParameter::integerValue('0007'));
    }

    public function testIntegerValueUsesDefaultForPartiallyNumericStrings(): void
    {
        self::assertSame(0, QueryParameter::integerValue('7abc'));
        self::assertSame(0, QueryParameter::integerValue('7.5'));
        self::assertSame(0, QueryParameter::integerValue('1e2'));
    }

    public function testIntegerValueUsesDefaultForValuesThatAreNotIntegers(): void
    {
        self::assertSame(0, QueryParameter::integerValue(7.5));
        self::assertSame(0, QueryParameter::integerValue(true));
        self::assertSame(0, QueryParameter::integerValue('9223372036854775808'));
    }

    public function testPageRejectsPartiallyNumericValuesAndCapsLargeIntegers(): void
    {
        $invalidPage = Request::create('/listagem?pagina=7abc');
        $largePage = Request::create('/listagem?pagina=999999999999999999999999999999');

        self::assertSame(1, QueryParameter::page($invalidPage, 'pagina', 20));
        self::assertSame(intdiv(PHP_INT_MAX, 20) + 1, QueryParameter::page($largePage, 'pagina', 20));
    }
}
