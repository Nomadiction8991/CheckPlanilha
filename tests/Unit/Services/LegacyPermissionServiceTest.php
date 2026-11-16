<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\LegacyPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

final class LegacyPermissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
            $table->string('tipo')->nullable();
            $table->string('email')->nullable();
            $table->json('permissions')->nullable();
        });
    }

    public function testDemotedUserWithStaleAdminSessionDoesNotGetAllPermissions(): void
    {
        DB::table('usuarios')->insert([
            'id' => 5,
            'ativo' => 1,
            'tipo' => 'comum',
            'email' => 'comum@example.com',
            'permissions' => null,
        ]);

        // Sessão aberta antes da mudança de perfil: ainda carrega o flag de administrador.
        Session::put('usuario_id', 5);
        Session::put('is_admin', true);

        self::assertFalse((new LegacyPermissionService())->can('users.delete'));
    }

    public function testAdministratorStoredInDatabaseKeepsAllPermissionsWithoutStoredPermissions(): void
    {
        DB::table('usuarios')->insert([
            'id' => 6,
            'ativo' => 1,
            'tipo' => 'administrador',
            'email' => 'admin@example.com',
            'permissions' => null,
        ]);

        Session::put('usuario_id', 6);
        Session::put('is_admin', true);

        self::assertTrue((new LegacyPermissionService())->can('users.delete'));
    }

    public function testSessionAdminFlagStillGrantsAccessWhenNoUserRecordExists(): void
    {
        Session::put('usuario_id', 99);
        Session::put('is_admin', true);

        self::assertTrue((new LegacyPermissionService())->can('users.delete'));
    }
}
