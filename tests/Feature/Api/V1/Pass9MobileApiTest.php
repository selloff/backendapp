<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass9MobileApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_legacy_mobile_login_returns_sanctum_token_and_mobile_envelope(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'buyer@selloff.test',
            'password' => 'password',
            'device_name' => 'mobile',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', '1')
            ->assertJsonPath('meta.auth', 'sanctum')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'email', 'wallet']]])
            ->assertHeader('X-Selloff-Auth', 'sanctum');
    }

    public function test_mobile_can_browse_paginated_products_with_image_prefix(): void
    {
        $response = $this->getJson('/api/v1/products/paginated?limit=5');

        $response->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination' => ['current_page', 'total'], 'image_url_prefix'],
            ]);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_mobile_wallet_purchase_flow_via_legacy_and_canonical_paths(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        $startingOrders = Order::query()->where('buyer_id', $buyer->id)->count();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/mobile/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('status', '1');

        $this->postJson('/api/v1/mobile/checkout/wallet')
            ->assertCreated()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('success', true);

        $this->assertSame($startingOrders + 1, Order::query()->where('buyer_id', $buyer->id)->count());

        $this->getJson('/api/v1/mobile/orders')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', $startingOrders + 1);
    }

    public function test_mobile_health_endpoint_reports_sanctum(): void
    {
        $this->getJson('/api/v1/mobile/health')
            ->assertOk()
            ->assertJsonPath('data.auth', 'sanctum')
            ->assertJsonPath('data.legacy_jwt', 'removed');
    }

    public function test_canonical_mobile_register_accepts_fullname(): void
    {
        $response = $this->postJson('/api/v1/mobile/register', [
            'fullname' => 'Mobile Tester',
            'email' => 'mobile.tester@selloff.test',
            'password' => 'password123',
            'phone_number' => '08012345678',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('data.user.first_name', 'Mobile')
            ->assertJsonPath('data.user.last_name', 'Tester');
    }
}
