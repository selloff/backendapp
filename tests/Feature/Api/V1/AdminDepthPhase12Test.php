<?php

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('dashboard exposes granular capabilities', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/dashboard')
        ->assertOk()
        ->assertJsonPath('data.capabilities.orders', true)
        ->assertJsonPath('data.capabilities.reviews', true)
        ->assertJsonPath('data.capabilities.comments', true)
        ->assertJsonStructure([
            'data' => [
                'latest_orders',
                'latest_reviews',
                'latest_comments',
            ],
        ]);
});

test('admin can save homepage layout settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'homepage',
        'settings' => [
            'featured_categories' => true,
            'index_latest_products' => true,
            'index_latest_products_count' => 10,
            'index_trending_products_count' => 6,
            'index_products_per_row' => 5,
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.index_latest_products_count', 10)
        ->assertJsonPath('data.settings.index_trending_products_count', 6);
});

test('admin orders support status and search filters', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $order = Order::query()->orderByDesc('id')->first();
    expect($order)->not->toBeNull();

    $this->getJson('/api/v1/admin/orders?status='.$order->status)
        ->assertOk()
        ->assertJsonFragment(['id' => $order->id]);

    $this->getJson('/api/v1/admin/orders?q='.$order->order_number)
        ->assertOk()
        ->assertJsonFragment(['order_number' => $order->order_number]);
});
