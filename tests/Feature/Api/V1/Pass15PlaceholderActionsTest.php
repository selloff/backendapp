<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass15PlaceholderActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_export_orders_transactions_and_digital_sales(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->get('/api/v1/admin/orders/export?format=csv')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get('/api/v1/admin/transactions/export?format=csv')
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get('/api/v1/admin/digital-sales/export?format=csv')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_can_delete_commerce_records(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $transaction = PaymentTransaction::query()->first();
        if ($transaction) {
            $this->deleteJson('/api/v1/admin/transactions/'.$transaction->id)
                ->assertOk()
                ->assertJsonPath('success', true);
            $this->assertDatabaseMissing('payment_transactions', ['id' => $transaction->id]);
        }

        $digitalSale = DigitalSale::query()->where('purchase_code', 'DEMO-DL-001')->firstOrFail();
        $this->deleteJson('/api/v1/admin/digital-sales/'.$digitalSale->id)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('digital_sales', ['id' => $digitalSale->id]);

        $earning = VendorEarning::query()->firstOrFail();
        $earningId = $earning->id;
        $this->deleteJson('/api/v1/admin/earnings/'.$earningId)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('vendor_earnings', ['id' => $earningId]);

        $payout = PayoutRequest::query()->firstOrFail();
        $payoutId = $payout->id;
        $this->deleteJson('/api/v1/admin/payouts/'.$payoutId)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('payout_requests', ['id' => $payoutId]);
    }

    public function test_admin_can_update_seller_balance(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/admin/earnings/seller-balances/'.$vendor->id, [
            'balance' => 1234.56,
        ])
            ->assertOk()
            ->assertJsonPath('data.seller_id', $vendor->id)
            ->assertJsonPath('data.balance', 1234.56);
    }

    public function test_admin_and_vendor_can_view_invoices(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $order = Order::query()->where('payment_status', 'payment_received')->firstOrFail();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/orders/'.$order->id.'/invoice')
            ->assertOk()
            ->assertJsonStructure(['data' => ['invoice_number', 'order_number', 'items']]);

        $membership = MembershipTransaction::query()->firstOrFail();
        $this->getJson('/api/v1/admin/membership/transactions/'.$membership->id.'/invoice')
            ->assertOk()
            ->assertJsonStructure(['data' => ['invoice_number', 'description', 'amount']]);

        $promotion = PromotionTransaction::query()->firstOrFail();
        $this->getJson('/api/v1/admin/promotion-transactions/'.$promotion->id.'/invoice')
            ->assertOk()
            ->assertJsonStructure(['data' => ['invoice_number', 'description', 'amount']]);

        $wallet = WalletDeposit::query()->firstOrFail();
        $this->getJson('/api/v1/admin/payments/wallet-deposits/'.$wallet->id.'/invoice')
            ->assertOk()
            ->assertJsonStructure(['data' => ['invoice_number', 'description', 'amount']]);

        Sanctum::actingAs($vendor);
        $this->getJson('/api/v1/vendor/membership/transactions/'.$membership->id.'/invoice')
            ->assertOk();
        $this->getJson('/api/v1/vendor/promotion-transactions/'.$promotion->id.'/invoice')
            ->assertOk();
        $this->getJson('/api/v1/orders/'.$order->id.'/invoice')
            ->assertOk();
    }

    public function test_vendor_can_duplicate_product(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/v1/vendor/products/'.$product->id.'/duplicate')
            ->assertCreated()
            ->assertJsonPath('success', true);

        $newId = $response->json('data.id');
        $this->assertNotSame($product->id, $newId);
        $this->assertDatabaseHas('products', [
            'id' => $newId,
            'vendor_id' => $vendor->id,
            'status' => 'draft',
        ]);
    }
}
