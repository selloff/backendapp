<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();
});

function disableCommerceEmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

function placeCommerceTestOrder(): Order
{
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $product = test()->postJson('/api/v1/products', [
        'title' => 'Commerce email test item',
        'price' => 2500,
        'stock' => 2,
        'status' => 'published',
    ])->assertCreated()->json('data');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    test()->postJson('/api/v1/cart/items', ['product_id' => $product['id'], 'quantity' => 1])->assertCreated();
    $checkout = test()->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $placed = test()->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    return Order::query()->findOrFail($placed['id']);
}

test('wallet checkout queues buyer and seller new order email jobs', function () {
    placeCommerceTestOrder();

    $buyerJob = EmailJob::query()->where('email_type', 'new_order')->first();
    $sellerJob = EmailJob::query()->where('email_type', 'new_order_seller')->first();

    expect($buyerJob)->not->toBeNull()
        ->and($buyerJob->to_email)->toBe('buyer@selloff.test')
        ->and($buyerJob->template)->toBe('new-order')
        ->and($sellerJob)->not->toBeNull()
        ->and($sellerJob->to_email)->toBe('vendor@selloff.test')
        ->and($sellerJob->template)->toBe('new-order-seller');

    expect(EmailJob::query()->whereIn('email_type', ['new_order', 'new_order_seller'])->count())->toBeGreaterThanOrEqual(2);
});

test('new order emails are skipped when order email toggle is disabled', function () {
    disableCommerceEmailOption('email_option_new_order');

    placeCommerceTestOrder();

    expect(EmailJob::query()->whereIn('email_type', ['new_order', 'new_order_seller', 'order_confirmation'])->count())->toBe(0);
});

test('vendor shipping update queues buyer order shipped email job', function () {
    $order = placeCommerceTestOrder();
    EmailJob::query()->delete();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
        'status' => 'shipped',
        'tracking_number' => 'COMMERCE-TRACK-1',
    ])->assertOk();

    $job = EmailJob::query()->where('email_type', 'order_shipped')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('buyer@selloff.test')
        ->and($job->template)->toBe('order-shipped')
        ->and($job->template_data['trackingNumber'] ?? null)->toBe('COMMERCE-TRACK-1');
});

test('order shipped email is skipped when shipped toggle is disabled', function () {
    disableCommerceEmailOption('email_option_order_shipped');

    $order = placeCommerceTestOrder();
    EmailJob::query()->delete();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->patchJson("/api/v1/vendor/orders/{$order->id}/status", [
        'status' => 'shipped',
        'tracking_number' => 'NO-EMAIL-TRACK',
    ])->assertOk();

    expect(EmailJob::query()->where('email_type', 'order_shipped')->count())->toBe(0);
});

test('buyer quote request queues seller quote request email job', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/quote-requests', [
        'product_id' => $product->id,
        'quantity' => 2,
        'message' => 'Bulk pricing please',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'quote_request')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('main');
});

test('vendor quote response queues buyer quote submitted email job', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    Sanctum::actingAs($buyer);
    $this->postJson('/api/v1/quote-requests', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertCreated();

    $quoteId = QuoteRequest::query()->where('buyer_id', $buyer->id)->latest('id')->value('id');
    EmailJob::query()->delete();

    Sanctum::actingAs($vendor);
    $this->patchJson("/api/v1/vendor/quote-requests/{$quoteId}", [
        'quoted_price' => 45000,
        'status' => 'quoted',
    ])->assertOk();

    $job = EmailJob::query()->where('email_type', 'quote_submitted')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('buyer@selloff.test');
});

test('buyer refund request queues seller refund submitted email job', function () {
    $order = placeCommerceTestOrder();
    EmailJob::query()->delete();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/orders/{$order->id}/refund-requests", [
        'description' => 'Wrong size delivered',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'refund_submitted')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('main');
});

test('vendor refund approval queues buyer refund approved email job', function () {
    $order = placeCommerceTestOrder();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/orders/{$order->id}/refund-requests", [
        'description' => 'Damaged item',
    ])->assertCreated();

    $refund = RefundRequest::query()->where('order_id', $order->id)->firstOrFail();
    EmailJob::query()->delete();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/refunds/{$refund->id}/approve", [
        'message' => 'Approved — sorry for the inconvenience.',
    ])->assertOk();

    $job = EmailJob::query()->where('email_type', 'refund_approved')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('buyer@selloff.test');
});

test('refund emails are skipped when refund toggle is disabled', function () {
    disableCommerceEmailOption('email_option_refund');

    $order = placeCommerceTestOrder();
    EmailJob::query()->delete();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/orders/{$order->id}/refund-requests", [
        'description' => 'No email expected',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'refund_submitted')->count())->toBe(0);
});
