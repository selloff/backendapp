<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor items for sale only includes published visible products', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonMissing(['data' => [['sku' => 'DEMO-PENDING-1']]])
        ->assertJsonMissing(['data' => [['sku' => 'DEMO-FREEBIE-1']]]);

    $this->getJson('/api/v1/vendor/products?st=pending')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PENDING-1']);
});

test('vendor pending list matches legacy status filter', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    Product::query()->create([
        'vendor_id' => $vendor->id,
        'sku' => 'PARITY-HIDDEN-PENDING',
        'slug' => 'parity-hidden-pending',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'hidden',
        'visibility' => 'hidden',
        'is_active' => false,
        'is_verified' => false,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 1000,
        'currency_code' => 'NGN',
        'stock' => 1,
    ]);

    Product::query()->create([
        'vendor_id' => $vendor->id,
        'sku' => 'PARITY-UNVERIFIED-PUBLISHED',
        'slug' => 'parity-unverified-published',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => false,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 1000,
        'currency_code' => 'NGN',
        'stock' => 1,
    ]);

    Sanctum::actingAs($vendor);

    $pendingSkus = collect($this->getJson('/api/v1/vendor/products?st=pending')->json('data.data'))
        ->pluck('sku')
        ->all();

    expect($pendingSkus)->toContain('DEMO-PENDING-1');
    expect($pendingSkus)->not->toContain('PARITY-HIDDEN-PENDING');
    expect($pendingSkus)->not->toContain('PARITY-UNVERIFIED-PUBLISHED');

    $activeSkus = collect($this->getJson('/api/v1/vendor/products')->json('data.data'))
        ->pluck('sku')
        ->all();

    expect($activeSkus)->toContain('PARITY-UNVERIFIED-PUBLISHED');
    expect($activeSkus)->not->toContain('DEMO-PENDING-1');
    expect($activeSkus)->not->toContain('PARITY-HIDDEN-PENDING');
});

test('affiliate product appears in items for sale when published', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    Product::query()->create([
        'vendor_id' => $vendor->id,
        'sku' => 'PARITY-AFFILIATE-ACTIVE',
        'slug' => 'parity-affiliate-active',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => false,
        'is_affiliate' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 1000,
        'currency_code' => 'NGN',
        'stock' => 5,
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'PARITY-AFFILIATE-ACTIVE', 'is_affiliate' => true]);
});

test('legacy numeric status and visibility still match items for sale', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    Product::query()->create([
        'vendor_id' => $vendor->id,
        'sku' => 'PARITY-LEGACY-STATUS-1',
        'slug' => 'parity-legacy-status-1',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => '1',
        'visibility' => '1',
        'is_active' => true,
        'is_affiliate' => true,
        'is_commission_set' => true,
        'commission_rate' => 8,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 120000,
        'currency_code' => 'NGN',
        'stock' => 10,
    ]);

    ProductTranslation::query()->create([
        'product_id' => Product::query()->where('sku', 'PARITY-LEGACY-STATUS-1')->value('id'),
        'locale' => 'en',
        'title' => 'Top Cartier sunglasses (Unisex) Foreign used',
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonFragment([
            'sku' => 'PARITY-LEGACY-STATUS-1',
            'is_affiliate' => true,
        ]);
});
