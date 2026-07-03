<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Review\Models\ProductReview;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass8DemoConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_demo_seed_produces_multi_vendor_marketplace(): void
    {
        $this->assertGreaterThanOrEqual(6, User::role('vendor')->count());
        $this->assertGreaterThanOrEqual(45, Product::query()->where('status', 'published')->count());
        $this->assertGreaterThanOrEqual(2, Order::query()->count());
        $this->assertGreaterThanOrEqual(2, VendorEarning::query()->count());
        $this->assertGreaterThanOrEqual(1, PayoutRequest::query()->where('status', 'pending')->count());
        $this->assertGreaterThanOrEqual(7, ProductReview::query()->where('is_approved', true)->count());

        $this->getJson('/api/v1/products?per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data']]);

        $vendorSlugs = collect($this->getJson('/api/v1/products?per_page=50')->json('data.data'))
            ->pluck('vendor.shop_name')
            ->filter()
            ->unique()
            ->values();

        $this->assertGreaterThanOrEqual(4, $vendorSlugs->count());
    }

    public function test_demo_buyer_has_order_history_and_wallet_checkout_still_works(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $orders = $this->getJson('/api/v1/orders')->assertOk()->json('data.data');
        $this->assertGreaterThanOrEqual(2, count($orders));

        $this->getJson('/api/v1/orders/'.$orders[0]['id'])
            ->assertOk()
            ->assertJsonPath('data.buyer.email', 'buyer@selloff.test');

        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');

        $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'wallet_balance');
    }

    public function test_demo_vendors_have_earnings_and_admin_sees_orders(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $summary = $this->getJson('/api/v1/vendor/earnings')->assertOk()->json('data');
        $this->assertGreaterThan(0, $summary['total_earned']);
        $this->assertGreaterThan(0, $summary['available_balance']);

        $vendorOrders = $this->getJson('/api/v1/vendor/orders')->assertOk()->json('data.data');
        $this->assertNotEmpty($vendorOrders);

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/orders')->assertOk();
        $this->getJson('/api/v1/admin/payouts?status=pending')->assertOk();
        $this->getJson('/api/v1/admin/products?pending_only=1')->assertOk();
    }

    public function test_product_detail_includes_options_and_related_products(): void
    {
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->getJson('/api/v1/products/'.$phone->slug)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'DEMO-PHONE-1')
            ->assertJsonStructure([
                'data' => [
                    'options' => [['id', 'name', 'values']],
                    'variants' => [['id', 'option_value_ids', 'stock']],
                    'category_breadcrumb',
                ],
            ]);

        $this->assertNotEmpty($this->getJson('/api/v1/products/'.$phone->slug)->json('data.options'));
        $this->assertNotEmpty($this->getJson('/api/v1/products/'.$phone->slug)->json('data.variants'));

        $related = $this->getJson('/api/v1/products/'.$phone->id.'/related?limit=5')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($related);
        $this->assertGreaterThanOrEqual(1, count($related));
        $this->assertNotContains($phone->id, array_column($related, 'id'));
    }

    public function test_add_to_cart_with_variant_succeeds(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $variantId = $phone->variants()->where('is_default', true)->value('id');
        $this->assertNotNull($variantId);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $phone->id,
            'quantity' => 1,
            'variant_id' => $variantId,
            'product_options_summary' => 'Color: Black, Storage: 128GB',
        ])->assertCreated();
    }
}
