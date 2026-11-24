<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\AdministrationFilters;
use App\DTO\AssetTypeFilters;
use App\DTO\ChurchFilters;
use App\DTO\DepartmentFilters;
use App\DTO\ProductFilters;
use App\DTO\UserFilters;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StateFilterValidationTest extends TestCase
{
    #[DataProvider('filterDtos')]
    public function test_unknown_state_is_ignored(string $filterClass): void
    {
        $filters = $filterClass::fromRequest(Request::create('/list', 'GET', ['estado' => 'XYZ']));

        self::assertNull($filters->state);
        self::assertArrayNotHasKey('estado', $filters->toQuery());
    }

    #[DataProvider('filterDtos')]
    public function test_valid_state_is_normalized(string $filterClass): void
    {
        $filters = $filterClass::fromRequest(Request::create('/list', 'GET', ['estado' => ' mt ']));

        self::assertSame('MT', $filters->state);
        self::assertSame('MT', $filters->toQuery()['estado']);
    }

    /** @return array<string, array{class-string}> */
    public static function filterDtos(): array
    {
        return [
            'administrations' => [AdministrationFilters::class],
            'asset types' => [AssetTypeFilters::class],
            'churches' => [ChurchFilters::class],
            'departments' => [DepartmentFilters::class],
            'products' => [ProductFilters::class],
            'users' => [UserFilters::class],
        ];
    }
}
