<?php

declare(strict_types=1);

namespace App\Sync;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Descobre a chave primária de uma tabela e monta a consulta da linha a partir da chave
 * registrada no gatilho (valor simples, JSON para chave composta, "*" para tabela sem chave).
 */
final class TableKeys
{
    /**
     * @return list<string>
     */
    public static function primaryKey(string $tabela): array
    {
        foreach (Schema::getIndexes($tabela) as $indice) {
            if (($indice['primary'] ?? false) === true) {
                return array_values($indice['columns']);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $chavePrimaria
     */
    public static function query(string $tabela, array $chavePrimaria, string $chave): Builder
    {
        $consulta = DB::table($tabela);

        if ($chavePrimaria === []) {
            if ($chave !== '*') {
                throw new RuntimeException('A chave para uma tabela sem chave primária é inválida.');
            }

            if ($consulta->count() > 1) {
                throw new RuntimeException('A tabela sem chave primária contém mais de um registro; sincronização recusada.');
            }

            return $consulta;
        }

        if (count($chavePrimaria) === 1) {
            return $consulta->where($chavePrimaria[0], $chave);
        }

        $valores = json_decode($chave, true);
        $valores = is_array($valores) ? $valores : [];

        foreach ($chavePrimaria as $coluna) {
            $consulta->where($coluna, $valores[$coluna] ?? null);
        }

        return $consulta;
    }

    /**
     * Confere se os campos de chave presentes na linha correspondem à chave do evento.
     * Uma chave vazia só é aceita quando a tabela não tem chave primária.
     *
     * @param  list<string>  $chavePrimaria
     * @param  array<string, mixed>  $linha
     */
    public static function corresponde(array $chavePrimaria, string $chave, array $linha): bool
    {
        if ($chavePrimaria === []) {
            return $chave === '*';
        }

        if (count($chavePrimaria) === 1) {
            $valores = [$chavePrimaria[0] => $chave];
        } else {
            $valores = json_decode($chave, true);

            if (! is_array($valores)) {
                return false;
            }
        }

        foreach ($chavePrimaria as $coluna) {
            if (! array_key_exists($coluna, $valores)
                || ! array_key_exists($coluna, $linha)
                || (string) $valores[$coluna] !== (string) $linha[$coluna]) {
                return false;
            }
        }

        return true;
    }
}
