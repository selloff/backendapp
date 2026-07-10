<?php

use App\Modules\Selloff\Catalog\Models\Product;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'show_vendor_contact_info_guests' => true,
    ], 'general');
});

test('view contact reveals vendor phone and gtm event', function () {
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $response = $this->postJson("/api/v1/products/{$product->slug}/view-contact")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.phone_number', '+2348012345678');

    $events = $response->json('data.gtm_events');
    expect($events)->toBeArray();
    expect($events[0]['event'])->toBe('view_contact');
    expect($events[0]['eventData']['item_id'])->toBe((string) $product->id);
});

test('click to call returns gtm event', function () {
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $response = $this->postJson("/api/v1/products/{$product->slug}/click-to-call")
        ->assertOk()
        ->assertJsonPath('success', true);

    $events = $response->json('data.gtm_events');
    expect($events)->toBeArray();
    expect($events[0]['event'])->toBe('click_to_call');
});

test('view contact rejects marketplace listings', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->postJson("/api/v1/products/{$product->slug}/view-contact")
        ->assertStatus(422);
});

test('product show includes vendor contact flags without phone', function () {
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.show_phone_contact', true)
        ->assertJsonPath('data.vendor.email', 'vendor@selloff.test')
        ->assertJsonMissingPath('data.vendor.phone_number');
});
