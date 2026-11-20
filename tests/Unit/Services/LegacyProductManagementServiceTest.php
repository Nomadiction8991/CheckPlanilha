<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\CreateLegacyProductData;
use App\DTO\UpdateLegacyProductData;
use App\Models\Legacy\Produto;
use App\Services\LegacyProductManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class LegacyProductManagementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('comums', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('administracao_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
        });

        Schema::create('dependencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->string('descricao')->nullable();
        });

        Schema::create('tipos_bens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('administracao_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
        });

        // Espelha a constraint uk_produto_codigo (UNIQUE em codigo) do MySQL de produção.
        Schema::create('produtos', function (Blueprint $table): void {
            $table->id('id_produto');
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->string('codigo', 50)->nullable()->unique();
            $table->unsignedBigInteger('tipo_bem_id')->nullable();
            $table->string('bem')->nullable();
            $table->string('complemento')->nullable();
            $table->string('editado_marca')->nullable();
            $table->string('altura_m')->nullable();
            $table->string('largura_m')->nullable();
            $table->string('comprimento_m')->nullable();
            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->unsignedBigInteger('editado_tipo_bem_id')->nullable();
            $table->string('editado_bem')->nullable();
            $table->string('editado_complemento')->nullable();
            $table->string('editado_altura_m')->nullable();
            $table->string('editado_largura_m')->nullable();
            $table->string('editado_comprimento_m')->nullable();
            $table->unsignedBigInteger('editado_dependencia_id')->nullable();
            $table->integer('novo')->nullable();
            $table->integer('importado')->nullable();
            $table->integer('checado')->nullable();
            $table->integer('editado')->nullable();
            $table->integer('imprimir_etiqueta')->nullable();
            $table->integer('imprimir_14_1')->nullable();
            $table->string('condicao_14_1')->nullable();
            $table->string('observacao')->nullable();
            $table->string('nota_numero')->nullable();
            $table->string('nota_data')->nullable();
            $table->string('nota_valor')->nullable();
            $table->string('nota_fornecedor')->nullable();
            $table->unsignedBigInteger('administrador_acessor_id')->nullable();
            $table->integer('ativo')->default(1);
        });

        DB::table('comums')->insert(['id' => 7, 'administracao_id' => 1, 'codigo' => '001', 'descricao' => 'Central']);
        DB::table('dependencias')->insert(['id' => 2, 'comum_id' => 7, 'descricao' => 'Salão']);
        DB::table('tipos_bens')->insert(['id' => 4, 'administracao_id' => 1, 'codigo' => '1', 'descricao' => 'CADEIRA']);

        session(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('tipos_bens');
        Schema::dropIfExists('dependencias');
        Schema::dropIfExists('comums');

        parent::tearDown();
    }

    public function testRejeitaCodigoJaUsadoPorOutroBemComMensagemAcionavel(): void
    {
        $this->insertProduto('ABC-1', ativo: 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Este código já está em uso por outro bem.');

        try {
            $this->service()->createMany($this->dados(code: 'ABC-1', multiplier: 1));
        } finally {
            $this->assertSame(1, DB::table('produtos')->count(), 'Nenhum bem novo deve ser gravado.');
        }
    }

    public function testRejeitaCodigoJaUsadoPorBemInativo(): void
    {
        // A constraint do banco vale também para bens inativos, então o código continua ocupado.
        $this->insertProduto('ABC-2', ativo: 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Este código já está em uso por outro bem.');

        $this->service()->createMany($this->dados(code: 'ABC-2', multiplier: 1));
    }

    public function testRejeitaCodigoComMultiplicadorMaiorQueUmSemGravarNada(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Para cadastrar mais de um bem, deixe o código em branco.');

        try {
            $this->service()->createMany($this->dados(code: 'ABC-3', multiplier: 2));
        } finally {
            $this->assertSame(0, DB::table('produtos')->count(), 'Nenhum bem deve ser gravado pela metade.');
        }
    }

    public function testCadastraVariosBensSemCodigo(): void
    {
        $quantidade = $this->service()->createMany($this->dados(code: null, multiplier: 3));

        $this->assertSame(3, $quantidade);
        $this->assertSame(3, DB::table('produtos')->count());
        $this->assertSame(
            0,
            DB::table('produtos')->whereNotNull('codigo')->count(),
            'Bens cadastrados sem código não devem receber código.',
        );
    }

    public function testCadastraBemUnicoComCodigoNovo(): void
    {
        $quantidade = $this->service()->createMany($this->dados(code: 'ABC-4', multiplier: 1));

        $this->assertSame(1, $quantidade);
        $this->assertSame('ABC-4', DB::table('produtos')->value('codigo'));
    }

    public function testConverteColisaoConcorrenteDeCodigoEmMensagemAcionavel(): void
    {
        Produto::creating(static function (Produto $produto): void {
            if ($produto->codigo === 'ABC-RACE') {
                DB::table('produtos')->insert([
                    'comum_id' => 7,
                    'codigo' => 'ABC-RACE',
                    'tipo_bem_id' => 4,
                    'bem' => 'CADEIRA',
                    'dependencia_id' => 2,
                    'ativo' => 1,
                ]);
            }
        });

        try {
            $this->service()->createMany($this->dados(code: 'ABC-RACE', multiplier: 1));
            $this->fail('A colisão simulada deveria ser recusada.');
        } catch (RuntimeException $erro) {
            $this->assertSame('Este código já está em uso por outro bem.', $erro->getMessage());
        }

        $this->assertSame(0, DB::table('produtos')->count(), 'A transação da operação deve ser revertida.');
    }

    public function testRejeitaTipoDeBemDeOutraAdministracaoParaUsuarioSemAcessoGlobal(): void
    {
        DB::table('tipos_bens')->insert(['id' => 5, 'administracao_id' => 2, 'codigo' => '2', 'descricao' => 'CADEIRA']);
        $this->escopoDaAdministracao1();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('O tipo de bem selecionado está fora do seu escopo permitido.');

        try {
            $this->service()->createMany($this->dados(code: 'ABC-5', multiplier: 1, assetTypeId: 5));
        } finally {
            $this->assertSame(0, DB::table('produtos')->count(), 'Nenhum bem deve ser gravado com tipo de outra administração.');
        }
    }

    public function testAceitaTipoDeBemDaPropriaAdministracaoParaUsuarioSemAcessoGlobal(): void
    {
        $this->escopoDaAdministracao1();

        $quantidade = $this->service()->createMany($this->dados(code: 'ABC-6', multiplier: 1, assetTypeId: 4));

        $this->assertSame(1, $quantidade);
    }

    public function testEdicaoNaoTrocaParaTipoDeBemDeOutraAdministracao(): void
    {
        DB::table('tipos_bens')->insert(['id' => 5, 'administracao_id' => 2, 'codigo' => '2', 'descricao' => 'CADEIRA']);
        DB::table('produtos')->insert([
            'id_produto' => 30,
            'comum_id' => 7,
            'codigo' => 'ABC-8',
            'tipo_bem_id' => 4,
            'bem' => 'CADEIRA',
            'dependencia_id' => 2,
            'ativo' => 1,
        ]);
        $this->escopoDaAdministracao1();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('O tipo de bem selecionado está fora do seu escopo permitido.');

        try {
            $this->service()->update(
                Produto::query()->findOrFail(30),
                new UpdateLegacyProductData(
                    editedAssetTypeId: 5,
                    editedItemName: 'CADEIRA',
                    editedComplement: 'Metalica',
                    editedBrand: null,
                    editedHeightMeters: null,
                    editedWidthMeters: null,
                    editedLengthMeters: null,
                    editedDependencyId: 2,
                    verified: true,
                    printLabel: true,
                    observation: '',
                    printReport141: false,
                    condition141: '',
                    invoiceNumber: null,
                    invoiceDate: null,
                    invoiceValue: null,
                    invoiceSupplier: null,
                ),
            );
        } finally {
            $this->assertNull(
                DB::table('produtos')->where('id_produto', 30)->value('editado_tipo_bem_id'),
                'A edição não deve gravar tipo de outra administração.',
            );
        }
    }

    public function testAceitaTipoDeBemCompartilhadoSemAdministracaoParaUsuarioSemAcessoGlobal(): void
    {
        DB::table('tipos_bens')->insert(['id' => 6, 'administracao_id' => null, 'codigo' => '3', 'descricao' => 'CADEIRA']);
        $this->escopoDaAdministracao1();

        $quantidade = $this->service()->createMany($this->dados(code: 'ABC-7', multiplier: 1, assetTypeId: 6));

        $this->assertSame(1, $quantidade);
    }

    private function escopoDaAdministracao1(): void
    {
        session([
            'is_admin' => false,
            'administracao_id' => 1,
            'administracoes_permitidas' => [1],
        ]);
    }

    private function service(): LegacyProductManagementService
    {
        return new LegacyProductManagementService();
    }

    private function insertProduto(string $codigo, int $ativo): void
    {
        DB::table('produtos')->insert([
            'comum_id' => 7,
            'codigo' => $codigo,
            'tipo_bem_id' => 4,
            'bem' => 'CADEIRA',
            'dependencia_id' => 2,
            'ativo' => $ativo,
        ]);
    }

    private function dados(?string $code, int $multiplier, int $assetTypeId = 4): CreateLegacyProductData
    {
        return new CreateLegacyProductData(
            churchId: 7,
            code: $code,
            assetTypeId: $assetTypeId,
            itemName: 'CADEIRA',
            complement: 'Metalica',
            brand: null,
            heightMeters: null,
            widthMeters: null,
            lengthMeters: null,
            dependencyId: 2,
            multiplier: $multiplier,
            printReport141: false,
            condition141: '',
            invoiceNumber: null,
            invoiceDate: null,
            invoiceValue: null,
            invoiceSupplier: null,
        );
    }
}
