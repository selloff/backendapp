<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductWizardDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_create_free_digital_product(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/products', [
            'title' => 'Free digital guide',
            'type' => 'digital',
            'listing_type' => 'sell_on_site',
            'price' => 0,
            'is_free_product' => true,
            'status' => 'draft',
            'digital_files' => [
                ['file_name' => 'uploads/temp/demo.pdf', 'storage' => 'public'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_free_product', true)
            ->assertJsonPath('data.price', '0.00');
    }

    public function test_vat_rate_rejected_above_max_when_vat_enabled(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['vat_status' => true], 'payment');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/products', [
            'title' => 'VAT test product',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'price' => 5000,
            'vat_rate' => 150,
            'status' => 'draft',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vat_rate']);
    }

    public function test_sku_auto_generated_when_omitted_and_marketplace_sku_enabled(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['marketplace_sku' => true], 'product');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/v1/products', [
            'title' => 'SKU auto product',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'price' => 12000,
            'status' => 'draft',
        ])->assertCreated();

        $sku = (string) $response->json('data.sku');
        $this->assertNotSame('', $sku);
        $this->assertStringStartsWith('SKU-', $sku);
    }

    public function test_product_show_includes_effective_commission_from_category(): void
    {
        $category = Category::query()->where('is_commission_set', true)->first();
        if ($category === null) {
            $category = Category::query()->firstOrFail();
            $category->update(['is_commission_set' => true, 'commission_rate' => 8]);
        }

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'category_id' => $category->id,
            'is_commission_set' => false,
            'commission_rate' => null,
        ]);

        $rate = (float) $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->json('data.effective_commission_rate');

        $this->assertEquals((float) $category->commission_rate, $rate);
    }
}
