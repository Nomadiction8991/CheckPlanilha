<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QueryParameter;
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
}
