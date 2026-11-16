<?php

declare(strict_types=1);

namespace App\Support;

final class InternalRedirect
{
    public static function isSafePath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !in_array(substr($path, 0, 2), ['//', '/\\'], true);
    }
}
