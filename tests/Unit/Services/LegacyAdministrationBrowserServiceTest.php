<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\AdministrationFilters;
use App\Models\Legacy\Administracao;
use App\Services\LegacyAdministrationBrowserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyAdministrationBrowserServiceTest extends TestCase
{
    private LegacyAdministrationBrowserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('administracoes', function (Blueprint $table): void {
            $table->id();
            $table->string('descricao')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cidade')->nullable();
            $table->string('cnpj')->nullable();
        });

        $this->service = new LegacyAdministrationBrowserService();
    }

    public function testPaginateFiltersByState(): void
    {
        Administracao::query()->create([
            'descricao' => 'Administração Curitiba',
            'cnpj' => '11111111000111',
            'estado' => 'PR',
            'cidade' => 'CURITIBA',
        ]);

        Administracao::query()->create([
            'descricao' => 'Administração São Paulo',
            'cnpj' => '22222222000122',
            'estado' => 'SP',
            'cidade' => 'SÃO PAULO',
        ]);

        $filtersPr = new AdministrationFilters(search: '', state: 'PR', page: 1, perPage: 10);
        $paginatorPr = $this->service->paginate($filtersPr);

        self::assertSame(1, $paginatorPr->total());
        self::assertSame('Administração Curitiba', $paginatorPr->items()[0]->descricao);

        $filtersSp = new AdministrationFilters(search: '', state: 'SP', page: 1, perPage: 10);
        $paginatorSp = $this->service->paginate($filtersSp);

        self::assertSame(1, $paginatorSp->total());
        self::assertSame('Administração São Paulo', $paginatorSp->items()[0]->descricao);

        $filtersAll = new AdministrationFilters(search: '', state: null, page: 1, perPage: 10);
        $paginatorAll = $this->service->paginate($filtersAll);

        self::assertSame(2, $paginatorAll->total());
    }

    public function testPaginateFiltersBySearchAndStateTogether(): void
    {
        Administracao::query()->create([
            'descricao' => 'Administração Central Curitiba',
            'cnpj' => '11111111000111',
            'estado' => 'PR',
            'cidade' => 'CURITIBA',
        ]);

        Administracao::query()->create([
            'descricao' => 'Administração Central SP',
            'cnpj' => '22222222000122',
            'estado' => 'SP',
            'cidade' => 'SÃO PAULO',
        ]);

        $filters = new AdministrationFilters(search: 'Central', state: 'PR', page: 1, perPage: 10);
        $paginator = $this->service->paginate($filters);

        self::assertSame(1, $paginator->total());
        self::assertSame('Administração Central Curitiba', $paginator->items()[0]->descricao);
    }

    public function testPaginateHandlesExtremelyLargePageNumbers(): void
    {
        Administracao::query()->create([
            'descricao' => 'Administração Central',
            'cnpj' => '11111111000111',
            'estado' => 'MT',
            'cidade' => 'CUIABA',
        ]);

        $filters = AdministrationFilters::fromRequest(Request::create('/administrations?pagina=999999999999999999999999999999'));

        $paginator = $this->service->paginate($filters);

        self::assertSame(1, $paginator->total());
        self::assertSame([], $paginator->items());
    }

    public function testSearchTreatsWildcardCharactersAsLiteralText(): void
    {
        foreach ([[1, 'ADM_NORTE'], [2, 'ADM NORTE'], [3, 'ADM 100%']] as [$id, $descricao]) {
            $adm = new Administracao();
            $adm->forceFill(['id' => $id, 'descricao' => $descricao]);
            $adm->save();
        }

        $underscore = $this->service->paginate(new AdministrationFilters(search: '_', state: null, page: 1, perPage: 10));
        self::assertSame(1, $underscore->total());
        self::assertSame('ADM_NORTE', $underscore->items()[0]->descricao);

        $percent = $this->service->paginate(new AdministrationFilters(search: '%', state: null, page: 1, perPage: 10));
        self::assertSame(1, $percent->total());
        self::assertSame('ADM 100%', $percent->items()[0]->descricao);
    }
}
