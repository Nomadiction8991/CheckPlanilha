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

        return is_scalar($value) ? (int) $value : $default;
    }

    public static function page(Request $request, string $key, int $perPage, int $default = 1): int
    {
        $perPage = max(1, $perPage);
        $page = max(1, self::integer($request, $key, $default));
        $maxPage = intdiv(PHP_INT_MAX, $perPage) + 1;

        return min($page, $maxPage);
    }

    public static function boolean(Request $request, string $key): bool
    {
        return is_scalar($request->query($key)) && $request->boolean($key);
    }
}
