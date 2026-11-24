<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\AssetTypeMutationData;
use App\Models\Legacy\Produto;
use App\Models\Legacy\TipoBem;
use App\Services\LegacyAssetTypeManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

final class LegacyAssetTypeDeleteEditedLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tipos_bens', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
            $table->unsignedBigInteger('administracao_id')->nullable();
        });

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
            $table->string('tipo')->nullable();
            $table->string('email')->nullable();
        });

        Schema::create('administracoes', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao')->nullable();
        });

        DB::table('administracoes')->insert([
            ['id' => 10, 'descricao' => 'LOCAL'],
            ['id' => 20, 'descricao' => 'OUTRA'],
        ]);

        Schema::create('produtos', function (Blueprint $table): void {
            $table->id('id_produto');
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->unsignedBigInteger('tipo_bem_id')->nullable();
            $table->unsignedBigInteger('editado_tipo_bem_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('bem')->nullable();
            $table->integer('ativo')->default(1);
        });

        Session::put(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('tipos_bens');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('administracoes');
        parent::tearDown();
    }

    public function testAssetTypeUsedOnlyAsEditedTypeCannotBeDeleted(): void
    {
        $this->seedAssetTypeAsEditedType(productId: 500, originalTypeId: 1, editedTypeId: 2);

        $service = new LegacyAssetTypeManagementService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('vinculado a produtos');

        $service->delete(TipoBem::query()->find(2));
    }

    public function testAssetTypeUsedAsEditedTypeIsKeptWhenDeletionIsBlocked(): void
    {
        $this->seedAssetTypeAsEditedType(productId: 500, originalTypeId: 1, editedTypeId: 2);

        $service = new LegacyAssetTypeManagementService();

        try {
            $service->delete(TipoBem::query()->find(2));
        } catch (RuntimeException) {
            // Bloqueio esperado; o estado persistido é verificado abaixo.
        }

        self::assertNotNull(TipoBem::query()->find(2));
        self::assertSame(2, (int) Produto::query()->find(500)->editado_tipo_bem_id);
    }

    public function testUnusedAssetTypeCanStillBeDeleted(): void
    {
        $this->seedAssetTypeAsEditedType(productId: 500, originalTypeId: 1, editedTypeId: 2);
        TipoBem::query()->create(['id' => 3, 'codigo' => '999', 'descricao' => 'SEM USO']);

        $service = new LegacyAssetTypeManagementService();
        $service->delete(TipoBem::query()->find(3));

        self::assertNull(TipoBem::query()->find(3));
    }

    public function testDemotedAdministratorCannotDeleteTypeFromAnotherAdministration(): void
    {
        $this->createAssetType(4, 20);
        $this->startDemotedAdministratorSession();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Você só pode gerenciar tipos de bem da sua própria administração.');

        (new LegacyAssetTypeManagementService())->delete(TipoBem::query()->findOrFail(4));
    }

    public function testDemotedAdministratorCannotDeleteSharedType(): void
    {
        $this->createAssetType(5, null);
        $this->startDemotedAdministratorSession();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Você só pode gerenciar tipos de bem da sua própria administração.');

        (new LegacyAssetTypeManagementService())->delete(TipoBem::query()->findOrFail(5));
    }

    public function testDemotedAdministratorCannotUpdateSharedType(): void
    {
        $this->createAssetType(6, null);
        $this->startDemotedAdministratorSession();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Você só pode gerenciar tipos de bem da sua própria administração.');

        (new LegacyAssetTypeManagementService())->update(
            TipoBem::query()->findOrFail(6),
            new AssetTypeMutationData(10, 'TIPO ALTERADO'),
        );
    }

    public function testDemotedAdministratorCannotCreateTypeInAnotherAdministration(): void
    {
        $this->startDemotedAdministratorSession();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Você só pode cadastrar ou editar tipos de bem da sua própria administração.');

        (new LegacyAssetTypeManagementService())->create(new AssetTypeMutationData(20, 'OUTRO TIPO'));
    }

    public function testDemotedAdministratorCannotMoveTypeToAnotherAdministration(): void
    {
        $this->createAssetType(4, 10);
        $this->startDemotedAdministratorSession();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Você só pode cadastrar ou editar tipos de bem da sua própria administração.');

        (new LegacyAssetTypeManagementService())->update(
            TipoBem::query()->findOrFail(4),
            new AssetTypeMutationData(20, 'TIPO MOVIDO'),
        );
    }

    private function startDemotedAdministratorSession(): void
    {
        DB::table('usuarios')->insert([
            'id' => 9,
            'ativo' => 1,
            'tipo' => 'comum',
            'email' => 'usuario@example.test',
        ]);
        Session::put([
            'usuario_id' => 9,
            'is_admin' => true,
            'administracao_id' => 10,
        ]);
    }

    private function createAssetType(int $id, ?int $administrationId): void
    {
        $type = new TipoBem();
        $type->forceFill([
            'id' => $id,
            'codigo' => str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'descricao' => 'TIPO ' . $id,
            'administracao_id' => $administrationId,
        ])->save();
    }

    private function seedAssetTypeAsEditedType(int $productId, int $originalTypeId, int $editedTypeId): void
    {
        TipoBem::query()->create(['id' => $originalTypeId, 'codigo' => '001', 'descricao' => 'CADEIRA']);
        TipoBem::query()->create(['id' => $editedTypeId, 'codigo' => '002', 'descricao' => 'CADEIRA PLASTICA']);

        $produto = new Produto();
        $produto->forceFill([
            'id_produto' => $productId,
            'comum_id' => 100,
            'tipo_bem_id' => $originalTypeId,
            'editado_tipo_bem_id' => $editedTypeId,
            'bem' => 'CADEIRA',
            'ativo' => 1,
        ]);
        $produto->save();
    }
}
