<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Services\AdminReportService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_reports_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/reports/sales')
            ->assertUnauthorized();
    }

    public function test_admin_reports_reject_unknown_type(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/reports/not-a-report')
            ->assertNotFound();
    }

    public function test_admin_reports_return_expected_envelope_for_all_types(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        foreach (AdminReportService::TYPES as $type) {
            $response = $this->getJson('/api/v1/admin/reports/'.$type.'?from=2026-01-01&to=2026-06-28')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.report', $type);

            $response->assertJsonStructure([
                'data' => [
                    'report',
                    'period' => ['from', 'to', 'previous_from', 'previous_to'],
                    'summary',
                    'series',
                    'breakdown',
                    'details' => ['data', 'total', 'current_page', 'last_page', 'per_page'],
                ],
            ]);
        }
    }

    public function test_admin_reports_sales_include_deltas_and_pagination(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/reports/sales?from=2026-01-01&to=2026-06-28&page=1&per_page=15')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'gmv',
                        'gmv_delta_pct',
                        'orders',
                        'orders_delta_pct',
                    ],
                ],
            ]);
    }

    public function test_admin_reports_export_returns_csv(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/admin/reports/sales/export?from=2026-01-01&to=2026-06-28');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }
}
