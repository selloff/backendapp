<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Models\User;
use App\Modules\Selloff\Escrow\Models\EscrowLedgerEntry;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use Illuminate\Support\Facades\DB;

class EscrowReleaseService
{
    public function __construct(
        private readonly EscrowWorkflowService $workflow,
        private readonly EscrowPricingService $pricing,
    ) {}

    public function scheduleAfterDeliveryConfirm(EscrowTransaction $transaction, ?int $buyerId = null): EscrowTransaction
    {
        abort_if($transaction->status === EscrowStatus::DISPUTED, 422, 'Cannot schedule release while disputed.');

        $inspectionDays = $this->workflow->inspectionDays();
        $releaseAt = $inspectionDays > 0 ? now()->addDays($inspectionDays) : now();

        $transaction->update([
            'buyer_confirmed_item_delivery' => true,
            'accepted_at' => now(),
            'release_scheduled_at' => $releaseAt,
            'status' => EscrowStatus::AWAITING_ACCEPTANCE,
        ]);

        $this->workflow->recordEvent($transaction, 'buyer_confirmed_delivery', 'buyer', $buyerId, [
            'release_scheduled_at' => $releaseAt->toIso8601String(),
            'inspection_days' => $inspectionDays,
        ]);

        if ($inspectionDays === 0) {
            return $this->releaseNow($transaction->fresh(), null, false);
        }

        return $transaction->fresh();
    }

    public function releaseNow(EscrowTransaction $transaction, ?User $admin = null, bool $adminOverride = true): EscrowTransaction
    {
        if ($transaction->transaction_complete || $transaction->status === EscrowStatus::COMPLETED) {
            return $transaction;
        }

        abort_if($transaction->status === EscrowStatus::DISPUTED, 422, 'Cannot release while disputed.');

        if ($adminOverride && $admin !== null) {
            abort_unless($transaction->payment_received, 422, 'Escrow must be funded before release.');
        } else {
            abort_unless($transaction->buyer_confirmed_item_delivery, 422, 'Buyer must confirm delivery before release.');
            abort_if(
                $transaction->release_scheduled_at !== null && $transaction->release_scheduled_at->isFuture(),
                422,
                'Release is still in the inspection window.',
            );
        }

        return DB::transaction(function () use ($transaction, $admin, $adminOverride) {
            $transaction->update(['status' => EscrowStatus::RELEASING]);
            $pricing = $this->pricing->resolvePricing($transaction);

            VendorEarning::query()->firstOrCreate(
                ['escrow_transaction_id' => $transaction->id],
                [
                    'seller_id' => $transaction->seller_id,
                    'earned_amount' => $pricing['seller_amount'],
                    'sale_amount' => $pricing['item_price'],
                    'commission_rate' => $pricing['commission_rate'],
                    'commission_amount' => $pricing['commission_amount'],
                    'currency_code' => $transaction->currency_code,
                    'exchange_rate' => 1,
                ],
            );

            EscrowLedgerEntry::query()->create([
                'escrow_transaction_id' => $transaction->id,
                'entry_type' => 'release',
                'amount' => $pricing['seller_amount'],
                'currency_code' => $transaction->currency_code,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'metadata' => ['commission_amount' => $pricing['commission_amount']],
            ]);

            if ($pricing['commission_amount'] > 0) {
                EscrowLedgerEntry::query()->create([
                    'escrow_transaction_id' => $transaction->id,
                    'entry_type' => 'fee',
                    'amount' => $pricing['commission_amount'],
                    'currency_code' => $transaction->currency_code,
                    'metadata' => ['commission_rate' => $pricing['commission_rate']],
                ]);
            }

            $transaction->update([
                'seller_received_payment' => true,
                'transaction_complete' => true,
                'released_at' => now(),
                'status' => EscrowStatus::COMPLETED,
            ]);

            $this->workflow->recordEvent(
                $transaction,
                $adminOverride && $admin ? 'released_by_admin' : 'released_to_seller',
                $adminOverride && $admin ? 'admin' : 'system',
                $admin?->id,
                ['seller_amount' => $pricing['seller_amount']],
            );

            return $transaction->fresh();
        });
    }

    public function processDueReleases(): int
    {
        $due = EscrowTransaction::query()
            ->where('status', EscrowStatus::AWAITING_ACCEPTANCE)
            ->where('buyer_confirmed_item_delivery', true)
            ->whereNotNull('release_scheduled_at')
            ->where('release_scheduled_at', '<=', now())
            ->where('transaction_complete', false)
            ->get();

        $count = 0;
        foreach ($due as $transaction) {
            if ($transaction->status === EscrowStatus::DISPUTED) {
                continue;
            }

            $this->releaseNow($transaction, null, false);
            $count++;
        }

        return $count;
    }

    public function releaseHeldOrderEscrow(EscrowTransaction $transaction): EscrowTransaction
    {
        abort_unless($transaction->status === EscrowStatus::HELD, 422, 'Not a held checkout escrow row.');
        abort_if($transaction->order_id === null, 422, 'Held escrow must be linked to an order.');

        if ($transaction->transaction_complete) {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction) {
            $pricing = $this->pricing->resolvePricing($transaction);

            $existingEarning = VendorEarning::query()
                ->where('order_id', $transaction->order_id)
                ->where('seller_id', $transaction->seller_id)
                ->exists();

            if (! $existingEarning) {
                VendorEarning::query()->firstOrCreate(
                    ['escrow_transaction_id' => $transaction->id],
                    [
                        'seller_id' => $transaction->seller_id,
                        'order_id' => $transaction->order_id,
                        'earned_amount' => $pricing['seller_amount'],
                        'sale_amount' => $pricing['item_price'],
                        'commission_rate' => $pricing['commission_rate'],
                        'commission_amount' => $pricing['commission_amount'],
                        'currency_code' => $transaction->currency_code,
                        'exchange_rate' => 1,
                    ],
                );
            }

            EscrowLedgerEntry::query()->create([
                'escrow_transaction_id' => $transaction->id,
                'entry_type' => 'release',
                'amount' => $pricing['seller_amount'],
                'currency_code' => $transaction->currency_code,
                'metadata' => ['source' => 'checkout_held'],
            ]);

            $transaction->update([
                'seller_received_payment' => true,
                'transaction_complete' => true,
                'released_at' => now(),
                'status' => EscrowStatus::COMPLETED,
            ]);

            $this->workflow->recordEvent($transaction, 'checkout_held_released', 'system', null, [
                'order_id' => $transaction->order_id,
            ]);

            return $transaction->fresh();
        });
    }
}
