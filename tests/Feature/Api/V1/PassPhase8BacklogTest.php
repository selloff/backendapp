<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassPhase8BacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_fetch_and_update_product_with_extras(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $category = Category::query()->firstOrFail();

        $created = $this->postJson('/api/v1/products', [
            'title' => 'Phase 8 Digital Product',
            'description' => 'Digital download',
            'category_id' => $category->id,
            'type' => 'digital',
            'listing_type' => 'license_key',
            'price' => 5000,
            'stock' => 10,
            'options' => [
                ['name' => 'Plan', 'values' => ['Basic', 'Pro']],
            ],
            'license_keys' => ['PHASE8-KEY-1', 'PHASE8-KEY-2'],
            'images' => [
                ['path' => 'uploads/images/test/phase8.jpg', 'disk' => 'public'],
            ],
        ])->assertCreated();

        $productId = $created->json('data.id');

        $this->getJson("/api/v1/vendor/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.type', 'digital')
            ->assertJsonPath('data.listing_type', 'license_key')
            ->assertJsonStructure(['data' => ['options', 'license_keys', 'images']]);

        $this->putJson("/api/v1/products/{$productId}", [
            'title' => 'Phase 8 Digital Updated',
            'images' => [],
            'options' => [
                ['name' => 'Plan', 'values' => ['Enterprise']],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Phase 8 Digital Updated')
            ->assertJsonCount(0, 'data.images')
            ->assertJsonCount(1, 'data.options');
    }

    public function test_vendor_can_fulfill_order_with_tracking(): void
    {
        $order = $this->placeVendorOrder();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson("/api/v1/vendor/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);

        $this->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'shipped',
            'tracking_number' => 'PHASE8-TRACK',
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping_tracking_number', 'PHASE8-TRACK');

        $this->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
            'status' => 'completed',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_vendor_can_approve_refund_with_message_thread(): void
    {
        $order = $this->placeVendorOrder();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/orders/{$order->id}/refund-requests", [
            'description' => 'Wrong size delivered',
        ])->assertCreated();

        $refund = RefundRequest::query()->where('order_id', $order->id)->firstOrFail();

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/refunds')
            ->assertOk()
            ->assertJsonFragment(['id' => $refund->id]);

        $this->postJson("/api/v1/vendor/refunds/{$refund->id}/approve", [
            'message' => 'Refund approved — sorry for the inconvenience.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_vendor_can_manage_shipping_methods(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $zoneId = $this->postJson('/api/v1/vendor/shipping/zones', ['name' => 'Phase 8 Zone'])
            ->assertCreated()
            ->json('data.id');

        $methodId = $this->postJson("/api/v1/vendor/shipping/zones/{$zoneId}/methods", [
            'name' => 'Express',
            'flat_rate' => 2500,
        ])
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/v1/vendor/shipping/zones/{$zoneId}/methods/{$methodId}", [
            'name' => 'Express updated',
            'flat_rate' => 3000,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Express updated');

        $this->deleteJson("/api/v1/vendor/shipping/zones/{$zoneId}/methods/{$methodId}")
            ->assertOk();

        $this->deleteJson("/api/v1/vendor/shipping/zones/{$zoneId}")
            ->assertOk();
    }

    public function test_vendor_can_update_coupon_with_product_linkage(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $product = $this->postJson('/api/v1/products', [
            'title' => 'Coupon Target Product',
            'price' => 1000,
            'stock' => 5,
        ])->assertCreated()->json('data');

        $couponId = $this->postJson('/api/v1/vendor/coupons', [
            'coupon_code' => 'PHASE8',
            'discount_rate' => 15,
        ])
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/v1/vendor/coupons/{$couponId}", [
            'discount_rate' => 20,
            'product_ids' => [$product['id']],
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_rate', 20);

        $this->assertDatabaseHas('coupon_products', [
            'coupon_id' => $couponId,
            'product_id' => $product['id'],
        ]);

        $this->assertTrue(
            Coupon::query()->findOrFail($couponId)->products()->where('products.id', $product['id'])->exists(),
        );
    }

    public function test_vendor_earnings_summary_includes_dashboard_counts(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/earnings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_earned',
                    'available_balance',
                    'reserved_for_payouts',
                    'sales_count',
                    'products_count',
                    'pending_products_count',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.products_count'));
    }

    private function placeVendorOrder(): Order
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $product = $this->postJson('/api/v1/products', [
            'title' => 'Phase 8 Order Item',
            'price' => 2500,
            'stock' => 2,
        ])->assertCreated()->json('data');

        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', ['product_id' => $product['id'], 'quantity' => 1])->assertCreated();
        $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
        $placed = $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
            ->assertCreated()
            ->json('data');

        return Order::query()->findOrFail($placed['id']);
    }
}
