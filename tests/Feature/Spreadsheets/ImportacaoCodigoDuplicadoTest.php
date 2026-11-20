<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheets;

use App\Services\CsvParserService;
use App\Services\ImportacaoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ImportacaoCodigoDuplicadoTest extends TestCase
{
    public function test_importacao_informa_codigo_global_ja_usado_em_outra_igreja(): void
    {
        $this->criarTabelas();
        $this->prepararDados();

        $caminho = storage_path('importacao/codigo-duplicado-'.uniqid('', true).'.csv');
        if (! is_dir(dirname($caminho))) {
            mkdir(dirname($caminho), 0775, true);
        }
        self::assertTrue(copy(base_path('tests/Fixtures/importacao/csv-codigo-existente-outra-igreja.csv'), $caminho));

        try {
            $pdo = DB::connection()->getPdo();
            $analise = (new CsvParserService($pdo, 1))->analisar($caminho);

            self::assertSame('novo', $analise['registros'][0]['status']);

            $resultado = (new ImportacaoService($pdo))->processarComAcoes(
                1,
                [(string) $analise['registros'][0]['linha_csv'] => 'importar'],
                $analise,
            );

            self::assertSame(1, $resultado['erro']);
            self::assertSame('Este código já está em uso por outro bem.', $resultado['erros'][0]['mensagem']);
            self::assertSame('Este código já está em uso por outro bem.', DB::table('import_erros')->value('mensagem_erro'));
            self::assertFalse(DB::table('produtos')->where('comum_id', 10)->exists());
        } finally {
            if (is_file($caminho)) {
                unlink($caminho);
            }
        }
    }

    private function criarTabelas(): void
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
        Schema::create('configuracoes', function (Blueprint $table): void {
            $table->integer('pulo_linhas')->nullable();
            $table->text('mapeamento_colunas')->nullable();
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
            $table->string('codigo')->nullable()->unique();
            $table->unsignedBigInteger('tipo_bem_id')->nullable();
            $table->text('bem');
            $table->text('complemento')->nullable();
            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->integer('ativo')->default(1);
            $table->integer('novo')->default(0);
            $table->integer('importado')->default(0);
            $table->integer('checado')->default(0);
            $table->integer('editado')->default(0);
            $table->integer('imprimir_etiqueta')->default(0);
            $table->integer('imprimir_14_1')->default(0);
            $table->text('observacao')->nullable();
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
            $table->integer('linhas_processadas')->default(0);
            $table->integer('linhas_sucesso')->default(0);
            $table->integer('linhas_erro')->default(0);
            $table->decimal('porcentagem', 5, 2)->default(0);
            $table->text('mensagem_erro')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
        });
        Schema::create('import_erros', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('importacao_id');
            $table->text('linha_csv')->nullable();
            $table->text('codigo')->nullable();
            $table->text('localidade')->nullable();
            $table->text('codigo_comum')->nullable();
            $table->text('descricao_csv')->nullable();
            $table->text('bem')->nullable();
            $table->text('complemento')->nullable();
            $table->text('dependencia')->nullable();
            $table->text('mensagem_erro');
        });
    }

    private function prepararDados(): void
    {
        DB::table('administracoes')->insert([
            ['id' => 1, 'descricao' => 'Administração A'],
            ['id' => 2, 'descricao' => 'Administração B'],
        ]);
        DB::table('usuarios')->insert(['id' => 1, 'nome' => 'Operador', 'email' => 'operador@example.test']);
        DB::table('comums')->insert([
            ['id' => 10, 'codigo' => '09-0002', 'administracao_id' => 1],
            ['id' => 20, 'codigo' => '09-0003', 'administracao_id' => 2],
        ]);
        DB::table('configuracoes')->insert([
            'pulo_linhas' => 1,
            'mapeamento_colunas' => 'codigo=A;complemento=B;dependencia=C;localidade=D',
        ]);
        DB::table('tipos_bens')->insert(['id' => 4, 'codigo' => '4', 'descricao' => 'CADEIRA', 'administracao_id' => 1]);
        DB::table('produtos')->insert([
            'id_produto' => 1,
            'comum_id' => 20,
            'codigo' => '09-0002 / 0001',
            'tipo_bem_id' => 4,
            'bem' => 'CADEIRA',
            'dependencia_id' => 1,
        ]);
        DB::table('importacoes')->insert([
            'id' => 1,
            'usuario_id' => 1,
            'administracao_id' => 1,
            'arquivo_nome' => 'csv-codigo-existente-outra-igreja.csv',
            'arquivo_caminho' => 'fixture',
            'total_linhas' => 1,
            'status' => 'aguardando',
        ]);
    }
}
