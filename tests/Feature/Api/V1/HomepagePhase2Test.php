<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HomepagePhase2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_homepage_returns_selloff_section_order_and_settings(): void
    {
        $response = $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertSame(5, $data['settings']['index_products_per_row']);
        $this->assertSame('rows', $data['settings']['product_grid_layout']);
        $this->assertSame(10, $data['settings']['index_recommended_products_count']);
        $this->assertTrue($data['settings']['index_latest_products']);
        $this->assertSame('round_boxes', $data['settings']['fea_categories_design']);
        $this->assertArrayHasKey('top', $data['site_banners']);
        $this->assertNull($data['site_banners']['mid']);

        $sectionKeys = collect($data['sections'])->pluck('key')->all();
        $this->assertContains('phones', $sectionKeys);
        $this->assertContains('laptops', $sectionKeys);
        $this->assertContains('other', $sectionKeys);

        $phones = collect($data['sections'])->firstWhere('key', 'phones');
        $this->assertSame('Latest Smartphones & Tablets', $phones['title']);
        $this->assertNotEmpty($phones['products']);
    }

    public function test_homepage_normalizes_featured_categories_design_values(): void
    {
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
    }

    public function test_homepage_normalizes_product_grid_layout_values(): void
    {
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
    }

    public function test_latest_phone_section_orders_by_created_at(): void
    {
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
        $this->assertNotNull($phones);

        $titles = collect($phones['products'])->pluck('title')->all();
        $newerIndex = array_search('Newer Phone Listing', $titles, true);
        $olderIndex = array_search('Older Phone Listing', $titles, true);

        $this->assertNotFalse($newerIndex);
        $this->assertNotFalse($olderIndex);
        $this->assertLessThan($olderIndex, $newerIndex);
    }

    public function test_homepage_mid_site_banner_uses_platform_setting_and_hides_when_empty(): void
    {
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
    }

    public function test_homepage_restores_product_sections_when_latest_and_promoted_are_both_disabled(): void
    {
        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'index_latest_products' => false,
            'index_promoted_products' => false,
        ], 'homepage');

        $response = $this->getJson('/api/v1/homepage')
            ->assertOk();

        $data = $response->json('data');

        $this->assertTrue($data['settings']['index_latest_products']);
        $this->assertTrue($data['settings']['index_promoted_products']);
        $this->assertNotEmpty($data['sections']);
        $this->assertNotEmpty($data['promoted_products']);
    }
}
