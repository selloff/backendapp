<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('dashboard latest and pending product lists do not overlap', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $approved = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'dashboard-approved-product',
        'sku' => 'DASH-APPROVED-1',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 50000,
        'currency_code' => 'NGN',
        'stock' => 1,
    ]);

    $awaiting = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'dashboard-pending-product',
        'sku' => 'DASH-PENDING-1',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'pending',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => false,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 40000,
        'currency_code' => 'NGN',
        'stock' => 1,
    ]);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

    $latestIds = collect($response->json('data.latest_products'))->pluck('id');
    $pendingIds = collect($response->json('data.latest_pending_products'))->pluck('id');

    expect($latestIds->contains($approved->id))->toBeTrue();
    expect($pendingIds->contains($awaiting->id))->toBeTrue();
    expect($latestIds->contains($awaiting->id))->toBeFalse();
    expect($pendingIds->contains($approved->id))->toBeFalse();
    expect($latestIds->first())->toBe($approved->id);
});

test('repair command marks published products verified', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $broken = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'broken-moderation-product',
        'sku' => 'DASH-BROKEN-1',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => false,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 30000,
        'currency_code' => 'NGN',
        'stock' => 1,
    ]);

    $this->artisan('selloff:repair-product-moderation-flags')->assertSuccessful();

    $broken->refresh();
    expect($broken->is_verified)->toBeTrue();
});

test('repair command restores published status when is draft is false', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $broken = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'cartier-sunglasses-repair-test',
        'sku' => 'REPAIR-MISDRAFT-1',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'status' => 'draft',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'price' => 120000,
        'currency_code' => 'NGN',
        'stock' => 10,
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonMissing(['sku' => 'REPAIR-MISDRAFT-1']);

    $this->artisan('selloff:repair-product-moderation-flags')->assertSuccessful();

    $broken->refresh();
    expect($broken->status)->toBe('published');

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'REPAIR-MISDRAFT-1']);
});
