<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Media\Models\ProductImage;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can mark active physical item as sold and hidden', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-1',
        'slug' => 'quick-sold-1',
        'stock' => 12,
        'type' => 'physical',
    ]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/mark-sold")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.is_sold', true)
        ->assertJsonPath('data.visibility', 'hidden')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.stock', 0);

    $product->refresh();
    expect($product->is_sold)->toBeTrue();
    expect($product->visibility)->toBe('hidden');
    expect($product->is_active)->toBeFalse();
    expect($product->stock)->toBe(0);
});

test('mark sold rejects deleted item', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-DELETED',
        'slug' => 'quick-sold-deleted',
        'is_deleted' => true,
    ]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/mark-sold")->assertStatus(422);
});

test('mark sold rejects already sold draft and pending items', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $sold = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-ALREADY',
        'slug' => 'quick-sold-already',
        'is_sold' => true,
    ]);
    $this->postJson("/api/v1/vendor/products/{$sold->id}/mark-sold")->assertStatus(422);

    $draft = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-DRAFT',
        'slug' => 'quick-sold-draft',
        'status' => 'draft',
        'is_draft' => true,
        'visibility' => 'hidden',
        'is_active' => false,
    ]);
    $this->postJson("/api/v1/vendor/products/{$draft->id}/mark-sold")->assertStatus(422);

    $pending = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-PENDING',
        'slug' => 'quick-sold-pending',
        'status' => 'pending',
        'is_verified' => false,
    ]);
    $this->postJson("/api/v1/vendor/products/{$pending->id}/mark-sold")->assertStatus(422);
});

test('mark sold rejects non owner', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-SOLD-OWNER',
        'slug' => 'quick-sold-owner',
    ]);

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/vendor/products/{$product->id}/mark-sold")->assertForbidden();
});

test('vendor can update price only without changing title', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PRICE-1',
        'slug' => 'quick-price-1',
        'price' => 10000,
        'title' => 'Original listing title',
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'price' => 15000,
        'price_discounted' => 12000,
    ])
        ->assertOk()
        ->assertJsonPath('data.price', '15000.00')
        ->assertJsonPath('data.price_discounted', '12000.00')
        ->assertJsonPath('data.title', 'Original listing title');
});

test('vendor can replace images only', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PHOTOS-1',
        'slug' => 'quick-photos-1',
        'title' => 'Photos listing',
    ]);

    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'products/old-image.jpg',
        'disk' => 'public',
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'images' => [
            ['path' => 'products/new-image-a.jpg', 'disk' => 'public'],
            ['path' => 'products/new-image-b.jpg', 'disk' => 'public'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Photos listing')
        ->assertJsonCount(2, 'data.images');

    $this->assertDatabaseMissing('product_images', [
        'product_id' => $product->id,
        'path' => 'products/old-image.jpg',
    ]);
    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'path' => 'products/new-image-a.jpg',
    ]);
});

test('vendor can set main image by array order', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PHOTOS-MAIN',
        'slug' => 'quick-photos-main',
        'title' => 'Main image listing',
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'images' => [
            ['path' => 'products/image-a.jpg', 'disk' => 'public'],
            ['path' => 'products/image-b.jpg', 'disk' => 'public'],
        ],
    ])->assertOk();

    $this->putJson("/api/v1/products/{$product->id}", [
        'images' => [
            ['path' => 'products/image-b.jpg', 'disk' => 'public'],
            ['path' => 'products/image-a.jpg', 'disk' => 'public'],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'path' => 'products/image-b.jpg',
        'is_primary' => true,
    ]);
    $this->assertDatabaseHas('product_images', [
        'product_id' => $product->id,
        'path' => 'products/image-a.jpg',
        'is_primary' => false,
    ]);
});

test('price update marks listing as edited when approve after editing enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 1,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PRICE-EDITED',
        'slug' => 'quick-price-edited',
        'title' => 'Edited price listing',
        'is_edited' => false,
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'price' => 18000,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_edited', true)
        ->assertJsonPath('data.price', '18000.00');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'is_edited' => true,
        'status' => 'published',
    ]);
});

test('image update marks listing as edited and keeps it on vendor items for sale', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'approve_after_editing' => 2,
    ]);

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PHOTO-EDITED',
        'slug' => 'quick-photo-edited',
        'title' => 'Edited photo listing',
        'is_edited' => false,
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'images' => [
            ['path' => 'products/edited-photo.jpg', 'disk' => 'public'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.is_edited', true)
        ->assertJsonPath('data.status', 'pending');

    $this->getJson('/api/v1/vendor/products')
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id, 'is_edited' => true]);
});

test('published product cannot remove all images', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = createVendorActiveProduct_in_VendorProductQuickActions($vendor, [
        'sku' => 'QUICK-PHOTOS-EMPTY',
        'slug' => 'quick-photos-empty',
    ]);

    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => 'products/only-image.jpg',
        'disk' => 'public',
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    Sanctum::actingAs($vendor);

    $this->putJson("/api/v1/products/{$product->id}", [
        'images' => [],
    ])->assertStatus(422)->assertJsonValidationErrors(['images']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function createVendorActiveProduct_in_VendorProductQuickActions(User $vendor, array $overrides = []): Product
{
    $title = (string) ($overrides['title'] ?? 'Quick action test item');
    unset($overrides['title']);

    /** @var Product $product */
    $product = Product::query()->create(array_merge([
        'vendor_id' => $vendor->id,
        'category_id' => Product::query()->value('category_id'),
        'slug' => 'quick-action-'.uniqid(),
        'sku' => 'QUICK-'.uniqid(),
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'is_sold' => false,
        'price' => 25000,
        'currency_code' => 'NGN',
        'stock' => 5,
    ], $overrides));

    ProductTranslation::query()->create([
        'product_id' => $product->id,
        'locale' => 'en',
        'title' => $title,
    ]);

    return $product;
}
