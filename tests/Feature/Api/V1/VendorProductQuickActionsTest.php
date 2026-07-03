<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Media\Models\ProductImage;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductQuickActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_mark_active_physical_item_as_sold_and_hidden(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
        $this->assertTrue($product->is_sold);
        $this->assertSame('hidden', $product->visibility);
        $this->assertFalse($product->is_active);
        $this->assertSame(0, $product->stock);
    }

    public function test_mark_sold_rejects_deleted_item(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
            'sku' => 'QUICK-SOLD-DELETED',
            'slug' => 'quick-sold-deleted',
            'is_deleted' => true,
        ]);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/mark-sold")->assertStatus(422);
    }

    public function test_mark_sold_rejects_already_sold_draft_and_pending_items(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $sold = $this->createVendorActiveProduct($vendor, [
            'sku' => 'QUICK-SOLD-ALREADY',
            'slug' => 'quick-sold-already',
            'is_sold' => true,
        ]);
        $this->postJson("/api/v1/vendor/products/{$sold->id}/mark-sold")->assertStatus(422);

        $draft = $this->createVendorActiveProduct($vendor, [
            'sku' => 'QUICK-SOLD-DRAFT',
            'slug' => 'quick-sold-draft',
            'status' => 'draft',
            'is_draft' => true,
            'visibility' => 'hidden',
            'is_active' => false,
        ]);
        $this->postJson("/api/v1/vendor/products/{$draft->id}/mark-sold")->assertStatus(422);

        $pending = $this->createVendorActiveProduct($vendor, [
            'sku' => 'QUICK-SOLD-PENDING',
            'slug' => 'quick-sold-pending',
            'status' => 'pending',
            'is_verified' => false,
        ]);
        $this->postJson("/api/v1/vendor/products/{$pending->id}/mark-sold")->assertStatus(422);
    }

    public function test_mark_sold_rejects_non_owner(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
            'sku' => 'QUICK-SOLD-OWNER',
            'slug' => 'quick-sold-owner',
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/vendor/products/{$product->id}/mark-sold")->assertForbidden();
    }

    public function test_vendor_can_update_price_only_without_changing_title(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    public function test_vendor_can_replace_images_only(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    public function test_vendor_can_set_main_image_by_array_order(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    public function test_price_update_marks_listing_as_edited_when_approve_after_editing_enabled(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'approve_after_editing' => 1,
        ]);

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    public function test_image_update_marks_listing_as_edited_and_keeps_it_on_vendor_items_for_sale(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'approve_after_editing' => 2,
        ]);

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    public function test_published_product_cannot_remove_all_images(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = $this->createVendorActiveProduct($vendor, [
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
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createVendorActiveProduct(User $vendor, array $overrides = []): Product
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
}
