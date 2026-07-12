<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('marketplace preferences exposes wave4 storage keys', function () {
    $this->getJson('/api/v1/preferences/marketplace')
        ->assertOk()
        ->assertJsonPath('data.storage_keys.estimated_delivery_location', 'mds_estimated_delivery_location')
        ->assertJsonPath('data.storage_keys.cart_has_changed', 'mds_cart_has_changed')
        ->assertJsonPath('data.storage_keys.control_panel_lang', 'mds_control_panel_lang');
});

test('user can persist selected currency code on profile', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $usd = Currency::query()->where('code', 'USD')->firstOrFail();

    app(PlatformSettingsService::class)->upsertMany([
        'currency_converter' => true,
        'default_currency' => 'NGN',
    ], 'payment');

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'selected_currency_code' => $usd->code,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.selected_currency_code', 'USD');

    expect($buyer->fresh()->selected_currency_code)->toBe('USD');
});

test('user can update phone number on profile', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['phone_number' => null]);

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'phone_number' => '08012345678',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.phone_number', '08012345678');

    expect($buyer->fresh()->phone_number)->toBe('08012345678');
});

test('cart item mutation clears applied shipping', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $country = Country::query()->where('code', 'NG')->firstOrFail();
    $state = State::query()->where('name', 'Lagos')->firstOrFail();
    $method = ShippingMethod::query()->where('name', 'Standard Delivery')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', ['product_id' => $audio->id, 'quantity' => 1])
        ->assertCreated();

    $this->postJson('/api/v1/cart/shipping', [
        'shipping_method_id' => $method->id,
        'country_id' => $country->id,
        'state_id' => $state->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.totals.shipping_cost', 1500);

    $this->postJson('/api/v1/cart/items', ['product_id' => $phone->id, 'quantity' => 1])
        ->assertCreated()
        ->assertJsonPath('data.totals.shipping_cost', 0);
});

test('user can update avatar cover and storage on profile', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->patchJson('/api/v1/auth/me', [
        'avatar' => 'uploads/profile/test-avatar.jpg',
        'storage_avatar' => 'public',
        'cover_path' => 'uploads/profile/test-cover.jpg',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.avatar', 'uploads/profile/test-avatar.jpg')
        ->assertJsonPath('data.cover_path', 'uploads/profile/test-cover.jpg');

    expect($vendor->fresh()->avatar)->toBe('uploads/profile/test-avatar.jpg')
        ->and($vendor->fresh()->storage_avatar)->toBe('public')
        ->and($vendor->fresh()->vendorProfile?->cover_path)->toBe('uploads/profile/test-cover.jpg');

    $this->patchJson('/api/v1/auth/me', [
        'cover_path' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.cover_path', null);

    expect($vendor->fresh()->vendorProfile?->cover_path)->toBeNull();
});
