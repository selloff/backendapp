<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowLedgerEntry;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('manual funding records ledger hold', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $transaction = prepareFundableTransaction_in_EscrowRelease();

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
});

test('delivery confirm schedules release and command credits vendor earning', function () {
    app(PlatformSettingsService::class)->upsertMany(['escrow_inspection_days' => 0]);

    $transaction = prepareFundableTransaction_in_EscrowRelease();
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
    expect($transaction->transaction_complete)->toBeTrue();
    expect($transaction->status)->toBe(EscrowStatus::COMPLETED);

    $this->assertDatabaseHas('vendor_earnings', [
        'escrow_transaction_id' => $transaction->id,
        'seller_id' => $transaction->seller_id,
    ]);
});

test('dispute blocks scheduled release until admin resolves', function () {
    app(PlatformSettingsService::class)->upsertMany(['escrow_inspection_days' => 0]);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $transaction = prepareFundableTransaction_in_EscrowRelease();
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
});

test('buyer can list escrow transactions', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/escrow-transactions?status=active')
        ->assertOk()
        ->assertJsonPath('success', true);
});

function prepareFundableTransaction_in_EscrowRelease(): EscrowTransaction
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
