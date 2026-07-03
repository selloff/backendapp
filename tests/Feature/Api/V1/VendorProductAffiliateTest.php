<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductAffiliateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_toggle_product_affiliate_when_program_uses_selected_products(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $product = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        $this->assertFalse((bool) $product->is_affiliate);

        $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_affiliate', true);

        $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_affiliate', false);
    }

    public function test_vendor_cannot_toggle_product_affiliate_when_program_is_not_selected_products_mode(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

        ReferralProfile::query()->where('user_id', $vendor->id)->update(['vendor_affiliate_status' => 1]);

        $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
            ->assertStatus(422);
    }

    public function test_vendor_affiliate_program_exposes_product_management_flag(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/affiliate')
            ->assertOk()
            ->assertJsonPath('data.can_manage_product_affiliate', true)
            ->assertJsonPath('data.vendor_affiliate_status', 2);
    }

    public function test_vendor_can_save_affiliate_status_setting(): void
    {
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
    }

    public function test_vendor_product_list_includes_is_affiliate_field(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'affiliate_program' => json_encode([
                'status' => true,
                'type' => 'seller_based',
                'commission_rate' => 5,
                'discount_rate' => 1,
            ]),
        ], 'general');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/products')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'DEMO-PHONE-1', 'is_affiliate' => true])
            ->assertJsonFragment(['sku' => 'DEMO-PHONE-1', 'is_commission_set' => true]);
    }

    public function test_toggling_affiliate_does_not_remove_product_from_items_for_sale(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/affiliate/toggle")
            ->assertOk()
            ->assertJsonPath('data.is_affiliate', true);

        $skus = collect($this->getJson('/api/v1/vendor/products')->json('data.data'))
            ->pluck('sku')
            ->all();

        $this->assertContains('DEMO-AUDIO-1', $skus);
    }
}
