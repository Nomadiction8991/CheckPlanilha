<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class QueryParameter
{
    public static function string(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function integer(Request $request, string $key, int $default = 0): int
    {
        $value = $request->query($key, $default);

        return is_scalar($value) ? (int) $value : $default;
    }

    public static function boolean(Request $request, string $key): bool
    {
        return is_scalar($request->query($key)) && $request->boolean($key);
    }
}
