<?php

use App\Models\User;
use App\Modules\Selloff\Order\Models\DigitalSale;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin digital sales list returns stored price', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $sale = DigitalSale::query()->where('purchase_code', 'DEMO-DL-001')->firstOrFail();
    $sale->update([
        'price' => 5000,
        'currency_code' => 'NGN',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/digital-sales?per_page=100')
        ->assertOk()
        ->assertJsonFragment([
            'purchase_code' => 'DEMO-DL-001',
            'price' => '5000.00',
            'currency_code' => 'NGN',
        ]);
});
