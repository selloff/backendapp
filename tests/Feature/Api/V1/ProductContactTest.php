<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Models\Product;
use Tests\TestCase;

class ProductContactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);

        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'show_vendor_contact_info_guests' => true,
        ], 'general');
    }

    public function test_view_contact_reveals_vendor_phone_and_gtm_event(): void
    {
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $response = $this->postJson("/api/v1/products/{$product->slug}/view-contact")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone_number', '+2348012345678');

        $events = $response->json('data.gtm_events');
        $this->assertIsArray($events);
        $this->assertSame('view_contact', $events[0]['event']);
        $this->assertSame((string) $product->id, $events[0]['eventData']['item_id']);
    }

    public function test_click_to_call_returns_gtm_event(): void
    {
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $response = $this->postJson("/api/v1/products/{$product->slug}/click-to-call")
            ->assertOk()
            ->assertJsonPath('success', true);

        $events = $response->json('data.gtm_events');
        $this->assertIsArray($events);
        $this->assertSame('click_to_call', $events[0]['event']);
    }

    public function test_view_contact_rejects_marketplace_listings(): void
    {
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->postJson("/api/v1/products/{$product->slug}/view-contact")
            ->assertStatus(422);
    }

    public function test_product_show_includes_vendor_contact_flags_without_phone(): void
    {
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.show_phone_contact', true)
            ->assertJsonPath('data.vendor.email', 'vendor@selloff.test')
            ->assertJsonMissingPath('data.vendor.phone_number');
    }
}
