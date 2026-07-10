<?php

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin order show includes legacy detail fields', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $order = Order::query()->with(['items', 'buyer'])->firstOrFail();
    $item = $order->items->first();
    expect($item)->not->toBeNull();

    $item->update([
        'product_vat' => 120,
        'product_vat_rate' => 7.5,
        'seller_shipping_cost' => 500,
        'shipping_method' => 'flat_rate',
        'shipping_tracking_number' => 'TRACK-001',
        'shipping_tracking_url' => 'https://track.example/001',
        'order_status' => 'shipped',
    ]);

    $order->update([
        'price_vat' => 120,
        'coupon_code' => 'SAVE10',
        'coupon_discount' => 50,
        'transaction_fee' => 25,
        'transaction_fee_rate' => 1.5,
        'affiliate_data' => ['discount' => 10, 'discountRate' => 5],
        'global_taxes_data' => [
            ['taxNameArray' => 'Service tax', 'taxRate' => 2, 'taxTotal' => 15],
        ],
    ]);

    PaymentTransaction::query()->updateOrCreate(
        ['order_id' => $order->id],
        [
            'user_id' => $order->buyer_id,
            'amount' => $order->price_total,
            'currency_code' => 'USD',
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
        ],
    );

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/orders/'.$order->id)
        ->assertOk()
        ->assertJsonPath('data.price_vat', '120.00')
        ->assertJsonPath('data.coupon_code', 'SAVE10')
        ->assertJsonPath('data.coupon_discount', '50.00')
        ->assertJsonPath('data.transaction_fee', '25.00')
        ->assertJsonPath('data.affiliate_data.discount', 10)
        ->assertJsonPath('data.transaction.currency_code', 'USD')
        ->assertJsonStructure([
            'data' => [
                'buyer' => ['id', 'username', 'slug', 'email', 'phone_number', 'avatar'],
                'items' => [
                    ['product_vat', 'seller_shipping_cost', 'shipping_tracking_number', 'seller'],
                ],
            ],
        ])
        ->assertJsonPath('data.items.0.product_vat', '120.00')
        ->assertJsonPath('data.items.0.seller_shipping_cost', '500.00')
        ->assertJsonPath('data.items.0.shipping_tracking_number', 'TRACK-001');
});

test('admin can view invoice by order number', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $order = Order::query()->where('payment_status', 'payment_received')->firstOrFail();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/invoices/orders/'.$order->order_number.'?type=admin')
        ->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonStructure([
            'data' => [
                'invoice_number',
                'company',
                'client',
                'payment',
                'items',
                'totals' => ['subtotal', 'total'],
            ],
        ]);
});

test('admin can approve guest order item', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $order = Order::query()->create([
        'buyer_id' => null,
        'guest_email' => 'guest@selloff.test',
        'order_number' => 990001,
        'price_subtotal' => 1000,
        'price_total' => 1000,
        'currency_code' => 'NGN',
        'status' => 'processing',
        'payment_method' => 'bank_transfer',
        'payment_status' => 'awaiting_payment',
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => null,
        'seller_id' => null,
        'product_title' => 'Guest product',
        'quantity' => 1,
        'unit_price' => 1000,
        'total_price' => 1000,
        'is_approved' => false,
        'order_status' => 'order_processing',
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/orders/'.$order->id.'/items/'.$item->id.'/approve-guest')
        ->assertOk()
        ->assertJsonPath('data.is_approved', true);
});
