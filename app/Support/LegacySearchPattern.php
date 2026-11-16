<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Busca textual literal: o termo digitado pelo usuário nunca é interpretado como
 * curinga de LIKE (% e _). O caractere de escape é '!', válido em MySQL, SQLite e PostgreSQL.
 */
final class LegacySearchPattern
{
    private const ESCAPE_CHAR = '!';

    public static function contains(string $term): string
    {
        $escaped = str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR . self::ESCAPE_CHAR, self::ESCAPE_CHAR . '%', self::ESCAPE_CHAR . '_'],
            $term,
        );

        return '%' . $escaped . '%';
    }

    public static function whereContains(Builder $query, string $column, string $term, bool $or = false): Builder
    {
        $sql = $column . " LIKE ? ESCAPE '" . self::ESCAPE_CHAR . "'";
        $bindings = [self::contains($term)];

        return $or
            ? $query->orWhereRaw($sql, $bindings)
            : $query->whereRaw($sql, $bindings);
    }
}
