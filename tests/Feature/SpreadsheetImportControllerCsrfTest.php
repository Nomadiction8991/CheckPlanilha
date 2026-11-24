<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tests that POST endpoints on SpreadsheetImportController reject requests
 * without a valid CSRF token (returning 419), including AJAX endpoints.
 *
 * Laravel's PreventRequestForgery::runningUnitTests() bypasses CSRF when
 * APP_ENV=testing. We temporarily set the env to 'production' to verify the
 * 419 rejection path, then restore it for exempt-route tests.
 */
final class SpreadsheetImportControllerCsrfTest extends TestCase
{
    private ?string $savedEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = $this->app->environment();
    }

    protected function tearDown(): void
    {
        if ($this->savedEnv !== null) {
            $this->app->instance('env', $this->savedEnv);
        }
        parent::tearDown();
    }

    /**
     * Temporarily set the app environment so that the CSRF middleware runs
     * its full verification logic.
     */
    private function enableCsrfProtection(): void
    {
        $this->app->instance('env', 'production');
    }

    /**
     * Restore the testing environment (CSRF bypass through runningUnitTests).
     */
    private function restoreCsrfBypass(): void
    {
        if ($this->savedEnv !== null) {
            $this->app->instance('env', $this->savedEnv);
        }
    }

    // ─── CSRF-protected POST endpoints (should return 419) ──────────────

    public function test_store_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->post(route('migration.spreadsheets.store'), [
            'administracao_id' => 1,
        ]);

        $response->assertStatus(419);
    }

    public function test_confirm_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->post(route('migration.spreadsheets.confirm', ['importacao' => 1]));

        $response->assertStatus(419);
    }

    public function test_resolve_error_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->post(route('migration.spreadsheets.errors.resolve', ['erro' => 1]));

        $response->assertStatus(419);
    }

    // ─── AJAX POST endpoints must still validate their CSRF header ──────

    public function test_save_preview_actions_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->postJson(route('migration.spreadsheets.preview.actions', [
            'importacao' => 999,
        ]), ['acoes' => []]);

        $response->assertStatus(419);
    }

    public function test_bulk_preview_action_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->postJson(route('migration.spreadsheets.preview.bulk', [
            'importacao' => 999,
        ]), ['acao' => 'ignorar']);

        $response->assertStatus(419);
    }

    public function test_start_processing_rejects_without_csrf_token(): void
    {
        $this->enableCsrfProtection();

        $response = $this->postJson(route('migration.spreadsheets.start', [
            'importacao' => 999,
        ]));

        $response->assertStatus(419);
    }

    public function test_ajax_preview_action_accepts_a_valid_csrf_header(): void
    {
        $this->enableCsrfProtection();
        $token = str_repeat('a', 40);

        $response = $this->withSession(['_token' => $token])
            ->postJson(route('migration.spreadsheets.preview.actions', [
                'importacao' => 999,
            ]), ['acoes' => []], ['X-CSRF-TOKEN' => $token]);

        $this->assertNotSame(419, $response->getStatusCode());
    }

    // ─── Compatibility routes also reject requests without a token ────

    public function test_legacy_save_preview_actions_not_exempt(): void
    {
        $this->enableCsrfProtection();

        $response = $this->postJson(route('migration.compat.spreadsheets.preview.save-actions'), [
            'importacao_id' => 1,
        ]);

        $response->assertStatus(419);
    }

    public function test_legacy_confirm_not_exempt(): void
    {
        $this->enableCsrfProtection();

        $response = $this->postJson(route('migration.compat.spreadsheets.confirm'), [
            'importacao_id' => 1,
        ]);

        $response->assertStatus(419);
    }

    // ─── Sanity check: with default test bypass, CSRF does not block ────

    public function test_store_not_blocked_when_csrf_bypass_active(): void
    {
        // Default APP_ENV=testing keeps runningUnitTests() active, so CSRF
        // is bypassed. The request may fail at controller level but must
        // NOT be blocked by CSRF.
        $this->restoreCsrfBypass();

        $response = $this->post(route('migration.spreadsheets.store'), [
            'administracao_id' => 1,
        ]);

        $this->assertNotSame(419, $response->getStatusCode(), 'Default test bypass should not return 419');
    }
}
