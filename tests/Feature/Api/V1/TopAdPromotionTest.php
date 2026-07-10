<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can fetch top ad pricing options', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/top-ad-pricing')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'options' => [
                    ['duration_days', 'price', 'rank_weight', 'label'],
                ],
                'badge_label',
                'stack_weight_bonus',
            ],
        ])
        ->assertJsonCount(4, 'data.options');
});

test('wallet purchase applies stacked top boost to product', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->where('is_draft', false)->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
        'duration_days' => 7,
        'payment_method' => 'wallet_balance',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed');

    $product->refresh();
    expect($product->top_boost_active)->toBeTrue();
    expect((int) $product->top_boost_stack_count)->toBe(1);
    expect((int) $product->top_boost_weight)->toBe(100);
    expect($product->top_boost_expires_at)->not->toBeNull();

    $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
        'duration_days' => 14,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $product->refresh();
    expect((int) $product->top_boost_stack_count)->toBe(2);
    expect((int) $product->top_boost_weight)->toBeGreaterThan(100);
});

test('recommended sort ranks higher top ad stack above peer', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $peer = User::query()->where('email', 'vendor2@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $timestamp = now()->subHours(2);

    $boosted = publishedProduct_in_TopAdPromotion($vendor, $category, 'top-ad-rank-boosted');
    $regular = publishedProduct_in_TopAdPromotion($peer, $category, 'top-ad-rank-regular');

    Product::query()->whereIn('id', [$boosted->id, $regular->id])->update([
        'created_at' => $timestamp,
        'is_promoted' => false,
    ]);

    $vendor->update(['wallet_balance' => 50000]);
    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$boosted->id}/purchase-top-ad", [
        'duration_days' => 30,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $this->postJson("/api/v1/vendor/products/{$boosted->id}/purchase-top-ad", [
        'duration_days' => 7,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    $ids = collect($response->json('data.data'))->pluck('id');
    expect($ids->contains($boosted->id))->toBeTrue();
    expect($ids->contains($regular->id))->toBeTrue();
    expect($ids->search($boosted->id) < $ids->search($regular->id))->toBeTrue();
});

test('top ad transaction metadata is tagged', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->where('is_draft', false)->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
        'duration_days' => 7,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $tx = PromotionTransaction::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
    expect($tx->metadata['kind'] ?? null)->toBe('top_ad');
    expect((int) ($tx->metadata['duration_days'] ?? 0))->toBe(7);
});

function publishedProduct_in_TopAdPromotion(User $vendor, Category $category, string $slug): Product
{
    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 5000,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
    $product->translations()->create([
        'locale' => 'en',
        'title' => ucfirst(str_replace('-', ' ', $slug)),
    ]);

    return $product;
}
