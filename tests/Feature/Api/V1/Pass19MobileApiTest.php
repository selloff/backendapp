<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('paginated by category slug returns products for slug', function () {
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

    expect(Product::query()->where('category_id', $category->id)->count())->toBeGreaterThan(0);

    $response = $this->getJson('/api/v1/products/paginated-by-category-slug?slug=smartphones')
        ->assertOk()
        ->assertJsonPath('status', '1');

    expect($response->json('data'))->not->toBeEmpty();
});

test('paginated by declutter returns discounted products', function () {
    expect(Product::query()
        ->whereNotNull('price_discounted')
        ->whereColumn('price_discounted', '<', 'price')
        ->count())->toBeGreaterThan(0);

    $response = $this->getJson('/api/v1/products/paginated-by-declutter')
        ->assertOk()
        ->assertJsonPath('status', '1');

    expect($response->json('data'))->not->toBeEmpty();
});

test('paginated by freebies returns zero price products', function () {
    expect(Product::query()->where('price', 0)->count())->toBeGreaterThan(0);

    $response = $this->getJson('/api/v1/products/paginated-by-freebies')
        ->assertOk()
        ->assertJsonPath('status', '1');

    expect($response->json('data'))->not->toBeEmpty();
});

test('paginated by favourite listings returns wishlist products', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    expect(Wishlist::query()->where('user_id', $buyer->id)->count())->toBeGreaterThan(0);

    $response = $this->getJson('/api/v1/products/paginated-by-fovourite-listings')
        ->assertOk()
        ->assertJsonPath('status', '1');

    expect($response->json('data'))->not->toBeEmpty();
});

test('update product category requires admin and bulk reassigns', function () {
    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $accessories = Category::query()->where('slug', 'accessories')->firstOrFail();

    $product = Product::query()
        ->where('sku', 'DEMO-FREEBIE-1')
        ->where('category_id', $phones->id)
        ->firstOrFail();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/update-product-category', [
        'old_category' => $phones->id,
        'new_category' => $accessories->id,
    ])->assertForbidden();

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/update-product-category', [
        'old_category' => $phones->id,
        'new_category' => $accessories->id,
    ])
        ->assertOk()
        ->assertJsonPath('status', '1')
        ->assertJsonPath('data.updated_count', fn ($count) => $count >= 1);

    $product->refresh();
    expect($product->category_id)->toBe($accessories->id);

    // Restore for other tests
    $product->update(['category_id' => $phones->id]);
});
