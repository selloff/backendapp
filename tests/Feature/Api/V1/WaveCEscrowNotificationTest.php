<?php

use App\Modules\Selloff\Escrow\Mail\EscrowStageMail;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('seller agreement notifies escrow admin', function () {
    Mail::fake();
    config(['selloff.escrow_admin_email' => 'escrow-admin@selloff.test']);

    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => false,
        'status' => 'buyer_agreed',
    ]);

    $this->postJson('/api/v1/escrow/token/demo-seller-escrow-token/confirm')
        ->assertOk()
        ->assertJsonPath('data.seller_agreed', true);

    Mail::assertSent(EscrowStageMail::class, function (EscrowStageMail $mail): bool {
        return $mail->data->stage === EscrowMailStage::AdminEscrowInitiation
            && str_contains($mail->data->subject, 'New Escrow Agreement Initiated');
    });
});

test('guest cannot reveal contact when setting disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'show_vendor_contact_info_guests' => false,
    ], 'general');

    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $this->postJson("/api/v1/products/{$product->slug}/view-contact")
        ->assertUnauthorized();
});

test('authenticated buyer can reveal contact when guests disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'show_vendor_contact_info_guests' => false,
    ], 'general');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson("/api/v1/products/{$product->slug}/view-contact")
        ->assertOk()
        ->assertJsonPath('data.phone_number', '+2348012345678');
});

test('vendor cannot create bidding product when bidding disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'bidding_enabled' => false,
    ], 'product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Blocked bidding listing',
        'price' => 1000,
        'type' => 'physical',
        'listing_type' => 'bidding',
        'status' => 'draft',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['listing_type']);
});

test('product show exposes sku flag and guest contact setting', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'show_vendor_contact_info_guests' => false,
        'marketplace_sku' => true,
    ], 'general');

    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.show_sku', true)
        ->assertJsonPath('data.contact_available_to_guests', false);
});
