<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('mobile product images returns seeded images', function () {
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $response = $this->getJson('/api/v1/products/product-images/'.$product->id)
        ->assertOk()
        ->assertJsonPath('status', '1')
        ->assertJsonCount(1, 'data.images');

    expect($response->json('data.images.0.image_url'))->toStartWith('https://');
});

test('mobile related products returns same category items', function () {
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $response = $this->getJson('/api/v1/products/related/'.$product->id.'/5')
        ->assertOk()
        ->assertJsonPath('status', '1');

    $related = $response->json('data');
    expect($related)->toBeArray();
    expect(count($related))->toBeGreaterThanOrEqual(1);
    expect(array_column($related, 'id'))->not->toContain($product->id);
});

test('vendor can post listing item via mobile route', function () {
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
});

test('mobile profile update and delete account', function () {
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
    expect($buyer->is_disable)->toBeTrue();
    expect($buyer->is_enable_login)->toBeFalse();
});

test('mobile follow and report endpoints persist records', function () {
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

    expect(DB::table('abuse_reports')->where('reporter_id', $buyer->id)->count())->toBe(3);
});

test('mobile category slug declutter freebies and favourites return data', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $categoryResponse = $this->getJson('/api/v1/products/paginated-by-category-slug?slug=smartphones')
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($categoryResponse->json('data'))->not->toBeEmpty();

    $declutterResponse = $this->getJson('/api/v1/products/paginated-by-declutter')
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($declutterResponse->json('data'))->not->toBeEmpty();

    $freebiesResponse = $this->getJson('/api/v1/products/paginated-by-freebies')
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($freebiesResponse->json('data'))->not->toBeEmpty();

    Sanctum::actingAs($buyer);

    $favouritesResponse = $this->getJson('/api/v1/products/paginated-by-fovourite-listings')
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($favouritesResponse->json('data'))->not->toBeEmpty();
});
