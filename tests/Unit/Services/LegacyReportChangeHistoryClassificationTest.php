<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\LegacyAuthSessionServiceInterface;
use App\Services\LegacyReportService;
use App\Services\LegacyReportTemplateService;
use Tests\TestCase;

final class LegacyReportChangeHistoryClassificationTest extends TestCase
{
    public function testEditedProductWithLabelFlagAppearsOnlyOnceInLabelSection(): void
    {
        $auth = $this->mock(LegacyAuthSessionServiceInterface::class);
        $service = new LegacyReportService(new LegacyReportTemplateService(), $auth);

        $product = [
            'origem' => 'produto',
            'codigo' => '1001',
            'editado' => 1,
            'imprimir' => 1,
            'checado' => 0,
            'observacoes' => null,
            'ativo' => 1,
        ];

        $sections = $this->classify($service, [$product]);

        $occurrences = array_filter(
            $sections['etiqueta']['itens'],
            static fn (array $item): bool => $item['codigo'] === '1001',
        );

        self::assertCount(1, $occurrences, 'Produto editado com etiqueta não pode aparecer duas vezes na mesma seção.');
        self::assertCount(1, $sections['alteracoes']['itens']);
    }

    public function testUneditedProductWithLabelFlagStillAppearsInLabelSection(): void
    {
        $auth = $this->mock(LegacyAuthSessionServiceInterface::class);
        $service = new LegacyReportService(new LegacyReportTemplateService(), $auth);

        $product = [
            'origem' => 'produto',
            'codigo' => '2002',
            'editado' => 0,
            'imprimir' => 1,
            'checado' => 0,
            'observacoes' => null,
            'ativo' => 1,
        ];

        $sections = $this->classify($service, [$product]);

        self::assertCount(1, $sections['etiqueta']['itens']);
        self::assertCount(0, $sections['alteracoes']['itens']);
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @return array<string, array{titulo: string, itens: array<int, array<string, mixed>>}>
     */
    private function classify(LegacyReportService $service, array $products): array
    {
        $method = new \ReflectionMethod($service, 'classifyChangeHistoryProducts');
        $method->setAccessible(true);

        /** @var array<string, array{titulo: string, itens: array<int, array<string, mixed>>}> $sections */
        $sections = $method->invoke($service, $products);

        return $sections;
    }
}
