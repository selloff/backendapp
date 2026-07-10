<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Content\Models\BlogCategory;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can moderate products', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/products?pending_only=1')
        ->assertOk()
        ->assertJsonPath('success', true);

    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();

    $this->postJson('/api/v1/admin/products/'.$pending->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.is_verified', true)
        ->assertJsonPath('data.status', 'published');
});

test('admin can refund order and vendor can request payout', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    Sanctum::actingAs($buyer);
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $order = $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    expect(VendorEarning::query()->where('seller_id', $vendor->id)->count())->toBeGreaterThanOrEqual(1);

    Sanctum::actingAs($vendor);
    $summary = $this->getJson('/api/v1/vendor/earnings')->assertOk()->json('data');
    expect($summary['available_balance'])->toBeGreaterThan(0);

    $payout = $this->postJson('/api/v1/vendor/payouts', ['amount' => 1000])
        ->assertCreated()
        ->json('data');

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/admin/payouts/'.$payout['id'].'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $refund = $this->postJson('/api/v1/admin/orders/'.$order['id'].'/refunds', [
        'description' => 'Buyer requested refund',
    ])->assertCreated()->json('data');

    $orderItemId = $order['items'][0]['id'] ?? null;
    expect($orderItemId)->not->toBeNull();

    $this->postJson('/api/v1/admin/refunds/'.$refund['id'].'/approve', [
        'message' => 'Refund processed',
    ])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.is_completed', true);

    expect(\App\Modules\Selloff\Order\Models\OrderItem::query()->find($orderItemId)?->order_status)->toBe('refund_approved');
    expect((bool) RefundRequest::query()->find($refund['id'])?->is_completed)->toBeTrue();
    expect((bool) VendorEarning::query()
        ->where('order_id', $order['id'])
        ->where('seller_id', $vendor->id)
        ->value('is_refunded'))->toBeTrue();
    expect(PayoutRequest::query()->find($payout['id'])?->status)->toBe('approved');
});

test('admin can manage cms support and membership endpoints', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $category = BlogCategory::query()->firstOrFail();

    $this->postJson('/api/v1/admin/cms/blog/posts', [
        'title' => 'Selloff Launch',
        'content' => 'We are live.',
        'is_published' => true,
        'category_id' => $category->id,
    ])->assertCreated();

    $this->getJson('/api/v1/admin/cms/blog/posts')->assertOk();

    $this->postJson('/api/v1/admin/membership-plans', [
        'title' => 'Vendor Pro',
        'price' => 9999,
        'duration_days' => 30,
    ])->assertCreated();

    $this->getJson('/api/v1/admin/locations/countries')->assertOk();
});
