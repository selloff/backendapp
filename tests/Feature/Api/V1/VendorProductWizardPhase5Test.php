<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductWizardPhase5Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_create_draft_with_tags_listing_type_and_images(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $category = Category::query()->firstOrFail();

        $response = $this->postJson('/api/v1/products', [
            'title' => 'Phase 5 Classified Listing',
            'description' => 'Full wizard draft product',
            'short_description' => 'Short blurb',
            'category_id' => $category->id,
            'type' => 'physical',
            'listing_type' => 'ordinary_listing',
            'price' => 0,
            'stock' => 0,
            'status' => 'draft',
            'tags' => ['phones', 'Samsung'],
            'images' => [
                ['path' => 'uploads/images/test/wizard-1.jpg', 'disk' => 'public'],
                ['path' => 'uploads/images/test/wizard-2.jpg', 'disk' => 'public'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.listing_type', 'ordinary_listing')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.tags.0', 'phones')
            ->assertJsonPath('data.tags.1', 'samsung');

        $productId = $response->json('data.id');

        $this->getJson("/api/v1/vendor/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.tags.0', 'phones')
            ->assertJsonCount(2, 'data.images');
    }

    public function test_vendor_can_publish_with_location_shipping_and_media(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $category = Category::query()->firstOrFail();
        $country = Country::query()->firstOrFail();
        $state = State::query()->where('country_id', $country->id)->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->firstOrFail();

        $productId = $this->postJson('/api/v1/products', [
            'title' => 'Phase 5 Marketplace Product',
            'category_id' => $category->id,
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'price' => 0,
            'stock' => 0,
            'status' => 'draft',
            'images' => [
                ['path' => 'uploads/images/test/wizard-market.jpg', 'disk' => 'public'],
            ],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/products/{$productId}", [
            'price' => 12500,
            'stock' => 4,
            'status' => 'published',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'address' => '12 Market Road',
            'zip_code' => '100001',
            'shipping_dimensions' => [
                'weight' => 1.5,
                'length' => 20,
                'width' => 15,
                'height' => 10,
            ],
            'video_path' => 'videos/demo-preview.mp4',
            'video_disk' => 'public',
            'audio_path' => 'audios/demo-preview.mp3',
            'audio_disk' => 'public',
            'tags' => ['electronics'],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.country_id', $country->id)
            ->assertJsonPath('data.address', '12 Market Road')
            ->assertJsonPath('data.shipping_dimensions.weight', 1.5)
            ->assertJsonPath('data.video_path', 'videos/demo-preview.mp4')
            ->assertJsonPath('data.tags.0', 'electronics');

        $product = Product::query()->findOrFail($productId);
        $this->assertSame('published', $product->status);
        $this->assertSame('12 Market Road', $product->address);
        $this->assertCount(1, $product->tags);
    }

    public function test_product_create_rejects_more_than_ten_images(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $images = [];
        for ($i = 1; $i <= 11; $i++) {
            $images[] = ['path' => "uploads/images/test/img-{$i}.jpg", 'disk' => 'public'];
        }

        $this->postJson('/api/v1/products', [
            'title' => 'Too many images',
            'price' => 1000,
            'stock' => 1,
            'images' => $images,
        ])->assertStatus(422);
    }

    public function test_updating_general_fields_does_not_downgrade_published_product_to_draft(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => Product::query()->value('category_id'),
            'slug' => 'published-general-save-test',
            'sku' => 'WIZARD-PUBLISHED-1',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_verified' => true,
            'is_draft' => false,
            'is_deleted' => false,
            'price' => 120000,
            'currency_code' => 'NGN',
            'stock' => 10,
        ]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'title' => 'Updated Cartier sunglasses title',
            'description' => 'Updated description',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'price' => 120000,
            'stock' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $product->refresh();
        $this->assertSame('published', $product->status);
        $this->assertFalse($product->is_draft);
    }
}
