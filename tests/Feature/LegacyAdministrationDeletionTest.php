<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Legacy\Administracao;
use App\Services\LegacyAdministrationManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class LegacyAdministrationDeletionTest extends TestCase
{
    public function test_concurrent_user_link_is_reported_as_business_error_instead_of_database_exception(): void
    {
        Schema::create('administracoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('descricao');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
            $table->foreign('administracao_id')->references('id')->on('administracoes')->restrictOnDelete();
        });
        Schema::create('importacoes', fn (Blueprint $table) => $table->increments('id'));
        Schema::create('tipos_bens', fn (Blueprint $table) => $table->increments('id'));
        Schema::create('comums', fn (Blueprint $table) => $table->increments('id'));

        $administrationId = DB::table('administracoes')->insertGetId(['descricao' => 'Central']);
        $administration = Administracao::query()->findOrFail($administrationId);
        $linkInserted = false;

        DB::connection()->beforeExecuting(function (string $query) use ($administrationId, &$linkInserted): void {
            if (! $linkInserted && str_starts_with(strtolower(ltrim($query)), 'delete from')) {
                $linkInserted = true;
                DB::table('usuarios')->insert(['administracao_id' => $administrationId]);
            }
        });

        try {
            (new LegacyAdministrationManagementService)->delete($administration);
            self::fail('O vínculo criado durante a exclusão deveria impedir a operação.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Esta administração não pode ser excluída porque há registros vinculados.',
                $exception->getMessage(),
            );
        }

        self::assertDatabaseHas('administracoes', ['id' => $administrationId]);
    }

    public function test_deletion_reports_when_administration_is_linked_to_churches(): void
    {
        Schema::create('administracoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('descricao');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
        });
        Schema::create('importacoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
        });
        Schema::create('tipos_bens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
        });
        Schema::create('comums', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
            $table->foreign('administracao_id')->references('id')->on('administracoes')->restrictOnDelete();
        });

        $administrationId = DB::table('administracoes')->insertGetId(['descricao' => 'Central']);
        DB::table('comums')->insert(['administracao_id' => $administrationId]);
        $administration = Administracao::query()->findOrFail($administrationId);

        try {
            (new LegacyAdministrationManagementService)->delete($administration);
            self::fail('A exclusão deveria ser impedida por igrejas vinculadas.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Esta administração não pode ser excluída porque já está vinculada a igrejas.',
                $exception->getMessage(),
            );
        }

        self::assertDatabaseHas('administracoes', ['id' => $administrationId]);
    }

    public function test_deletion_reports_when_administration_is_linked_to_asset_types(): void
    {
        Schema::create('administracoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('descricao');
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
        });
        Schema::create('importacoes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
        });
        Schema::create('tipos_bens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
            $table->foreign('administracao_id')->references('id')->on('administracoes')->restrictOnDelete();
        });
        Schema::create('comums', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('administracao_id')->nullable();
            $table->foreign('administracao_id')->references('id')->on('administracoes')->restrictOnDelete();
        });

        $administrationId = DB::table('administracoes')->insertGetId(['descricao' => 'Central']);
        DB::table('tipos_bens')->insert([
            'administracao_id' => $administrationId,
        ]);
        $administration = Administracao::query()->findOrFail($administrationId);

        try {
            (new LegacyAdministrationManagementService)->delete($administration);
            self::fail('A exclusão deveria ser impedida por tipos de bens vinculados.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Esta administração não pode ser excluída porque já está vinculada a tipos de bens.',
                $exception->getMessage(),
            );
        }

        self::assertDatabaseHas('administracoes', ['id' => $administrationId]);
    }
}
