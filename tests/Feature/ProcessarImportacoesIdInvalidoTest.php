<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ImportacaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

final class ProcessarImportacoesIdInvalidoTest extends TestCase
{
    public function test_id_de_importacao_invalido_retorna_falha_sem_tentar_processar(): void
    {
        $this->mock(ImportacaoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProgresso')->never();
        });

        $exitCode = Artisan::call('checkplanilha:processar-importacoes', [
            'importacao' => 'abc',
        ]);
        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('ID da importação inválido', $output);
        self::assertStringNotContainsString('Processando importação #', $output);
    }

    public function test_id_zero_retorna_falha_sem_solicitar_entrada_interativa(): void
    {
        $this->mock(ImportacaoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buscarProgresso')->never();
        });

        $exitCode = Artisan::call('checkplanilha:processar-importacoes', [
            'importacao' => '0',
        ]);
        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('ID da importação inválido', $output);
        self::assertStringNotContainsString('ID da importação:', $output);
    }
}
