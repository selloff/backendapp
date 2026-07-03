<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Models\User;
use App\Modules\Selloff\Escrow\Models\EscrowLedgerEntry;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EscrowFundingService
{
    public function __construct(
        private readonly EscrowWorkflowService $workflow,
        private readonly EscrowPricingService $pricing,
        private readonly EscrowNotificationService $notifications,
    ) {}

    public function markFundedManual(
        EscrowTransaction $transaction,
        ?User $admin = null,
        ?string $paymentReference = null,
        ?string $note = null,
    ): EscrowTransaction {
        if ($transaction->payment_received) {
            return $transaction;
        }

        $this->workflow->assertBothAgreed($transaction);
        $this->workflow->assertDeliveryConfigured($transaction);
        $this->assertAwaitingFunding($transaction);

        return $this->applyFunding(
            $transaction,
            'manual',
            $paymentReference ?? ('MANUAL-'.$transaction->ref),
            $admin?->id,
            'admin',
            $note,
        );
    }

    public function payWithWallet(User $buyer, EscrowTransaction $transaction): EscrowTransaction
    {
        abort_unless($buyer->id === $transaction->buyer_id, 403, 'Only the buyer can pay for this escrow.');
        $this->workflow->assertBothAgreed($transaction);
        $this->workflow->assertDeliveryConfigured($transaction);
        $this->assertAwaitingFunding($transaction);

        if ($transaction->payment_received) {
            throw ValidationException::withMessages([
                'payment' => ['This escrow transaction is already funded.'],
            ]);
        }

        $pricing = $this->pricing->resolvePricing($transaction);
        $amount = $pricing['total_amount'];

        if ((float) $buyer->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance.'],
            ]);
        }

        return DB::transaction(function () use ($buyer, $transaction, $amount) {
            $newBalance = round((float) $buyer->wallet_balance - $amount, 2);
            $buyer->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $buyer->id,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Escrow payment: '.$transaction->ref,
            ]);

            return $this->applyFunding(
                $transaction->fresh(),
                'wallet_balance',
                'WALLET-'.$transaction->ref,
                $buyer->id,
                'buyer',
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function initPaystackPayment(User $buyer, EscrowTransaction $transaction, \App\Modules\Selloff\Payment\Gateways\PaystackGateway $paystack): array
    {
        abort_unless($buyer->id === $transaction->buyer_id, 403, 'Only the buyer can pay for this escrow.');
        $this->workflow->assertBothAgreed($transaction);
        $this->workflow->assertDeliveryConfigured($transaction);
        $this->assertAwaitingFunding($transaction);

        if ($transaction->payment_received) {
            throw ValidationException::withMessages([
                'payment' => ['This escrow transaction is already funded.'],
            ]);
        }

        if (! $paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $pricing = $this->pricing->resolvePricing($transaction);
        $reference = 'ESC-'.Str::upper(Str::random(12));
        $config = $paystack->enabledConfig();

        $metadata = $transaction->metadata ?? [];
        $metadata['pending_paystack_reference'] = $reference;
        $transaction->update([
            'payment_method' => 'paystack',
            'payment_reference' => $reference,
            'metadata' => $metadata,
        ]);

        $this->workflow->recordEvent($transaction, 'paystack_initiated', 'buyer', $buyer->id, [
            'reference' => $reference,
            'amount' => $pricing['total_amount'],
        ]);

        return [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'] ?? '',
            'email' => $buyer->email,
            'amount_kobo' => (int) round($pricing['total_amount'] * 100),
            'reference' => $reference,
            'currency' => $transaction->currency_code ?? 'NGN',
            'escrow_transaction_id' => $transaction->id,
        ];
    }

    public function completePaystackPayment(
        User $buyer,
        EscrowTransaction $transaction,
        string $paymentReference,
        \App\Modules\Selloff\Payment\Gateways\PaystackGateway $paystack,
    ): EscrowTransaction {
        abort_unless($buyer->id === $transaction->buyer_id, 403, 'Only the buyer can pay for this escrow.');

        if ($transaction->payment_received) {
            return $transaction;
        }

        $pricing = $this->pricing->resolvePricing($transaction);
        $verified = $paystack->verify($paymentReference);
        $paidAmount = round(((int) $verified->amount) / 100, 2);

        if (abs($paidAmount - $pricing['total_amount']) > 0.01) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match escrow total.'],
            ]);
        }

        return $this->applyFunding(
            $transaction,
            'paystack',
            $paymentReference,
            $buyer->id,
            'buyer',
        );
    }

    public function refundToBuyerWallet(EscrowTransaction $transaction, ?User $admin = null, ?string $note = null): EscrowTransaction
    {
        abort_unless($transaction->payment_received, 422, 'No funds to refund.');

        $buyer = $transaction->buyer;
        abort_if($buyer === null, 422, 'Buyer not found.');

        $pricing = $this->pricing->resolvePricing($transaction);
        $amount = $pricing['total_amount'];

        return DB::transaction(function () use ($transaction, $buyer, $amount, $admin, $note) {
            $newBalance = round((float) $buyer->wallet_balance + $amount, 2);
            $buyer->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $buyer->id,
                'type' => 'income',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Escrow refund: '.$transaction->ref,
            ]);

            EscrowLedgerEntry::query()->create([
                'escrow_transaction_id' => $transaction->id,
                'entry_type' => 'refund',
                'amount' => $amount,
                'currency_code' => $transaction->currency_code,
                'payment_method' => $transaction->payment_method,
                'payment_reference' => $transaction->payment_reference,
                'metadata' => $note ? ['note' => $note] : null,
            ]);

            $this->workflow->recordEvent($transaction, 'refunded_to_buyer_wallet', 'admin', $admin?->id, [
                'amount' => $amount,
                'note' => $note,
            ]);

            return $this->workflow->transition($transaction, EscrowStatus::REFUNDED, [
                'transaction_complete' => true,
            ]);
        });
    }

    private function applyFunding(
        EscrowTransaction $transaction,
        string $paymentMethod,
        string $paymentReference,
        ?int $actorId,
        string $actorType,
        ?string $note = null,
    ): EscrowTransaction {
        return DB::transaction(function () use ($transaction, $paymentMethod, $paymentReference, $actorId, $actorType, $note) {
            $pricing = $this->pricing->resolvePricing($transaction);

            EscrowLedgerEntry::query()->create([
                'escrow_transaction_id' => $transaction->id,
                'entry_type' => 'hold',
                'amount' => $pricing['total_amount'],
                'currency_code' => $transaction->currency_code,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'metadata' => $note ? ['note' => $note] : null,
            ]);

            $transaction->update([
                'payment_received' => true,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'funded_at' => now(),
                'status' => EscrowStatus::FUNDED,
            ]);

            $this->workflow->recordEvent($transaction, 'funded', $actorType, $actorId, [
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'amount' => $pricing['total_amount'],
                'note' => $note,
            ]);

            $transaction = $transaction->fresh();
            $this->notifications->sendBuyerPaidConfirmation($transaction);
            $this->notifications->sendSellerPaymentReceived($transaction);

            return $transaction;
        });
    }

    private function assertAwaitingFunding(EscrowTransaction $transaction): void
    {
        abort_if(EscrowStatus::isTerminal($transaction->status), 422, 'Escrow is not awaiting payment.');
        abort_if($transaction->payment_received, 422, 'Escrow is already funded.');
        $this->workflow->assertBothAgreed($transaction);
    }
}
