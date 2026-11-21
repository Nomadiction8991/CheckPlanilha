<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class QueryParameter
{
    public static function string(Request $request, string $key, string $default = ''): string
    {
        return self::scalarString($request->query($key, $default), $default);
    }

    public static function inputString(Request $request, string $key, string $default = ''): string
    {
        return self::scalarString($request->input($key, $default), $default);
    }

    private static function scalarString(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function integer(Request $request, string $key, int $default = 0): int
    {
        $value = $request->query($key, $default);

        return self::integerValue($value, $default);
    }

    public static function inputInteger(Request $request, string $key, int $default = 0): int
    {
        return self::integerValue($request->input($key, $default), $default);
    }

    public static function integerValue(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^[+-]?\d+$/D', $value) !== 1) {
            return $default;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim(ltrim($value, '+-'), '0') ?: '0';

        if ($digits === '0') {
            return 0;
        }

        $normalized = ($negative ? '-' : '') . $digits;
        $integer = filter_var($normalized, FILTER_VALIDATE_INT);

        return is_int($integer) ? $integer : $default;
    }

    public static function page(Request $request, string $key, int $perPage, int $default = 1): int
    {
        $perPage = max(1, $perPage);
        $maxPage = intdiv(PHP_INT_MAX, $perPage) + 1;
        $value = $request->query($key, $default);

        if (is_string($value) && preg_match('/^\+?\d+$/D', $value) === 1) {
            $digits = ltrim(ltrim($value, '+'), '0') ?: '0';
            $maximum = (string) $maxPage;

            if (strlen($digits) > strlen($maximum)
                || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
                return $maxPage;
            }
        }

        $page = max(1, self::integerValue($value, $default));

        return min($page, $maxPage);
    }

    public static function boolean(Request $request, string $key): bool
    {
        return is_scalar($request->query($key)) && $request->boolean($key);
    }
}
