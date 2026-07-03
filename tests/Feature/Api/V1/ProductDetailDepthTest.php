<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\DigitalFile;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Shipping\Models\DeliveryTimeOption;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductDetailDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_product_show_includes_platform_safety_tips(): void
    {
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $tips = $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->json('data.safety_tips');

        $this->assertIsArray($tips);
        $this->assertCount(5, $tips);
        $this->assertStringContainsString('Escrow', (string) $tips[1]);
        $this->assertStringContainsString('strong password', (string) $tips[3]);
    }

    public function test_product_show_uses_updated_platform_safety_tips(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'product_safety_tips' => ['Custom tip one', 'Custom tip two'],
        ], 'product_listing');

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.safety_tips', ['Custom tip one', 'Custom tip two']);
    }

    public function test_shipping_estimate_requires_location_for_guests_without_params(): void
    {
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}/shipping-estimate")
            ->assertOk()
            ->assertJsonPath('data.status', 'location_required');
    }

    public function test_shipping_estimate_returns_zone_label_for_authenticated_buyer(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}/shipping-estimate")
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.label', '3-5 days');
    }

    public function test_product_show_includes_viewer_digital_purchase_for_owned_digital_product(): void
    {
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
    }

    public function test_shipping_estimate_includes_delivery_time_label_when_set(): void
    {
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
    }
}
