<?php

declare(strict_types=1);

namespace App\Support;

final class BrazilStateCode
{
    public static function normalizePrefix(string $value): ?string
    {
        $prefix = mb_strtoupper(mb_substr(trim($value), 0, 2, 'UTF-8'), 'UTF-8');

        return array_key_exists($prefix, (array) config('brazil.states', [])) ? $prefix : null;
    }
}
