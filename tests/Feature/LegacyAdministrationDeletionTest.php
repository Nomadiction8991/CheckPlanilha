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
    public function testDeletionReportsWhenAdministrationIsLinkedToChurches(): void
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
            (new LegacyAdministrationManagementService())->delete($administration);
            self::fail('A exclusão deveria ser impedida por igrejas vinculadas.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Esta administração não pode ser excluída porque já está vinculada a igrejas.',
                $exception->getMessage(),
            );
        }

        self::assertDatabaseHas('administracoes', ['id' => $administrationId]);
    }

    public function testDeletionReportsWhenAdministrationIsLinkedToAssetTypes(): void
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
            (new LegacyAdministrationManagementService())->delete($administration);
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
