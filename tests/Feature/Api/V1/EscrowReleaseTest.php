<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowLedgerEntry;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EscrowReleaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_manual_funding_records_ledger_hold(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $transaction = $this->prepareFundableTransaction();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/escrow/transactions/{$transaction->id}/stages", [
            'payment_received' => true,
            'payment_reference' => 'BANK-TEST-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_received', true)
            ->assertJsonPath('data.status', EscrowStatus::FUNDED);

        $this->assertDatabaseHas('escrow_ledger_entries', [
            'escrow_transaction_id' => $transaction->id,
            'entry_type' => 'hold',
        ]);
    }

    public function test_delivery_confirm_schedules_release_and_command_credits_vendor_earning(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['escrow_inspection_days' => 0]);

        $transaction = $this->prepareFundableTransaction();
        $transaction->update([
            'payment_received' => true,
            'payment_method' => 'manual',
            'funded_at' => now(),
            'seller_shipped_item' => true,
            'shipped_at' => now(),
            'status' => EscrowStatus::SHIPPED,
        ]);

        EscrowLedgerEntry::query()->create([
            'escrow_transaction_id' => $transaction->id,
            'entry_type' => 'hold',
            'amount' => 1000,
            'currency_code' => 'NGN',
        ]);

        $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/confirm-delivery')
            ->assertOk()
            ->assertJsonPath('data.buyer_confirmed_item_delivery', true);

        $transaction->refresh();
        $this->assertTrue($transaction->transaction_complete);
        $this->assertSame(EscrowStatus::COMPLETED, $transaction->status);

        $this->assertDatabaseHas('vendor_earnings', [
            'escrow_transaction_id' => $transaction->id,
            'seller_id' => $transaction->seller_id,
        ]);
    }

    public function test_dispute_blocks_scheduled_release_until_admin_resolves(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['escrow_inspection_days' => 0]);

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $transaction = $this->prepareFundableTransaction();
        $transaction->update([
            'payment_received' => true,
            'payment_method' => 'manual',
            'funded_at' => now(),
            'status' => EscrowStatus::SHIPPED,
            'seller_shipped_item' => true,
        ]);

        $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/dispute', ['reason' => 'Item not as described'])
            ->assertOk()
            ->assertJsonPath('data.status', EscrowStatus::DISPUTED);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/escrow/transactions/{$transaction->id}/resolve-dispute", [
            'resolution' => 'release_seller',
            'note' => 'Seller evidence accepted',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EscrowStatus::COMPLETED);

        $this->assertDatabaseHas('vendor_earnings', [
            'escrow_transaction_id' => $transaction->id,
        ]);
    }

    public function test_buyer_can_list_escrow_transactions(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/escrow-transactions?status=active')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function prepareFundableTransaction(): EscrowTransaction
    {
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        $transaction->update([
            'buyer_agreed' => true,
            'seller_agreed' => true,
            'status' => EscrowStatus::AWAITING_FUNDING,
            'delivery_cost' => 1500,
            'delivery_address' => '12 Admiralty Way, Lekki, Lagos',
            'payment_received' => false,
            'seller_shipped_item' => false,
            'buyer_confirmed_item_delivery' => false,
            'seller_received_payment' => false,
            'transaction_complete' => false,
        ]);

        return $transaction->fresh();
    }
}
