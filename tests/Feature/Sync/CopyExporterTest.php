<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Sync\CopyExporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CopyExporterTest extends SincronizacaoTestCase
{
    public function test_snapshot_e_completado_antes_de_entregar_o_cursor(): void
    {
        config([
            'sincronizacao.ordem_da_copia' => ['produtos'],
            'sincronizacao.tabelas' => ['produtos'],
        ]);
        Schema::create('produtos', static function (Blueprint $table): void {
            $table->increments('id_produto');
            $table->string('descricao');
        });

        $linhas = app(CopyExporter::class)->linhas();
        $linhas->rewind();
        $cabecalho = json_decode((string) $linhas->current(), true, 512, JSON_THROW_ON_ERROR);

        DB::table('produtos')->insert(['descricao' => 'Gravado depois do cursor']);
        $linhasDaCopia = iterator_to_array($linhas);
        $registros = array_values(array_filter(array_map(
            static fn (string $linha): array => json_decode($linha, true, 512, JSON_THROW_ON_ERROR),
            $linhasDaCopia,
        ), static fn (array $linha): bool => ($linha['tipo'] ?? null) === 'linha'));

        $this->assertSame(0, $cabecalho['cursor']);
        $this->assertSame([], $registros);
    }
}
