<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheets;

use App\Services\ImportacaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ImportacaoAdministracaoComumScopeTest extends TestCase
{
    public function test_processamento_mantem_igrejas_com_mesmo_codigo_em_administracoes_diferentes(): void
    {
        Schema::create('administracoes', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('email');
        });
        Schema::create('comums', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('administracao_id')->nullable();
        });
        Schema::create('tipos_bens', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('descricao');
            $table->unsignedBigInteger('administracao_id')->nullable();
        });
        Schema::create('dependencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('comum_id');
            $table->string('descricao');
        });
        Schema::create('produtos', function (Blueprint $table): void {
            $table->id('id_produto');
            $table->unsignedBigInteger('comum_id');
            $table->string('codigo');
            $table->unsignedBigInteger('tipo_bem_id');
            $table->string('bem');
            $table->string('complemento')->default('');
            $table->unsignedBigInteger('dependencia_id');
            foreach (['novo', 'importado', 'checado', 'editado', 'imprimir_etiqueta', 'imprimir_14_1', 'ativo'] as $column) {
                $table->integer($column)->default(0);
            }
            $table->string('observacao')->default('');
        });
        Schema::create('import_erros', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('importacao_id');
            foreach (['linha_csv', 'codigo', 'localidade', 'codigo_comum', 'descricao_csv', 'bem', 'complemento', 'dependencia', 'mensagem_erro'] as $column) {
                $table->text($column)->nullable();
            }
        });
        Schema::create('importacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->unsignedBigInteger('administracao_id')->nullable();
            $table->string('arquivo_nome');
            $table->string('arquivo_caminho');
            $table->integer('total_linhas');
            $table->string('status');
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->integer('linhas_processadas')->default(0);
            $table->integer('linhas_sucesso')->default(0);
            $table->integer('linhas_erro')->default(0);
            $table->decimal('porcentagem', 5, 2)->default(0);
            $table->text('mensagem_erro')->nullable();
        });

        DB::table('administracoes')->insert([
            ['id' => 1, 'descricao' => 'Administração A'],
            ['id' => 2, 'descricao' => 'Administração B'],
        ]);
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Operador', 'email' => 'operador@example.com']);
        DB::table('comums')->insert([
            ['id' => 10, 'codigo' => '09-0001', 'administracao_id' => 1],
            ['id' => 20, 'codigo' => '09-0001', 'administracao_id' => 2],
        ]);
        DB::table('tipos_bens')->insert(['id' => 1, 'codigo' => '4', 'descricao' => 'CADEIRA', 'administracao_id' => 1]);
        DB::table('importacoes')->insert([
            'id' => 1,
            'usuario_id' => 1,
            'administracao_id' => 1,
            'arquivo_nome' => 'teste.csv',
            'arquivo_caminho' => 'teste.csv',
            'total_linhas' => 1,
            'status' => 'aguardando',
        ]);

        (new ImportacaoService(DB::connection()->getPdo()))->processarComAcoes(1, ['30' => 'importar'], [
            'registros' => [[
                'linha_csv' => '30',
                'status' => 'novo',
                'dados_csv' => [
                    'codigo' => '09-0001 / 0001',
                    'codigo_comum' => '09-0001',
                    'tipo_bem_codigo' => '4',
                    'bem' => 'CADEIRA',
                    'complemento' => 'MADEIRA',
                    'dependencia_descricao' => 'SALÃO',
                ],
            ]],
        ]);

        self::assertSame(1, (int) DB::table('comums')->where('id', 10)->value('administracao_id'));
        self::assertSame(2, (int) DB::table('comums')->where('id', 20)->value('administracao_id'));
        self::assertSame(10, (int) DB::table('produtos')->value('comum_id'));
    }
}
