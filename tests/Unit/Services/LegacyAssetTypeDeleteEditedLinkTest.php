<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Legacy\Produto;
use App\Models\Legacy\TipoBem;
use App\Services\LegacyAssetTypeManagementService;
use Illuminate\Database\Schema\Blueprint;
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
