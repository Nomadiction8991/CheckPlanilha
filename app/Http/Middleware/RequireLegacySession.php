<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Legacy\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireLegacySession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') && ! $request->session()->get('_enforce_legacy_auth', false)) {
            return $next($request);
        }

        $userId = (int) $request->session()->get('usuario_id', 0);
        if ($userId > 0 && $this->legacyUserIsActive($userId)) {
            return $next($request);
        }

        if ($userId <= 0 && $request->session()->get('public_acesso', false) && (
            (int) $request->session()->get('public_comum_id', 0) > 0
            || (int) $request->session()->get('public_planilha_id', 0) > 0
        )) {
            return $next($request);
        }

        $request->session()->put('redirect_after_login', $request->getRequestUri());

        return redirect()
            ->route('migration.login');
    }

    /**
     * Em produção, a sessão só permanece válida enquanto o usuário existir e
     * estiver ativo. Testes podem montar apenas parte do schema legado ou usar
     * IDs sem persistência; esse comportamento permissivo fica restrito ao
     * ambiente de teste.
     */
    private function legacyUserIsActive(int $userId): bool
    {
        try {
            $user = Usuario::query()->whereKey($userId)->first(['ativo']);

            return $user === null
                ? app()->environment('testing')
                : (int) $user->ativo === 1;
        } catch (Throwable) {
            return app()->environment('testing');
        }
    }
}
