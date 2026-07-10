<?php

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('homepage returns selloff section order and settings', function () {
    $response = $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');

    expect($data['settings']['index_products_per_row'])->toBe(5);
    expect($data['settings']['product_grid_layout'])->toBe('rows');
    expect($data['settings']['index_recommended_products_count'])->toBe(10);
    expect($data['settings']['index_latest_products'])->toBeTrue();
    expect($data['settings']['fea_categories_design'])->toBe('round_boxes');
    expect($data['site_banners'])->toHaveKey('top');
    expect($data['site_banners']['mid'])->toBeNull();

    $sectionKeys = collect($data['sections'])->pluck('key')->all();
    expect($sectionKeys)->toContain('phones');
    expect($sectionKeys)->toContain('laptops');
    expect($sectionKeys)->toContain('other');

    $phones = collect($data['sections'])->firstWhere('key');
    expect($phones['title'])->toBe('Latest Smartphones & Tablets');
    expect($phones['products'])->not->toBeEmpty();
});

test('homepage hero scope returns only above-the-fold payload', function () {
    $full = $this->getJson('/api/v1/homepage')->assertOk()->json('data');
    $hero = $this->getJson('/api/v1/homepage?scope=hero')->assertOk()->json('data');

    expect($hero['sliders'])->not->toBeEmpty();
    expect($hero['sections'])->toHaveCount(1);
    expect($hero['sections'][0]['key'])->toBe($full['sections'][0]['key']);
    expect($hero['promoted_products'])->toBe([]);
    expect($hero['trending_products'])->toBe([]);
    expect($hero['category_carousels'])->toBe([]);
    expect($hero['blog_posts'])->toBe([]);
});

test('homepage deferred scope returns below-the-fold payload', function () {
    $full = $this->getJson('/api/v1/homepage')->assertOk()->json('data');
    $deferred = $this->getJson('/api/v1/homepage?scope=deferred')->assertOk()->json('data');

    expect($deferred['sliders'])->toBe([]);
    expect(collect($deferred['sections'])->pluck('key')->all())
        ->toBe(collect($full['sections'])->slice(1)->pluck('key')->all());

    if (count($full['sections']) > 1) {
        expect($deferred['sections'])->not->toBeEmpty();
    }

    expect($deferred['promoted_products'])->toBe($full['promoted_products']);
    expect($deferred['trending_products'])->toBe($full['trending_products']);
    expect($deferred['category_carousels'])->toBe($full['category_carousels']);
});

test('homepage normalizes featured categories design values', function () {
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'fea_categories_design' => 'square_layout',
    ], 'general');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.fea_categories_design', 'grid_layout');

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'fea_categories_design' => 'round_layout',
    ], 'general');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.fea_categories_design', 'round_boxes');
});

test('homepage normalizes product grid layout values', function () {
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'product_grid_layout' => 'masonry',
    ], 'general');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.product_grid_layout', 'masonry');

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'product_grid_layout' => 'invalid',
    ], 'general');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.product_grid_layout', 'rows');
});

test('latest phone section orders by created at', function () {
    $phonesCategory = \App\Modules\Selloff\Catalog\Models\Category::query()
        ->where('slug', 'smartphones')
        ->firstOrFail();

    $older = \App\Modules\Selloff\Catalog\Models\Product::query()->create([
        'vendor_id' => \App\Models\User::query()->where('email', 'vendor@selloff.test')->value('id'),
        'category_id' => $phonesCategory->id,
        'slug' => 'older-phone-'.uniqid(),
        'sku' => 'OLDER-PHONE',
        'price' => 10000,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);
    $older->translations()->create([
        'locale' => 'en',
        'title' => 'Older Phone Listing',
    ]);

    $newer = \App\Modules\Selloff\Catalog\Models\Product::query()->create([
        'vendor_id' => $older->vendor_id,
        'category_id' => $phonesCategory->id,
        'slug' => 'newer-phone-'.uniqid(),
        'sku' => 'NEWER-PHONE',
        'price' => 12000,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_verified' => true,
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $newer->translations()->create([
        'locale' => 'en',
        'title' => 'Newer Phone Listing',
    ]);

    $phones = collect($this->getJson('/api/v1/homepage')->json('data.sections'))->firstWhere('key', 'phones');
    expect($phones)->not->toBeNull();

    $titles = collect($phones['products'])->pluck('title')->all();
    $newerIndex = array_search('Newer Phone Listing', $titles, true);
    $olderIndex = array_search('Older Phone Listing', $titles, true);

    $this->assertNotFalse($newerIndex);
    $this->assertNotFalse($olderIndex);
    expect($newerIndex)->toBeLessThan($olderIndex);
});

test('homepage mid site banner uses platform setting and hides when empty', function () {
    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.site_banners.mid', null);

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'homepage_site_banner_mid_image' => 'uploads/banners/mid-banner.webp',
        'homepage_site_banner_mid_alt' => 'Mid banner alt',
    ], 'homepage');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.site_banners.mid.image_path', 'uploads/banners/mid-banner.webp')
        ->assertJsonPath('data.site_banners.mid.alt', 'Mid banner alt');

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'homepage_site_banner_mid_image' => null,
    ], 'homepage');

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.site_banners.mid', null);
});

test('homepage restores product sections when latest and promoted are both disabled', function () {
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'index_latest_products' => false,
        'index_promoted_products' => false,
    ], 'homepage');

    $response = $this->getJson('/api/v1/homepage')
        ->assertOk();

    $data = $response->json('data');

    expect($data['settings']['index_latest_products'])->toBeTrue();
    expect($data['settings']['index_promoted_products'])->toBeTrue();
    expect($data['sections'])->not->toBeEmpty();
    expect($data['promoted_products'])->not->toBeEmpty();
});
