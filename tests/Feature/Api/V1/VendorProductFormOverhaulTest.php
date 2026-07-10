<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\State;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('browse cities returns popular section', function () {
    $state = State::query()->where('status', true)->orderBy('name')->firstOrFail();

    $response = $this->getJson('/api/v1/location/browse/cities?state_id='.$state->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'state' => ['id', 'name'],
                'popular',
                'cities',
            ],
        ]);

    expect($response->json('data.popular'))->toBeArray();
    expect(count($response->json('data.popular')))->toBeLessThanOrEqual(5);
});

test('product store auto generates sku when omitted', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $categoryId = Product::query()->where('sku', 'DEMO-PHONE-1')->value('category_id');

    $response = $this->postJson('/api/v1/products', [
        'title' => 'Auto SKU Product',
        'description' => 'Test description',
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'category_id' => $categoryId,
        'price' => 1000,
        'stock' => 1,
        'status' => 'draft',
        'images' => [
            ['path' => 'uploads/test/auto-sku.jpg', 'disk' => 'public'],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $sku = $response->json('data.sku');
    expect($sku)->toBeString();
    $this->assertNotSame('', trim((string) $sku));
    expect((string) $sku)->toStartWith('SKU-');
});

test('ai writer requires platform setting and permission', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/ai-writer/generate', [
        'topic' => 'Samsung Galaxy A54',
        'content_type' => 'product',
    ])->assertForbidden();

    app(PlatformSettingsService::class)->upsertMany(['ai_writer_status' => true]);
    Permission::findOrCreate('ai_writer', 'web');
    $vendor->givePermissionTo('ai_writer');

    $this->postJson('/api/v1/ai-writer/generate', [
        'topic' => 'Samsung Galaxy A54',
        'content_type' => 'product',
        'tone' => 'professional',
        'length' => 'medium',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.source', 'stub');
});
