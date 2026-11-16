<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\LegacyPermissionServiceInterface;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tests for LegacyMigrationDashboardController.
 *
 * The controller is a single-action invokable that checks legacy_permissions
 * from the session and redirects to the first matching migration section.
 * It does NOT render a view – it always performs a redirect.
 */
final class LegacyMigrationDashboardControllerTest extends TestCase
{
    public function test_unauthenticated_user_with_no_permissions_is_redirected_to_login(): void
    {
        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.login'));
    }

    public function test_user_with_products_view_permission_is_redirected_to_products_index(): void
    {
        $this->authenticateWithPermissions(['products.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.products.index'));
    }

    public function test_user_with_churches_view_permission_is_redirected_to_churches_index(): void
    {
        $this->authenticateWithPermissions(['churches.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.churches.index'));
    }

    public function test_user_with_departments_view_permission_is_redirected_to_departments_index(): void
    {
        $this->authenticateWithPermissions(['departments.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.departments.index'));
    }

    public function test_user_with_asset_types_view_permission_is_redirected_to_asset_types_index(): void
    {
        $this->authenticateWithPermissions(['asset-types.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.asset-types.index'));
    }

    public function test_user_with_users_view_permission_is_redirected_to_users_index(): void
    {
        $this->authenticateWithPermissions(['users.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.users.index'));
    }

    public function test_user_with_administrations_view_permission_is_redirected_to_administrations_index(): void
    {
        $this->authenticateWithPermissions(['administrations.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.administrations.index'));
    }

    public function test_user_with_spreadsheets_import_permission_is_redirected_to_spreadsheets_create(): void
    {
        $this->authenticateWithPermissions(['spreadsheets.import' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.spreadsheets.create'));
    }

    public function test_user_with_reports_view_permission_is_redirected_to_reports_index(): void
    {
        $this->authenticateWithPermissions(['reports.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.reports.index'));
    }

    public function test_user_with_audits_view_permission_is_redirected_to_audits_index(): void
    {
        $this->authenticateWithPermissions(['audits.view' => true]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.audits.index'));
    }

    public function test_first_permission_in_priority_order_wins(): void
    {
        // 'products.view' is checked first in the controller, so it wins
        // even when the user also has other permissions.
        $this->authenticateWithPermissions([
            'reports.view' => true,
            'users.view' => true,
            'products.view' => true,
        ]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.products.index'));
    }

    public function test_user_without_any_permission_is_redirected_to_login(): void
    {
        $this->withSession([
            'legacy_permissions' => [],
        ]);

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.login'));
    }

    public function test_revoked_session_permission_uses_the_remaining_current_permission(): void
    {
        $this->withSession([
            'usuario_id' => 42,
            'legacy_permissions' => [
                'products.view' => true,
            ],
        ]);
        $this->mock(LegacyPermissionServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('currentPermissions')->once()->andReturn([
                'products.view' => false,
                'churches.view' => false,
                'users.view' => false,
                'administrations.view' => false,
                'spreadsheets.import' => false,
                'reports.view' => true,
            ]);
        });

        $response = $this->get(route('migration.dashboard'));

        $response->assertRedirect(route('migration.reports.index'));
    }

    /** @param array<string, bool> $permissions */
    private function authenticateWithPermissions(array $permissions): void
    {
        $this->withSession([
            'usuario_id' => 42,
            'legacy_permissions' => $permissions,
        ]);
        $this->mock(LegacyPermissionServiceInterface::class, function (MockInterface $mock) use ($permissions): void {
            $mock->shouldReceive('currentPermissions')->once()->andReturn($permissions);
        });
    }
}
