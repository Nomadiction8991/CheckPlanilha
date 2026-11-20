<?php

declare(strict_types=1);

namespace App\Sync;

/**
 * Comparação dos valores escalares que viajam nos eventos de sincronização.
 */
final class SyncValue
{
    public static function equals(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        if (is_bool($left)) {
            $left = $left ? '1' : '0';
        }

        if (is_bool($right)) {
            $right = $right ? '1' : '0';
        }

        return (string) $left === (string) $right;
    }
}
