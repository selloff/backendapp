<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\DigitalFile;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Shipping\Models\DeliveryTimeOption;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('product show includes platform safety tips', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $tips = $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->json('data.safety_tips');

    expect($tips)->toBeArray();
    expect($tips)->toHaveCount(5);
    $this->assertStringContainsString('Escrow', (string) $tips[1]);
    $this->assertStringContainsString('strong password', (string) $tips[3]);
});

test('product show uses updated platform safety tips', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'product_safety_tips' => ['Custom tip one', 'Custom tip two'],
    ], 'product_listing');

    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.safety_tips', ['Custom tip one', 'Custom tip two']);
});

test('shipping estimate requires location for guests without params', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->getJson("/api/v1/products/{$product->slug}/shipping-estimate")
        ->assertOk()
        ->assertJsonPath('data.status', 'location_required');
});

test('shipping estimate returns zone label for authenticated buyer', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->getJson("/api/v1/products/{$product->slug}/shipping-estimate")
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.label', '3-5 days');
});

test('product show includes viewer digital purchase for owned digital product', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $product->update(['type' => 'digital', 'is_free_product' => false]);

    DigitalFile::query()->firstOrCreate(
        ['product_id' => $product->id, 'file_name' => 'uploads/demo/digital-guide.pdf'],
        ['storage' => 'public'],
    );

    $sale = DigitalSale::query()->where('buyer_id', $buyer->id)->where('product_id', $product->id)->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.viewer_digital_purchase.id', $sale->id)
        ->assertJsonPath('data.viewer_digital_purchase.purchase_code', $sale->purchase_code);
});

test('shipping estimate includes delivery time label when set', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $option = DeliveryTimeOption::query()
        ->where('seller_id', $vendor->id)
        ->firstOrFail();

    $product->update(['delivery_time_option_id' => $option->id]);

    Sanctum::actingAs($buyer);

    $this->getJson("/api/v1/products/{$product->slug}/shipping-estimate")
        ->assertOk()
        ->assertJsonPath('data.delivery_time_label', $option->label);
});
