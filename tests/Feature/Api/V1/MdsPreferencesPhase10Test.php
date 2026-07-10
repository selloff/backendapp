<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('marketplace preferences exposes mds storage mapping', function () {
    $this->getJson('/api/v1/preferences/marketplace')
        ->assertOk()
        ->assertJsonPath('data.default_currency', 'NGN')
        ->assertJsonPath('data.storage_keys.selected_currency', 'mds_selected_currency')
        ->assertJsonPath('data.storage_keys.guest_wishlist', 'mds_guest_wishlist')
        ->assertJsonStructure([
            'data' => [
                'currency_converter_enabled',
                'session_note',
            ],
        ]);
});

test('guest wishlist preview returns active products', function () {
    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $this->getJson('/api/v1/wishlist/guest-preview?'.http_build_query([
        'product_ids' => [$phone->id, $audio->id, 999999],
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.product.id', $phone->id);
});

test('authenticated user can merge guest wishlist product ids', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/wishlist/merge-guest', [
        'product_ids' => [$phone->id, $audio->id, $phone->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.merged', 1)
        ->assertJsonPath('data.skipped', 1);

    expect(Wishlist::query()
        ->where('user_id', $buyer->id)
        ->whereIn('product_id', [$phone->id, $audio->id])
        ->count() === 2)->toBeTrue();

    $this->postJson('/api/v1/wishlist/merge-guest', [
        'product_ids' => [$phone->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.merged', 0)
        ->assertJsonPath('data.skipped', 1);
});
