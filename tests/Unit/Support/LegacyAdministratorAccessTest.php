<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyAdministratorAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

final class LegacyAdministratorAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
            $table->string('tipo')->nullable();
            $table->string('email')->nullable();
        });
    }

    public function testUserDemotedInDatabaseIsNotAdministratorEvenWithStaleSessionFlag(): void
    {
        DB::table('usuarios')->insert(['id' => 5, 'ativo' => 1, 'tipo' => 'comum', 'email' => 'comum@example.com']);

        Session::put('usuario_id', 5);
        Session::put('is_admin', true);

        self::assertFalse(LegacyAdministratorAccess::sessionIsAdministrator());
    }

    public function testUserPromotedInDatabaseIsAdministratorWithoutSessionFlag(): void
    {
        DB::table('usuarios')->insert(['id' => 6, 'ativo' => 1, 'tipo' => 'administrador', 'email' => 'admin@example.com']);

        Session::put('usuario_id', 6);
        Session::put('is_admin', false);

        self::assertTrue(LegacyAdministratorAccess::sessionIsAdministrator());
    }

    public function testSessionFlagIsUsedWhenNoUserRecordExists(): void
    {
        Session::put('usuario_id', 99);
        Session::put('is_admin', true);

        self::assertTrue(LegacyAdministratorAccess::sessionIsAdministrator());
    }

    public function testNonAdministratorWithoutRecordIsNotAdministrator(): void
    {
        Session::put('usuario_id', 99);
        Session::put('is_admin', false);

        self::assertFalse(LegacyAdministratorAccess::sessionIsAdministrator());
    }
}
