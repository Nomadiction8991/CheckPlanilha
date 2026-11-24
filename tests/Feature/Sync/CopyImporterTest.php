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

    public function test_copia_com_versao_de_protocolo_desconhecida_nao_apaga_dados_existentes(): void
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
            json_encode(['tipo' => 'cabecalho', 'versao' => 999, 'cursor' => 0], JSON_THROW_ON_ERROR),
            json_encode(['tipo' => 'fim', 'linhas' => 0], JSON_THROW_ON_ERROR),
        ];

        try {
            app(CopyImporter::class)->importar($linhas);
            $this->fail('Uma cópia de versão não suportada precisava ser recusada.');
        } catch (RuntimeException $erro) {
            $this->assertStringContainsString('versão', mb_strtolower($erro->getMessage(), 'UTF-8'));
        }

        $this->assertSame(
            1,
            DB::table('comums')->where('codigo', 'C-LOCAL')->count(),
            'Uma cópia incompatível não pode apagar os dados deste computador.',
        );
    }

    public function test_copia_com_cursor_fracionario_nao_apaga_dados_existentes(): void
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
            json_encode(['tipo' => 'cabecalho', 'versao' => 1, 'cursor' => 1.5], JSON_THROW_ON_ERROR),
            json_encode(['tipo' => 'fim', 'linhas' => 0], JSON_THROW_ON_ERROR),
        ];

        try {
            app(CopyImporter::class)->importar($linhas);
            $this->fail('Um cursor fracionário precisava ser recusado.');
        } catch (RuntimeException $erro) {
            $this->assertStringContainsString('cabeçalho', mb_strtolower($erro->getMessage(), 'UTF-8'));
        }

        $this->assertSame(1, DB::table('comums')->where('codigo', 'C-LOCAL')->count());
    }

    public function test_copia_com_quantidade_final_fracionaria_nao_apaga_dados_existentes(): void
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
            json_encode(['tipo' => 'fim', 'linhas' => 0.5], JSON_THROW_ON_ERROR),
        ];

        try {
            app(CopyImporter::class)->importar($linhas);
            $this->fail('Uma quantidade final fracionária precisava ser recusada.');
        } catch (RuntimeException $erro) {
            $this->assertStringContainsString('quantidade', mb_strtolower($erro->getMessage(), 'UTF-8'));
        }

        $this->assertSame(1, DB::table('comums')->where('codigo', 'C-LOCAL')->count());
    }

    public function test_falha_ao_gravar_cursor_reverte_a_substituicao_da_copia(): void
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
        DB::table('sincronizacao_estado')->insert([
            'chave' => 'ultimo_recebido',
            'valor' => '4',
        ]);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER falhar_ao_atualizar_cursor
            BEFORE UPDATE ON sincronizacao_estado
            WHEN NEW.chave = 'ultimo_recebido'
            BEGIN
                SELECT RAISE(ABORT, 'falha simulada ao gravar cursor');
            END
            SQL);

        $linhas = [
            json_encode(['tipo' => 'cabecalho', 'versao' => 1, 'cursor' => 10], JSON_THROW_ON_ERROR),
            json_encode(['tipo' => 'linha', 'tabela' => 'comums', 'dados' => ['codigo' => 'C-SERVIDOR']], JSON_THROW_ON_ERROR),
            json_encode(['tipo' => 'fim', 'linhas' => 1], JSON_THROW_ON_ERROR),
        ];

        try {
            app(CopyImporter::class)->importar($linhas);
            $this->fail('A falha de gravação do cursor precisava interromper a cópia.');
        } catch (RuntimeException $erro) {
            $this->assertStringContainsString('cursor', mb_strtolower($erro->getMessage(), 'UTF-8'));
        }

        $this->assertSame(1, DB::table('comums')->where('codigo', 'C-LOCAL')->count());
        $this->assertSame(0, DB::table('comums')->where('codigo', 'C-SERVIDOR')->count());
        $this->assertSame('4', DB::table('sincronizacao_estado')->where('chave', 'ultimo_recebido')->value('valor'));
    }
}
