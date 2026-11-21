<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\DepartmentFilters;
use App\Models\Legacy\Administracao;
use App\Models\Legacy\Comum;
use App\Models\Legacy\Dependencia;
use App\Services\LegacyDepartmentBrowserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

final class LegacyDepartmentBrowserServiceTest extends TestCase
{
    private LegacyDepartmentBrowserService $service;

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

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->string('tipo')->nullable();
            $table->integer('ativo')->default(1);
        });

        Schema::create('comums', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('descricao')->nullable();
            $table->string('estado', 2)->nullable();
            $table->unsignedBigInteger('administracao_id')->nullable();
        });

        Schema::create('dependencias', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->string('descricao')->nullable();
        });

        Schema::create('produtos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->unsignedBigInteger('dependencia_id')->nullable();
            $table->string('descricao')->nullable();
            $table->integer('ativo')->default(1);
        });

        $this->service = new LegacyDepartmentBrowserService();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('dependencias');
        Schema::dropIfExists('comums');
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('administracoes');
        parent::tearDown();
    }

    public function testPaginateFiltersByAdministrationId(): void
    {
        $admin1 = new Administracao();
        $admin1->forceFill(['id' => 10, 'descricao' => 'Administração SP']);
        $admin1->save();

        $admin2 = new Administracao();
        $admin2->forceFill(['id' => 20, 'descricao' => 'Administração RJ']);
        $admin2->save();

        $church1 = new Comum();
        $church1->forceFill(['id' => 100, 'codigo' => 'SP-01', 'descricao' => 'Central SP', 'administracao_id' => 10]);
        $church1->save();

        $church2 = new Comum();
        $church2->forceFill(['id' => 200, 'codigo' => 'RJ-01', 'descricao' => 'Central RJ', 'administracao_id' => 20]);
        $church2->save();

        $dep1 = new Dependencia();
        $dep1->forceFill(['id' => 1, 'comum_id' => 100, 'descricao' => 'SALAO SP']);
        $dep1->save();

        $dep2 = new Dependencia();
        $dep2->forceFill(['id' => 2, 'comum_id' => 200, 'descricao' => 'SALAO RJ']);
        $dep2->save();

        $filters = new DepartmentFilters(
            administrationId: 10,
            comumId: null,
            search: '',
            state: null,
            page: 1,
            perPage: 10,
        );

        $result = $this->service->paginate($filters);

        self::assertSame(1, $result->total());
        self::assertSame(1, $result->items()[0]->id);
        self::assertSame('SALAO SP', $result->items()[0]->descricao);
    }

    public function testPaginateFiltersByState(): void
    {
        $church1 = new Comum();
        $church1->forceFill(['id' => 100, 'codigo' => 'SP-01', 'descricao' => 'Central SP', 'estado' => 'SP']);
        $church1->save();

        $church2 = new Comum();
        $church2->forceFill(['id' => 200, 'codigo' => 'RJ-01', 'descricao' => 'Central RJ', 'estado' => 'RJ']);
        $church2->save();

        $dep1 = new Dependencia();
        $dep1->forceFill(['id' => 1, 'comum_id' => 100, 'descricao' => 'SALAO SP']);
        $dep1->save();

        $dep2 = new Dependencia();
        $dep2->forceFill(['id' => 2, 'comum_id' => 200, 'descricao' => 'SALAO RJ']);
        $dep2->save();

        $filters = new DepartmentFilters(
            administrationId: null,
            comumId: null,
            search: '',
            state: 'RJ',
            page: 1,
            perPage: 10,
        );

        $result = $this->service->paginate($filters);

        self::assertSame(1, $result->total());
        self::assertSame(2, $result->items()[0]->id);
        self::assertSame('SALAO RJ', $result->items()[0]->descricao);
    }

    public function testAdministrationOptionsReturnsAllAdministrationsOrdered(): void
    {
        $adminB = new Administracao();
        $adminB->forceFill(['id' => 2, 'descricao' => 'Belo Horizonte']);
        $adminB->save();

        $adminA = new Administracao();
        $adminA->forceFill(['id' => 1, 'descricao' => 'Aracaju']);
        $adminA->save();

        $options = $this->service->administrationOptions();

        self::assertCount(2, $options);
        self::assertSame(1, $options->first()->id);
        self::assertSame('Aracaju', $options->first()->descricao);
    }

    public function testBrowserRespectsAdministrationScope(): void
    {
        $admin1 = new Administracao();
        $admin1->forceFill(['id' => 10, 'descricao' => 'Administração SP']);
        $admin1->save();

        $admin2 = new Administracao();
        $admin2->forceFill(['id' => 20, 'descricao' => 'Administração RJ']);
        $admin2->save();

        $church1 = new Comum();
        $church1->forceFill(['id' => 100, 'codigo' => 'SP-01', 'descricao' => 'Central SP', 'administracao_id' => 10]);
        $church1->save();

        $church2 = new Comum();
        $church2->forceFill(['id' => 200, 'codigo' => 'RJ-01', 'descricao' => 'Central RJ', 'administracao_id' => 20]);
        $church2->save();

        $dep1 = new Dependencia();
        $dep1->forceFill(['id' => 1, 'comum_id' => 100, 'descricao' => 'SALAO SP']);
        $dep1->save();

        $dep2 = new Dependencia();
        $dep2->forceFill(['id' => 2, 'comum_id' => 200, 'descricao' => 'SALAO RJ']);
        $dep2->save();

        \Illuminate\Support\Facades\Session::put('is_admin', false);
        \Illuminate\Support\Facades\Session::put('administracao_id', 10);
        \Illuminate\Support\Facades\Session::put('administracoes_permitidas', [10]);

        $filters = new DepartmentFilters(
            administrationId: null,
            comumId: null,
            search: '',
            state: null,
            page: 1,
            perPage: 10,
        );

        $result = $this->service->paginate($filters);
        self::assertSame(1, $result->total());
        self::assertSame(1, $result->items()[0]->id);

        self::assertSame(1, $this->service->countAll());

        $churches = $this->service->churchOptions();
        self::assertCount(1, $churches);
        self::assertSame(100, $churches->first()->id);

        $administrations = $this->service->administrationOptions();
        self::assertCount(1, $administrations);
        self::assertSame(10, $administrations->first()->id);
    }

    public function testAuthenticatedUserWithoutAdministrationScopeCannotSeeAllDepartments(): void
    {
        DB::table('usuarios')->insert(['id' => 90, 'email' => 'user@example.test', 'tipo' => 'usuario', 'ativo' => 1]);
        DB::table('administracoes')->insert([
            ['id' => 10, 'descricao' => 'Administração A'],
            ['id' => 20, 'descricao' => 'Administração B'],
        ]);
        DB::table('comums')->insert([
            ['id' => 100, 'codigo' => 'A-01', 'descricao' => 'Igreja A', 'administracao_id' => 10],
            ['id' => 200, 'codigo' => 'B-01', 'descricao' => 'Igreja B', 'administracao_id' => 20],
        ]);
        DB::table('dependencias')->insert([
            ['id' => 1, 'comum_id' => 100, 'descricao' => 'Salão A'],
            ['id' => 2, 'comum_id' => 200, 'descricao' => 'Salão B'],
        ]);
        Session::put('usuario_id', 90);

        $result = $this->service->paginate(new DepartmentFilters(null, null, '', null, 1, 10));

        self::assertSame(0, $result->total());
        self::assertSame(0, $this->service->countAll());
        self::assertCount(0, $this->service->churchOptions());
        self::assertCount(0, $this->service->administrationOptions());
    }

    public function testSearchTreatsUnderscoreAsLiteralCharacter(): void
    {
        $church = new Comum();
        $church->forceFill(['id' => 100, 'codigo' => 'SP-01', 'descricao' => 'Central SP']);
        $church->save();

        $dep1 = new Dependencia();
        $dep1->forceFill(['id' => 1, 'comum_id' => 100, 'descricao' => 'CULTO_JOVEM']);
        $dep1->save();

        $dep2 = new Dependencia();
        $dep2->forceFill(['id' => 2, 'comum_id' => 100, 'descricao' => 'CULTO JOVEM']);
        $dep2->save();

        $result = $this->service->paginate($this->filtersWithSearch('_'));

        self::assertSame(1, $result->total());
        self::assertSame('CULTO_JOVEM', $result->items()[0]->descricao);
    }

    public function testSearchTreatsPercentAsLiteralCharacter(): void
    {
        $church = new Comum();
        $church->forceFill(['id' => 100, 'codigo' => 'SP-01', 'descricao' => 'Central SP']);
        $church->save();

        $dep1 = new Dependencia();
        $dep1->forceFill(['id' => 1, 'comum_id' => 100, 'descricao' => 'SALAO 100% FIEL']);
        $dep1->save();

        $dep2 = new Dependencia();
        $dep2->forceFill(['id' => 2, 'comum_id' => 100, 'descricao' => 'SALAO 1000 FIEL']);
        $dep2->save();

        $result = $this->service->paginate($this->filtersWithSearch('%'));

        self::assertSame(1, $result->total());
        self::assertSame('SALAO 100% FIEL', $result->items()[0]->descricao);

        $result = $this->service->paginate($this->filtersWithSearch('100%'));

        self::assertSame(1, $result->total());
        self::assertSame('SALAO 100% FIEL', $result->items()[0]->descricao);
    }

    private function filtersWithSearch(string $search): DepartmentFilters
    {
        return new DepartmentFilters(
            administrationId: null,
            comumId: null,
            search: $search,
            state: null,
            page: 1,
            perPage: 10,
        );
    }
}
