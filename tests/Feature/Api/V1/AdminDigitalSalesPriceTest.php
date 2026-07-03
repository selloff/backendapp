<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Order\Models\DigitalSale;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDigitalSalesPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_digital_sales_list_returns_stored_price(): void
    {
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
    }
}
