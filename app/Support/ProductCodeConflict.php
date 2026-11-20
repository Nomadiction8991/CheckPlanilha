<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;

final class ProductCodeConflict
{
    public const MESSAGE = 'Este código já está em uso por outro bem.';

    /**
     * @template T
     *
     * @param  callable(): T  $operacao
     * @return T
     */
    public static function handle(callable $operacao): mixed
    {
        try {
            return $operacao();
        } catch (QueryException $erro) {
            self::rethrowIfDuplicate($erro);
        }
    }

    public static function isDuplicate(PDOException $erro): bool
    {
        $mensagem = mb_strtolower($erro->getMessage(), 'UTF-8');

        return str_contains($mensagem, 'uk_produto_codigo')
            || str_contains($mensagem, 'unique constraint failed: produtos.codigo');
    }

    /**
     * Converte somente violações da constraint global de código. Outras falhas do
     * banco continuam sendo propagadas para não esconder erros operacionais.
     */
    public static function rethrowIfDuplicate(QueryException $erro): never
    {
        if (! self::isDuplicate($erro)) {
            throw $erro;
        }

        throw new RuntimeException(self::MESSAGE, previous: $erro);
    }
}
