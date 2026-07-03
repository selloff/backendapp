<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Escrow\Mail\EscrowStageMail;
use App\Modules\Selloff\Escrow\Support\EscrowMailStage;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaveCEscrowNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_seller_agreement_notifies_escrow_admin(): void
    {
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
    }

    public function test_guest_cannot_reveal_contact_when_setting_disabled(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'show_vendor_contact_info_guests' => false,
        ], 'general');

        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $this->postJson("/api/v1/products/{$product->slug}/view-contact")
            ->assertUnauthorized();
    }

    public function test_authenticated_buyer_can_reveal_contact_when_guests_disabled(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'show_vendor_contact_info_guests' => false,
        ], 'general');

        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/products/{$product->slug}/view-contact")
            ->assertOk()
            ->assertJsonPath('data.phone_number', '+2348012345678');
    }

    public function test_vendor_cannot_create_bidding_product_when_bidding_disabled(): void
    {
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
    }

    public function test_product_show_exposes_sku_flag_and_guest_contact_setting(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'show_vendor_contact_info_guests' => false,
            'marketplace_sku' => true,
        ], 'general');

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.show_sku', true)
            ->assertJsonPath('data.contact_available_to_guests', false);
    }
}
