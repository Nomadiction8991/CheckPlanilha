<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\AdministrationMutationData;
use App\Models\Legacy\Administracao;
use App\Services\LegacyAdministrationManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyAdministrationMutationAtomicityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('administracoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('descricao');
            $table->string('cnpj');
            $table->string('estado');
            $table->string('cidade');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email');
            $table->string('tipo')->nullable();
            $table->json('administracoes_permitidas')->nullable();
        });

        DB::statement("CREATE TRIGGER reject_admin_access_update BEFORE UPDATE ON usuarios BEGIN SELECT RAISE(ABORT, 'sync failed'); END");
    }

    public function testCreateRollsBackAdministrationWhenAdministratorAccessSyncFails(): void
    {
        DB::table('usuarios')->insert(['id' => 1, 'email' => 'admin@localhost']);

        try {
            (new LegacyAdministrationManagementService())->create($this->data('Nova'));
            self::fail('A falha na sincronização deveria cancelar a criação.');
        } catch (QueryException) {
            self::assertDatabaseCount('administracoes', 0);
        }
    }

    public function testUpdateRollsBackAdministrationWhenAdministratorAccessSyncFails(): void
    {
        DB::table('administracoes')->insert([
            'id' => 1,
            'descricao' => 'Antiga',
            'cnpj' => '11222333000181',
            'estado' => 'MT',
            'cidade' => 'CUIABA',
        ]);
        DB::table('usuarios')->insert(['id' => 1, 'email' => 'admin@localhost']);
        $administration = Administracao::query()->findOrFail(1);

        try {
            (new LegacyAdministrationManagementService())->update($administration, $this->data('Nova'));
            self::fail('A falha na sincronização deveria cancelar a atualização.');
        } catch (QueryException) {
            self::assertDatabaseHas('administracoes', ['id' => 1, 'descricao' => 'Antiga']);
        }
    }

    private function data(string $description): AdministrationMutationData
    {
        return new AdministrationMutationData(
            description: $description,
            cnpj: '11222333000181',
            state: 'MT',
            city: 'Cuiabá',
        );
    }
}
