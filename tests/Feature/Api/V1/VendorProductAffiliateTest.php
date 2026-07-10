<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can toggle product affiliate when program uses selected products', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $product = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    expect((bool) $product->is_affiliate)->toBeFalse();

    $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_affiliate', true);

    $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_affiliate', false);
});

test('vendor cannot toggle product affiliate when program is not selected products mode', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    ReferralProfile::query()->where('user_id', $vendor->id)->update(['vendor_affiliate_status' => 1]);

    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
        ->assertStatus(422);
});

test('vendor affiliate program exposes product management flag', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/affiliate')
        ->assertOk()
        ->assertJsonPath('data.can_manage_product_affiliate', true)
        ->assertJsonPath('data.vendor_affiliate_status', 2);
});

test('vendor can save affiliate status setting', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->putJson('/api/v1/vendor/affiliate/settings', [
        'vendor_affiliate_status' => 2,
        'affiliate_commission_rate' => 6,
        'affiliate_discount_rate' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.vendor_affiliate_status', 2)
        ->assertJsonPath('data.affiliate_commission_rate', '6.00');
});

test('vendor product list includes is affiliate field', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $phone = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $phone->update(['is_affiliate' => true]);

    $this->getJson("/api/v1/vendor/products/{$phone->id}")
        ->assertOk()
        ->assertJsonPath('data.is_affiliate', true);
});

test('toggling affiliate does not remove product from items for sale', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_affiliate', true);

    $skus = collect($this->getJson('/api/v1/vendor/products')->json('data.data'))
        ->pluck('sku')
        ->all();

    expect($skus)->toContain('DEMO-AUDIO-1');
});
