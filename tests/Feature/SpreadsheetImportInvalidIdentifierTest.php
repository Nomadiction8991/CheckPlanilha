<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\LegacySpreadsheetImportServiceInterface;
use Mockery\MockInterface;
use Tests\TestCase;

final class SpreadsheetImportInvalidIdentifierTest extends TestCase
{
    public function testLegacyProgressRejectsArrayImportId(): void
    {
        $this->mock(LegacySpreadsheetImportServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('loadProgress');
        });

        $response = $this->get('/spreadsheets/api/progress?id%5B%5D=5');

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Importação inválida',
        ]);
    }
}
