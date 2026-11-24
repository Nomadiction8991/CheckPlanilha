<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\ProcessingResult;
use PHPUnit\Framework\TestCase;

final class ProcessingResultTest extends TestCase
{
    public function test_merging_results_preserves_all_counters_and_errors(): void
    {
        $result = new ProcessingResult(2, 1, 3, 4, [
            ['linha' => 5, 'mensagem' => 'Erro inicial'],
        ]);

        $result->mesclar(new ProcessingResult(6, 7, 8, 9, [
            ['linha' => 10, 'mensagem' => 'Erro mesclado'],
        ]));

        self::assertSame([
            'sucesso' => 8,
            'erro' => 8,
            'pulados' => 11,
            'excluidos' => 13,
            'erros' => [
                ['linha' => 5, 'mensagem' => 'Erro inicial'],
                ['linha' => 10, 'mensagem' => 'Erro mesclado'],
            ],
        ], $result->toArray());
        self::assertSame(40, $result->total());
    }
}
