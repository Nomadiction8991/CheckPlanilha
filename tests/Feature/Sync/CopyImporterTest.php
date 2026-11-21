<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Sync\CopyImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class CopyImporterTest extends TestCase
{
    public function test_copia_com_linha_ndjson_invalida_nao_apaga_dados_existentes(): void
    {
        config([
            'sincronizacao.ordem_da_copia' => ['comums'],
            'sincronizacao.tabelas' => ['comums'],
        ]);
        Schema::create('comums', static function ($table): void {
            $table->increments('id');
            $table->string('codigo')->nullable();
        });
        Schema::create('sincronizacao_estado', static function ($table): void {
            $table->string('chave')->primary();
            $table->text('valor')->nullable();
        });
        DB::table('comums')->insert(['codigo' => 'C-LOCAL']);
        $linhas = [
            json_encode(['tipo' => 'cabecalho', 'versao' => 1, 'cursor' => 0], JSON_THROW_ON_ERROR),
            '{"tipo":"linha","tabela":',
            json_encode(['tipo' => 'fim', 'linhas' => 0], JSON_THROW_ON_ERROR),
        ];

        try {
            app(CopyImporter::class)->importar($linhas);
            $this->fail('A cópia com uma linha inválida precisava ser recusada.');
        } catch (RuntimeException $erro) {
            $this->assertStringContainsString('inválida', $erro->getMessage());
        }

        $this->assertSame(
            1,
            DB::table('comums')->where('codigo', 'C-LOCAL')->count(),
            'Uma cópia inválida não pode apagar os dados deste computador.',
        );
    }
}
