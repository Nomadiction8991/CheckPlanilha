<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RequireLegacySession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RequireLegacySessionTest extends TestCase
{
    public function test_redirects_to_login_when_session_user_no_longer_exists(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
        });

        $this->app->detectEnvironment(static fn (): string => 'local');

        $request = Request::create('/protected', 'GET');
        $session = $this->app->make('session')->driver();
        $session->setId('stale-session-test');
        $session->put('_enforce_legacy_auth', true);
        $session->put('usuario_id', 987654321);
        $request->setLaravelSession($session);

        $response = (new RequireLegacySession)->handle(
            $request,
            static fn (): Response => new Response('protected content', 200),
        );

        self::assertTrue($response->isRedirect());
        self::assertSame(route('migration.login'), $response->getTargetUrl());
    }

    public function test_redirects_to_login_when_user_status_cannot_be_verified(): void
    {
        Schema::dropIfExists('usuarios');
        $this->app->detectEnvironment(static fn (): string => 'local');

        $request = Request::create('/protected', 'GET');
        $session = $this->app->make('session')->driver();
        $session->setId('unverified-session-test');
        $session->put('_enforce_legacy_auth', true);
        $session->put('usuario_id', 987654321);
        $request->setLaravelSession($session);

        $response = (new RequireLegacySession)->handle(
            $request,
            static fn (): Response => new Response('protected content', 200),
        );

        self::assertTrue($response->isRedirect());
        self::assertSame(route('migration.login'), $response->getTargetUrl());
    }

    public function test_public_access_does_not_restore_a_removed_users_session(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
        });

        $this->app->detectEnvironment(static fn (): string => 'local');

        $request = Request::create('/protected', 'GET');
        $session = $this->app->make('session')->driver();
        $session->setId('removed-public-user-session');
        $session->put([
            '_enforce_legacy_auth' => true,
            'usuario_id' => 987654321,
            'public_acesso' => true,
            'public_comum_id' => 7,
        ]);
        $request->setLaravelSession($session);

        $response = (new RequireLegacySession)->handle(
            $request,
            static fn (): Response => new Response('protected content', 200),
        );

        self::assertTrue($response->isRedirect());
        self::assertSame(route('migration.login'), $response->getTargetUrl());
    }

    public function test_refreshes_administration_scope_from_database_for_an_authenticated_session(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->id();
            $table->integer('ativo')->default(1);
            $table->unsignedBigInteger('administracao_id')->nullable();
            $table->json('administracoes_permitidas')->nullable();
            $table->string('email')->nullable();
            $table->json('permissions')->nullable();
            $table->string('tipo')->nullable();
        });
        DB::table('usuarios')->insert([
            'id' => 41,
            'ativo' => 1,
            'administracao_id' => 3,
            'administracoes_permitidas' => json_encode([3]),
            'email' => 'operador@example.test',
            'permissions' => json_encode(['products.view' => false]),
            'tipo' => 'operador',
        ]);

        $this->app->detectEnvironment(static fn (): string => 'local');

        $request = Request::create('/protected', 'GET');
        $session = $this->app->make('session')->driver();
        $session->setId('stale-administration-scope-test');
        $session->put([
            '_enforce_legacy_auth' => true,
            'usuario_id' => 41,
            'administracao_id' => 4,
            'administracoes_permitidas' => [3, 4],
            'is_admin' => true,
            'legacy_permissions' => ['products.view' => true],
        ]);
        $request->setLaravelSession($session);

        $response = (new RequireLegacySession)->handle(
            $request,
            static fn (): Response => new Response('protected content', 200),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $session->get('administracao_id'));
        self::assertSame([3], $session->get('administracoes_permitidas'));
        self::assertFalse($session->get('is_admin'));
        self::assertSame(['products.view' => false], $session->get('legacy_permissions'));
    }
}
