<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Media\Models\ProductImage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('product resource exposes image variant urls', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    ProductImage::query()->where('product_id', $product->id)->delete();
    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => '202604/img_w960_phase8demo.webp',
        'disk' => 'aws_s3',
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    config([
        'filesystems.disks.s3.bucket' => 'selloff-prod',
        'filesystems.disks.s3.region' => 'eu-west-2',
        'filesystems.disks.s3.url' => null,
    ]);

    $image = $this->getJson('/api/v1/products/'.$product->slug)
        ->assertOk()
        ->json('data.images.0');

    expect($image['url'])->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w960_phase8demo.webp');
    expect($image['urls']['small'])->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_phase8demo.webp');
    expect($image['urls']['big'])->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w1600_phase8demo.webp');
});

test('cart returns item count and multi vendor seller summary', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $fashion = Product::query()->where('sku', 'DEMO-FASHION-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', ['product_id' => $phone->id, 'quantity' => 1])->assertCreated();
    $response = $this->postJson('/api/v1/cart/items', ['product_id' => $fashion->id, 'quantity' => 2])
        ->assertCreated();

    $response
        ->assertJsonPath('data.totals.item_count', 3)
        ->assertJsonPath('data.seller_count', 2)
        ->assertJsonPath('data.has_multiple_sellers', true)
        ->assertJsonCount(2, 'data.sellers');

    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.totals.item_count', 3)
        ->assertJsonPath('data.has_multiple_sellers', true);
});

test('cart item uses small variant image url', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    ProductImage::query()->where('product_id', $product->id)->delete();
    ProductImage::query()->create([
        'product_id' => $product->id,
        'path' => '202604/img_w960_cartthumb.webp',
        'disk' => 'aws_s3',
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    config([
        'filesystems.disks.s3.bucket' => 'selloff-prod',
        'filesystems.disks.s3.region' => 'eu-west-2',
        'filesystems.disks.s3.url' => null,
    ]);

    Sanctum::actingAs($buyer);

    $item = $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated()
        ->json('data.items.0');

    expect($item['product_image_url'])->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_cartthumb.webp');
    expect($item['seller']['slug'])->not->toBeNull();
});
