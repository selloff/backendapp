<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassEMobileStubsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_mobile_product_images_returns_seeded_images(): void
    {
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        $response = $this->getJson('/api/v1/products/product-images/'.$product->id)
            ->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonCount(1, 'data.images');

        $this->assertStringStartsWith('https://', $response->json('data.images.0.image_url'));
    }

    public function test_mobile_related_products_returns_same_category_items(): void
    {
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        $response = $this->getJson('/api/v1/products/related/'.$product->id.'/5')
            ->assertOk()
            ->assertJsonPath('status', '1');

        $related = $response->json('data');
        $this->assertIsArray($related);
        $this->assertGreaterThanOrEqual(1, count($related));
        $this->assertNotContains($product->id, array_column($related, 'id'));
    }

    public function test_vendor_can_post_listing_item_via_mobile_route(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/post-listing-item', [
            'title' => 'Mobile Listed Headphones',
            'price' => 19999,
            'stock' => 5,
            'description' => 'Posted from legacy mobile route.',
        ])
            ->assertCreated()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('data.title', 'Mobile Listed Headphones');

        $this->assertDatabaseHas('products', [
            'vendor_id' => $vendor->id,
            'price' => '19999.00',
        ]);
    }

    public function test_mobile_profile_update_and_delete_account(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/users/profile', [
            'first_name' => 'Mobile',
            'last_name' => 'Buyer',
            'phone_number' => '+2348000000000',
        ])
            ->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('data.user.first_name', 'Mobile');

        $this->postJson('/api/v1/users/delete-account', [
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('status', '1');

        $buyer->refresh();
        $this->assertTrue($buyer->is_disable);
        $this->assertFalse($buyer->is_enable_login);
    }

    public function test_mobile_follow_and_report_endpoints_persist_records(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/products/follow-seller', ['seller_id' => $vendor->id])
            ->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('message', 'Seller Followed');

        $this->assertDatabaseHas('followers', [
            'user_id' => $vendor->id,
            'follower_id' => $buyer->id,
        ]);

        $this->postJson('/api/v1/products/follow-seller', ['seller_id' => $vendor->id])
            ->assertOk()
            ->assertJsonPath('message', 'Seller Unfollowed');

        $this->postJson('/api/v1/products/report-seller', [
            'seller_id' => $vendor->id,
            'message' => 'Suspicious activity',
        ])->assertOk()->assertJsonPath('message', 'Seller has been reported.');

        $this->postJson('/api/v1/products/report-user', [
            'sender_id' => $vendor->id,
            'message' => 'Spam messages',
        ])->assertOk()->assertJsonPath('message', 'User has been reported.');

        $this->postJson('/api/v1/products/report-item', [
            'product_id' => $product->id,
            'message' => 'Counterfeit item',
        ])->assertOk()->assertJsonPath('message', 'Item has been reported.');

        $this->assertSame(3, DB::table('abuse_reports')->where('reporter_id', $buyer->id)->count());
    }

    public function test_mobile_category_slug_declutter_freebies_and_favourites_return_data(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        $categoryResponse = $this->getJson('/api/v1/products/paginated-by-category-slug?slug=smartphones')
            ->assertOk()
            ->assertJsonPath('status', '1');
        $this->assertNotEmpty($categoryResponse->json('data'));

        $declutterResponse = $this->getJson('/api/v1/products/paginated-by-declutter')
            ->assertOk()
            ->assertJsonPath('status', '1');
        $this->assertNotEmpty($declutterResponse->json('data'));

        $freebiesResponse = $this->getJson('/api/v1/products/paginated-by-freebies')
            ->assertOk()
            ->assertJsonPath('status', '1');
        $this->assertNotEmpty($freebiesResponse->json('data'));

        Sanctum::actingAs($buyer);

        $favouritesResponse = $this->getJson('/api/v1/products/paginated-by-fovourite-listings')
            ->assertOk()
            ->assertJsonPath('status', '1');
        $this->assertNotEmpty($favouritesResponse->json('data'));
    }
}
