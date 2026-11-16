<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\LegacyPermissionServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LegacyMigrationDashboardController extends Controller
{
    public function __construct(
        private readonly LegacyPermissionServiceInterface $permissions,
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        if ((int) Session::get('usuario_id', 0) <= 0) {
            return redirect()->route('migration.login');
        }

        $permissions = $this->permissions->currentPermissions();

        if (!empty($permissions['products.view'])) {
            return redirect()->route('migration.products.index');
        }

        if (!empty($permissions['churches.view'])) {
            return redirect()->route('migration.churches.index');
        }

        if (!empty($permissions['departments.view'])) {
            return redirect()->route('migration.departments.index');
        }

        if (!empty($permissions['asset-types.view'])) {
            return redirect()->route('migration.asset-types.index');
        }

        if (!empty($permissions['users.view'])) {
            return redirect()->route('migration.users.index');
        }

        if (!empty($permissions['administrations.view'])) {
            return redirect()->route('migration.administrations.index');
        }

        if (!empty($permissions['spreadsheets.import'])) {
            return redirect()->route('migration.spreadsheets.create');
        }

        if (!empty($permissions['reports.view'])) {
            return redirect()->route('migration.reports.index');
        }

        if (!empty($permissions['audits.view'])) {
            return redirect()->route('migration.audits.index');
        }

        return redirect()->route('migration.login');
    }
}
