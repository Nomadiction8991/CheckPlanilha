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
use Tests\TestCase;

final class QueryParameterFiltersTest extends TestCase
{
    public function test_list_filters_ignore_array_values_instead_of_casting_them(): void
    {
        $request = Request::create('/listagem', 'GET', [
            'administracao_id' => ['1'],
            'comum_id' => ['2'],
            'dependencia_id' => ['3'],
            'tipo_bem_id' => ['4'],
            'busca' => ['cadeira'],
            'nome' => ['cadeira'],
            'codigo' => ['A-1'],
            'estado' => ['MT'],
            'status' => ['com_nota'],
            'somente_novos' => ['1'],
            'pagina' => ['2'],
        ]);

        $administrations = AdministrationFilters::fromRequest($request);
        $users = UserFilters::fromRequest($request);
        $churches = ChurchFilters::fromRequest($request);
        $assetTypes = AssetTypeFilters::fromRequest($request);
        $departments = DepartmentFilters::fromRequest($request);
        $products = ProductFilters::fromRequest($request);

        self::assertSame('', $administrations->search);
        self::assertNull($administrations->state);
        self::assertSame(1, $administrations->page);
        self::assertNull($users->administrationId);
        self::assertSame('', $users->search);
        self::assertSame('', $users->status);
        self::assertNull($users->state);
        self::assertNull($churches->administrationId);
        self::assertSame('', $churches->search);
        self::assertNull($assetTypes->administrationId);
        self::assertSame('', $assetTypes->search);
        self::assertNull($departments->administrationId);
        self::assertNull($departments->comumId);
        self::assertSame('', $departments->search);
        self::assertNull($products->administrationId);
        self::assertNull($products->comumId);
        self::assertSame('', $products->search);
        self::assertNull($products->dependencyId);
        self::assertNull($products->assetTypeId);
        self::assertNull($products->state);
        self::assertSame('', $products->status);
        self::assertFalse($products->onlyNew);
        self::assertSame(1, $products->page);
    }

    public function test_list_filters_cap_page_numbers_before_offset_overflow(): void
    {
        $request = Request::create('/listagem', 'GET', [
            'pagina' => '999999999999999999999999999999',
        ]);
        $safeMaximum = intdiv(PHP_INT_MAX, 20) + 1;

        self::assertSame($safeMaximum, AdministrationFilters::fromRequest($request)->page);
        self::assertSame($safeMaximum, UserFilters::fromRequest($request)->page);
        self::assertSame($safeMaximum, ChurchFilters::fromRequest($request)->page);
        self::assertSame($safeMaximum, AssetTypeFilters::fromRequest($request)->page);
        self::assertSame($safeMaximum, DepartmentFilters::fromRequest($request)->page);
        self::assertSame($safeMaximum, ProductFilters::fromRequest($request)->page);
    }
}
