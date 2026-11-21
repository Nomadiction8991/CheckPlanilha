<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LegacyAuthSessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class LegacyAuthSessionScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('administracoes', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('comums', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('administracao_id')->nullable();
        });
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('email');
            $table->string('senha');
            $table->integer('ativo')->default(1);
            $table->unsignedBigInteger('administracao_id')->nullable();
            $table->json('administracoes_permitidas')->nullable();
            $table->unsignedBigInteger('comum_id')->nullable();
            $table->string('tipo')->nullable();
            $table->json('permissions')->nullable();
        });
    }

    public function test_login_does_not_assign_an_unscoped_user_to_the_first_church(): void
    {
        DB::table('comums')->insert([
            'id' => 7,
            'codigo' => '01',
            'administracao_id' => 2,
        ]);
        $this->createUser(null);

        $authenticated = (new LegacyAuthSessionService)->attempt('usuario@exemplo.com', 'segredo');

        self::assertNull($authenticated['comum_id']);
        self::assertNull(session('comum_id'));
        self::assertNull(DB::table('usuarios')->where('id', 9)->value('comum_id'));
    }

    public function test_login_does_not_replace_a_deleted_church_with_another_administration(): void
    {
        DB::table('comums')->insert([
            'id' => 7,
            'codigo' => '01',
            'administracao_id' => 2,
        ]);
        $this->createUser(99);

        $authenticated = (new LegacyAuthSessionService)->attempt('usuario@exemplo.com', 'segredo');

        self::assertNull($authenticated['comum_id']);
        self::assertNull(session('comum_id'));
        self::assertSame(99, DB::table('usuarios')->where('id', 9)->value('comum_id'));
    }

    public function test_user_can_switch_to_a_church_in_its_allowed_administration_scope(): void
    {
        DB::table('comums')->insert([
            'id' => 7,
            'codigo' => '01',
            'administracao_id' => 3,
        ]);
        $this->createUser(null, 2, [3]);
        session(['usuario_id' => 9]);

        (new LegacyAuthSessionService)->switchChurch(7);

        self::assertSame(7, session('comum_id'));
    }

    public function test_user_cannot_switch_to_a_church_outside_its_allowed_administration_scope(): void
    {
        DB::table('comums')->insert([
            'id' => 8,
            'codigo' => '02',
            'administracao_id' => 4,
        ]);
        $this->createUser(null, 2, [3]);
        session(['usuario_id' => 9]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Igreja fora do escopo permitido.');

        (new LegacyAuthSessionService)->switchChurch(8);
    }

    /** @param array<int, int> $allowedAdministrationIds */
    private function createUser(?int $churchId, ?int $administrationId = null, array $allowedAdministrationIds = []): void
    {
        DB::table('usuarios')->insert([
            'id' => 9,
            'nome' => 'Usuário sem escopo',
            'email' => 'USUARIO@EXEMPLO.COM',
            'senha' => Hash::make('segredo'),
            'ativo' => 1,
            'administracao_id' => $administrationId,
            'administracoes_permitidas' => json_encode($allowedAdministrationIds),
            'comum_id' => $churchId,
            'tipo' => 'operador',
            'permissions' => json_encode([]),
        ]);
    }
}
