<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LegacyProductManagementServiceInterface;
use App\DTO\CreateLegacyProductData;
use App\DTO\UpdateLegacyProductData;
use App\Models\Legacy\Comum;
use App\Models\Legacy\Dependencia;
use App\Models\Legacy\Produto;
use App\Models\Legacy\TipoBem;
use App\Support\LegacyProductTypeOptionSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyProductManagementService implements LegacyProductManagementServiceInterface
{
    use ResolvesLegacyProductScope;

    public function createMany(CreateLegacyProductData $data): int
    {
        $church = $this->assertChurchWithinProductScope($data->churchId);
        $assetType = $this->resolveAssetType($data->assetTypeId);
        $dependency = $this->resolveDependency($data->dependencyId, $church->id);
        $itemName = $this->normalizeItemName($assetType, $data->itemName);
        $code = $data->code !== null && trim($data->code) !== '' ? trim($data->code) : null;
        $this->assertCodeCanBeUsed($code, $data->multiplier);
        $complement = mb_strtoupper(trim($data->complement), 'UTF-8');
        $brand = $data->brand !== null
            ? mb_strtoupper(trim($data->brand), 'UTF-8')
            : null;
        $invoiceSupplier = $data->invoiceSupplier !== null
            ? mb_strtoupper(trim($data->invoiceSupplier), 'UTF-8')
            : null;

        DB::transaction(function () use ($data, $church, $assetType, $dependency, $itemName, $code, $complement, $brand, $invoiceSupplier): void {
            for ($index = 0; $index < $data->multiplier; $index++) {
                Produto::query()->create([
                    'comum_id' => (int) $church->id,
                    'codigo' => $code,
                    'tipo_bem_id' => (int) $assetType->id,
                    'bem' => $itemName,
                    'complemento' => $complement,
                    'editado_marca' => $brand,
                    'altura_m' => $data->heightMeters,
                    'largura_m' => $data->widthMeters,
                    'comprimento_m' => $data->lengthMeters,
                    'dependencia_id' => (int) $dependency->id,
                    'novo' => 1,
                    'importado' => 0,
                    'checado' => 0,
                    'editado' => 0,
                    'imprimir_etiqueta' => 0,
                    'imprimir_14_1' => $data->printReport141 ? 1 : 0,
                    'condicao_14_1' => $data->condition141,
                    'observacao' => '',
                    'nota_numero' => $data->invoiceNumber,
                    'nota_data' => $data->invoiceDate,
                    'nota_valor' => $data->invoiceValue,
                    'nota_fornecedor' => $invoiceSupplier,
                    'ativo' => 1,
                ]);
            }
        });

        return $data->multiplier;
    }

    public function update(Produto $product, UpdateLegacyProductData $data): Produto
    {
        $this->assertProductWithinProductScope($product);
        $assetType = $this->resolveAssetType($data->editedAssetTypeId);
        $dependency = $this->resolveDependency($data->editedDependencyId, (int) $product->comum_id);
        $itemName = $this->normalizeItemName($assetType, $data->editedItemName);
        $invoiceSupplier = $data->invoiceSupplier !== null
            ? mb_strtoupper(trim($data->invoiceSupplier), 'UTF-8')
            : null;

        $product->fill([
            'editado_tipo_bem_id' => (int) $assetType->id,
            'editado_bem' => $itemName,
            'editado_complemento' => mb_strtoupper(trim($data->editedComplement), 'UTF-8'),
            'editado_marca' => $data->editedBrand !== null
                ? mb_strtoupper(trim($data->editedBrand), 'UTF-8')
                : null,
            'editado_altura_m' => $data->editedHeightMeters,
            'editado_largura_m' => $data->editedWidthMeters,
            'editado_comprimento_m' => $data->editedLengthMeters,
            'editado_dependencia_id' => (int) $dependency->id,
            'checado' => 1,
            'imprimir_etiqueta' => 1,
            'observacao' => mb_strtoupper(trim($data->observation), 'UTF-8'),
            'editado' => 1,
            'imprimir_14_1' => $data->printReport141 ? 1 : 0,
            'condicao_14_1' => $data->condition141,
            'nota_numero' => $data->invoiceNumber,
            'nota_data' => $data->invoiceDate,
            'nota_valor' => $data->invoiceValue,
            'nota_fornecedor' => $invoiceSupplier,
        ]);
        $product->save();

        return $product->refresh();
    }

    private function resolveChurch(int $churchId): Comum
    {
        return $this->assertChurchWithinProductScope($churchId);
    }

    private function resolveAssetType(int $assetTypeId): TipoBem
    {
        $assetType = TipoBem::query()->find($assetTypeId);

        if ($assetType === null) {
            throw new RuntimeException('O tipo de bem selecionado não está mais disponível.');
        }

        $this->assertAssetTypeWithinProductScope($assetType);

        return $assetType;
    }

    private function resolveDependency(int $dependencyId, int $churchId): Dependencia
    {
        $dependency = Dependencia::query()
            ->whereKey($dependencyId)
            ->where('comum_id', $churchId)
            ->first();

        if ($dependency === null) {
            throw new RuntimeException('A dependência selecionada não pertence à igreja informada.');
        }

        return $dependency;
    }

    /**
     * O código é único na tabela de bens (inclusive nos inativos). Um código não pode
     * ser atribuído a mais de um bem, então não há como cadastrar vários com o mesmo código.
     */
    private function assertCodeCanBeUsed(?string $code, int $multiplier): void
    {
        if ($code === null) {
            return;
        }

        if ($multiplier > 1) {
            throw new RuntimeException('Para cadastrar mais de um bem, deixe o código em branco.');
        }

        $emUso = Produto::query()->where('codigo', $code)->exists();
        if ($emUso) {
            throw new RuntimeException('Este código já está em uso por outro bem.');
        }
    }

    private function normalizeItemName(TipoBem $assetType, string $itemName): string
    {
        $normalizedItemName = mb_strtoupper(trim($itemName), 'UTF-8');
        $options = LegacyProductTypeOptionSupport::optionsFromDescription((string) $assetType->descricao);

        if (!in_array($normalizedItemName, $options, true)) {
            throw new RuntimeException('O bem selecionado não é compatível com o tipo de bem informado.');
        }

        return $normalizedItemName;
    }
}
