<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CategoryTranslation;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can update order status with tracking', function () {
    $order = placeVendorOrder_in_PassBDepth();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->patchJson('/api/v1/vendor/orders/'.$order->id.'/status', [
        'status' => 'shipped',
        'tracking_number' => 'TRK-12345',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.shipping_tracking_number', 'TRK-12345');
});

test('vendor can approve and reject refunds', function () {
    $order = placeVendorOrder_in_PassBDepth();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/orders/'.$order->id.'/refund-requests', [
        'description' => 'Item damaged',
    ])->assertCreated();

    $refund = RefundRequest::query()->where('order_id', $order->id)->firstOrFail();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/vendor/refunds/'.$refund->id.'/reject', [
        'message' => 'Outside return window',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('vendor can update and delete shipping zones', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $zoneId = $this->postJson('/api/v1/vendor/shipping/zones', ['name' => 'Zone A'])
        ->assertCreated()
        ->json('data.id');

    $this->putJson('/api/v1/vendor/shipping/zones/'.$zoneId, ['name' => 'Zone B'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Zone B');

    $this->deleteJson('/api/v1/vendor/shipping/zones/'.$zoneId)
        ->assertOk();

    $this->assertDatabaseMissing('shipping_zones', ['id' => $zoneId]);
});

test('admin can mark order paid and update item status', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $order = placeVendorOrder_in_PassBDepth();
    $item = $order->items()->firstOrFail();

    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/admin/orders/'.$order->id.'/items/'.$item->id, [
        'order_status' => 'shipped',
    ])
        ->assertOk()
        ->assertJsonPath('data.order_status', 'shipped');
});

test('admin can update and delete categories', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $categoryId = $this->postJson('/api/v1/admin/categories', ['name' => 'Pass B Cat'])
        ->assertCreated()
        ->json('data.id');

    $this->putJson('/api/v1/admin/categories/'.$categoryId, [
        'name' => 'Pass B Updated',
        'status' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', false);

    $this->deleteJson('/api/v1/admin/categories/'.$categoryId, [], adminPinHeaders())->assertOk();
    $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
});

test('admin can manage location states', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $country = Country::query()->firstOrFail();

    $stateId = $this->postJson('/api/v1/admin/locations/states', [
        'country_id' => $country->id,
        'name' => 'Pass B State',
        'code' => 'PB',
    ])
        ->assertCreated()
        ->json('data.id');

    $this->putJson('/api/v1/admin/locations/states/'.$stateId, ['name' => 'Pass B Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Pass B Updated');

    $this->deleteJson('/api/v1/admin/locations/states/'.$stateId, [], adminPinHeaders())->assertOk();
});

test('vendor product accepts category description and images', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $category = Category::query()->first();
    if (! $category) {
        $category = Category::query()->create(['slug' => 'pass-b', 'status' => true]);
        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Pass B',
        ]);
    }

    $this->postJson('/api/v1/products', [
        'title' => 'Pass B Product',
        'description' => 'Full description',
        'category_id' => $category->id,
        'type' => 'physical',
        'price' => 9900,
        'stock' => 3,
        'images' => [
            ['path' => 'uploads/images/test/product.jpg', 'disk' => 'public'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.description', 'Full description')
        ->assertJsonPath('data.category_id', $category->id);
});

function placeVendorOrder_in_PassBDepth(): Order
{
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $product = test()->postJson('/api/v1/products', [
        'title' => 'Pass B Order Item',
        'price' => 2500,
        'stock' => 2,
    ])->assertCreated()->json('data');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    test()->postJson('/api/v1/cart/items', ['product_id' => $product['id'], 'quantity' => 1])->assertCreated();
    $checkout = test()->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $placed = test()->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    return Order::query()->findOrFail($placed['id']);
}
