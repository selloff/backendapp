<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Review\Models\ProductReview;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('demo seed produces multi vendor marketplace', function () {
    expect(User::role('vendor')->count())->toBeGreaterThanOrEqual(6);
    expect(Product::query()->where('status', 'published')->count())->toBeGreaterThanOrEqual(45);
    expect(Order::query()->count())->toBeGreaterThanOrEqual(2);
    expect(VendorEarning::query()->count())->toBeGreaterThanOrEqual(2);
    expect(PayoutRequest::query()->where('status', 'pending')->count())->toBeGreaterThanOrEqual(1);
    expect(ProductReview::query()->where('is_approved', true)->count())->toBeGreaterThanOrEqual(7);

    $this->getJson('/api/v1/products?per_page=50')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['data']]);

    $vendorSlugs = collect($this->getJson('/api/v1/products?per_page=50')->json('data.data'))
        ->pluck('vendor.shop_name')
        ->filter()
        ->unique()
        ->values();

    expect($vendorSlugs->count())->toBeGreaterThanOrEqual(4);
});

test('demo buyer has order history and wallet checkout still works', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $orders = $this->getJson('/api/v1/orders')->assertOk()->json('data.data');
    expect(count($orders))->toBeGreaterThanOrEqual(2);

    $this->getJson('/api/v1/orders/'.$orders[0]['id'])
        ->assertOk()
        ->assertJsonPath('data.buyer.email', 'buyer@selloff.test');

    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');

    $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->assertJsonPath('data.payment_method', 'wallet_balance');
});

test('demo vendors have earnings and admin sees orders', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $summary = $this->getJson('/api/v1/vendor/earnings')->assertOk()->json('data');
    expect($summary['total_earned'])->toBeGreaterThan(0);
    expect($summary['available_balance'])->toBeGreaterThan(0);

    $vendorOrders = $this->getJson('/api/v1/vendor/orders')->assertOk()->json('data.data');
    expect($vendorOrders)->not->toBeEmpty();

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/orders')->assertOk();
    $this->getJson('/api/v1/admin/payouts?status=pending')->assertOk();
    $this->getJson('/api/v1/admin/products?pending_only=1')->assertOk();
});

test('product detail includes options and related products', function () {
    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->getJson('/api/v1/products/'.$phone->slug)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sku', 'DEMO-PHONE-1')
        ->assertJsonStructure([
            'data' => [
                'options' => [['id', 'name', 'values']],
                'variants' => [['id', 'option_value_ids', 'stock']],
                'category_breadcrumb',
            ],
        ]);

    expect($this->getJson('/api/v1/products/'.$phone->slug)->json('data.options'))->not->toBeEmpty();
    expect($this->getJson('/api/v1/products/'.$phone->slug)->json('data.variants'))->not->toBeEmpty();

    $related = $this->getJson('/api/v1/products/'.$phone->id.'/related?limit=5')
        ->assertOk()
        ->json('data');

    expect($related)->toBeArray();
    expect(count($related))->toBeGreaterThanOrEqual(1);
    expect(array_column($related, 'id'))->not->toContain($phone->id);
});

test('add to cart with variant succeeds', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $variantId = $phone->variants()->where('is_default', true)->value('id');
    expect($variantId)->not->toBeNull();

    $this->postJson('/api/v1/cart/items', [
        'product_id' => $phone->id,
        'quantity' => 1,
        'variant_id' => $variantId,
        'product_options_summary' => 'Color: Black, Storage: 128GB',
    ])->assertCreated();
});
