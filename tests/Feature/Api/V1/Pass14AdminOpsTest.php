<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass14AdminOpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_commerce_ops_endpoints(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $order = Order::query()->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->getJson('/api/v1/admin/refunds')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/payouts')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/v1/admin/transactions')->assertOk()->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }
}
