<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Services\AdminReportService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin reports require authentication', function () {
    $this->getJson('/api/v1/admin/reports/sales')
        ->assertUnauthorized();
});

test('admin reports reject unknown type', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/reports/not-a-report')
        ->assertNotFound();
});

test('admin reports return expected envelope for all types', function () {
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
});

test('admin reports sales include deltas and pagination', function () {
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
});

test('admin reports export returns csv', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->get('/api/v1/admin/reports/sales/export?from=2026-01-01&to=2026-06-28');

    $response->assertOk();
    $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
});
