<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('legacy mobile login returns sanctum token and mobile envelope', function () {
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
});

test('mobile can browse paginated products with image prefix', function () {
    $response = $this->getJson('/api/v1/products/paginated?limit=5');

    $response->assertOk()
        ->assertJsonPath('status', '1')
        ->assertJsonStructure([
            'data',
            'meta' => ['pagination' => ['current_page', 'total'], 'image_url_prefix'],
        ]);

    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
});

test('mobile wallet purchase flow via legacy and canonical paths', function () {
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

    expect(Order::query()->where('buyer_id', $buyer->id)->count())->toBe($startingOrders + 1);

    $this->getJson('/api/v1/mobile/orders')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', $startingOrders + 1);
});

test('mobile health endpoint reports sanctum', function () {
    $this->getJson('/api/v1/mobile/health')
        ->assertOk()
        ->assertJsonPath('data.auth', 'sanctum')
        ->assertJsonPath('data.legacy_jwt', 'removed');
});

test('canonical mobile register accepts fullname', function () {
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
});
