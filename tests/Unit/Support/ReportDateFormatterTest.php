<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ReportDateFormatter;
use PHPUnit\Framework\TestCase;

final class ReportDateFormatterTest extends TestCase
{
    public function test_formats_a_parseable_date_whose_timestamp_is_zero(): void
    {
        self::assertSame('31/12/1969', ReportDateFormatter::format('Jan 1, 1970 00:00 UTC'));
    }

    public function test_preserves_an_unparseable_date(): void
    {
        self::assertSame('data inválida', ReportDateFormatter::format('data inválida'));
    }
}
