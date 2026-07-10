<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Models\ProductReview;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('guest can list approved product reviews', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    ProductReview::query()->updateOrCreate(
        ['product_id' => $product->id, 'user_id' => $buyer->id],
        ['rating' => 4, 'review' => 'Solid phone for the price.', 'is_approved' => true],
    );

    $this->getJson("/api/v1/products/{$product->id}/reviews")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['review' => 'Solid phone for the price.', 'rating' => 4]);
});

test('authenticated buyer can submit product review', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/products/{$product->id}/reviews", [
        'rating' => 5,
        'review' => 'Excellent classified listing experience.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.review', 'Excellent classified listing experience.');

    $this->assertDatabaseHas('product_reviews', [
        'product_id' => $product->id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'review' => 'Excellent classified listing experience.',
        'is_approved' => true,
    ]);
});
