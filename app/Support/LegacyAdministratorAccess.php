<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Legacy\Usuario;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Decide se a sessão atual pertence a um administrador global.
 *
 * O flag is_admin da sessão é gravado só no login. Enquanto o usuário existir no banco,
 * o banco decide: uma conta rebaixada perde o escopo global mesmo com a sessão aberta.
 * Sem registro (ou se o banco não responder), vale o flag da sessão, como antes.
 */
final class LegacyAdministratorAccess
{
    public static function sessionIsAdministrator(): bool
    {
        return self::isAdministrator(
            (int) Session::get('usuario_id', 0),
            (bool) Session::get('is_admin', false),
        );
    }

    /**
     * @param  int   $userId       Usuário da sessão (0 quando não há login).
     * @param  bool  $sessionFlag  Valor de is_admin gravado na sessão no login.
     */
    public static function isAdministrator(int $userId, bool $sessionFlag): bool
    {
        if ($userId > 0) {
            try {
                $user = Usuario::query()->whereKey($userId)->first();
            } catch (Throwable) {
                $user = null;
            }

            if ($user !== null) {
                return (int) $user->ativo === 1 && $user->isAdministrator();
            }
        }

        return $sessionFlag;
    }
}
