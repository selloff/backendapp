<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipPurchaseService
{
    public function __construct(
        private readonly MembershipQuoteService $quoteService,
        private readonly MembershipActivationService $activationService,
        private readonly PaystackGateway $paystack,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function purchase(User $user, MembershipPlan $plan, int $months, string $paymentMethod): array
    {
        $quote = $this->quoteService->quote($user, $plan, $months);

        if ($quote['amount_due'] <= 0) {
            $transaction = $this->completePurchase($user, $plan, $quote, $paymentMethod, 'completed');

            return $this->formatCheckoutResponse($transaction, $user->fresh() ?? $user, $quote);
        }

        return match ($paymentMethod) {
            'wallet_balance' => $this->purchaseWithWallet($user, $plan, $quote),
            'bank_transfer' => $this->purchaseWithBankTransfer($user, $plan, $quote),
            'paystack' => $this->purchaseWithPaystack($user, $plan, $quote),
            default => throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method for membership purchase.'],
            ]),
        };
    }

    public function completePaystackPayment(
        User $user,
        MembershipTransaction $transaction,
        string $paymentReference,
    ): MembershipTransaction {
        abort_unless((int) $transaction->user_id === (int) $user->id, 403);
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');
        abort_unless($transaction->payment_method === 'paystack', 422, 'Transaction is not a Paystack payment.');

        $verified = $this->paystack->verify($paymentReference);
        $expectedKobo = (int) round(((float) $transaction->amount) * 100);
        $paidKobo = (int) ($verified->amount ?? 0);
        $currency = strtoupper((string) ($verified->currency ?? ''));

        if ($paidKobo !== $expectedKobo || $currency !== strtoupper((string) ($transaction->currency_code ?? 'NGN'))) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match membership total.'],
            ]);
        }

        return DB::transaction(function () use ($transaction, $paymentReference) {
            $transaction->update([
                'payment_reference' => $paymentReference,
                'status' => 'completed',
            ]);

            $this->activateFromTransaction($transaction);

            return $transaction->fresh()->load('membershipPlan');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resumePendingPayment(
        User $user,
        MembershipTransaction $transaction,
        ?string $paymentMethod = null,
    ): array {
        abort_unless((int) $transaction->user_id === (int) $user->id, 403);
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');

        $user = $user->fresh() ?? $user;
        $transaction->loadMissing('membershipPlan');
        $method = $paymentMethod ?? $transaction->payment_method;

        if ($method === 'wallet_balance') {
            return $this->completePendingWithWallet($user, $transaction);
        }

        if ($method === 'paystack') {
            if ($transaction->payment_method !== 'paystack') {
                $reference = 'MEM-'.Str::upper(Str::random(12));
                $transaction->update([
                    'payment_method' => 'paystack',
                    'payment_reference' => $reference,
                ]);
            }

            return $this->resumePaystackPayment($user, $transaction->fresh());
        }

        if ($method === 'bank_transfer') {
            return $this->formatCheckoutResponse($transaction, $user, $this->quoteFromTransaction($transaction));
        }

        throw ValidationException::withMessages([
            'payment_method' => ['This payment method cannot be resumed.'],
        ]);
    }

    public function approvePending(MembershipTransaction $transaction): MembershipTransaction
    {
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');

        $user = $transaction->user;
        abort_if($user === null || $transaction->membershipPlan === null, 422, 'Invalid membership transaction.');

        return DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => 'completed']);
            $this->activateFromTransaction($transaction);

            return $transaction->fresh()->load('membershipPlan');
        });
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function purchaseWithWallet(User $user, MembershipPlan $plan, array $quote): array
    {
        $amount = (float) $quote['amount_due'];

        if ((float) $user->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance.'],
            ]);
        }

        $transaction = DB::transaction(function () use ($user, $plan, $quote, $amount) {
            $newBalance = round((float) $user->wallet_balance - $amount, 2);
            $user->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Membership: '.$plan->title,
            ]);

            return $this->completePurchase($user, $plan, $quote, 'wallet_balance', 'completed');
        });

        return $this->formatCheckoutResponse($transaction, $user->fresh() ?? $user, $quote);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function purchaseWithBankTransfer(User $user, MembershipPlan $plan, array $quote): array
    {
        $transaction = $this->createPendingTransaction($user, $plan, $quote, 'bank_transfer');

        return $this->formatCheckoutResponse($transaction, $user, $quote);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function purchaseWithPaystack(User $user, MembershipPlan $plan, array $quote): array
    {
        if (! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $reference = 'MEM-'.Str::upper(Str::random(12));
        $transaction = $this->createPendingTransaction($user, $plan, $quote, 'paystack', $reference);
        $config = $this->paystack->enabledConfig();

        return $this->formatCheckoutResponse($transaction, $user, $quote, [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'] ?? '',
            'email' => $user->email,
            'amount_kobo' => (int) round(((float) $quote['amount_due']) * 100),
            'reference' => $reference,
            'currency' => $quote['currency_code'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function completePendingWithWallet(User $user, MembershipTransaction $transaction): array
    {
        $amount = (float) $transaction->amount;

        if ((float) $user->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance.'],
            ]);
        }

        return DB::transaction(function () use ($user, $transaction, $amount) {
            $newBalance = round((float) $user->wallet_balance - $amount, 2);
            $user->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Membership: '.($transaction->membershipPlan?->title ?? $transaction->membership_plan_id),
            ]);

            $transaction->update([
                'status' => 'completed',
                'payment_method' => 'wallet_balance',
            ]);

            $this->activateFromTransaction($transaction);

            return $this->formatCheckoutResponse(
                $transaction->fresh()->load('membershipPlan'),
                $user->fresh() ?? $user,
                $this->quoteFromTransaction($transaction),
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resumePaystackPayment(User $user, MembershipTransaction $transaction): array
    {
        if (! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $reference = $transaction->payment_reference ?: ('MEM-'.Str::upper(Str::random(12)));
        if ($transaction->payment_reference !== $reference) {
            $transaction->update(['payment_reference' => $reference]);
        }

        $config = $this->paystack->enabledConfig();

        return $this->formatCheckoutResponse(
            $transaction,
            $user,
            $this->quoteFromTransaction($transaction),
            [
                'type' => 'paystack_inline',
                'public_key' => $config['public_key'] ?? '',
                'email' => $user->email,
                'amount_kobo' => (int) round(((float) $transaction->amount) * 100),
                'reference' => $reference,
                'currency' => $transaction->currency_code ?? 'NGN',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function completePurchase(
        User $user,
        MembershipPlan $plan,
        array $quote,
        string $paymentMethod,
        string $status,
        ?string $paymentReference = null,
    ): MembershipTransaction {
        $transaction = $this->createTransactionRecord($user, $plan, $quote, $paymentMethod, $status, $paymentReference);
        $this->activationService->activate(
            $user,
            $plan,
            (string) $quote['purchase_type'],
            (int) $quote['months'],
            (float) $quote['amount_due'],
        );

        return $transaction->load('membershipPlan');
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function createPendingTransaction(
        User $user,
        MembershipPlan $plan,
        array $quote,
        string $paymentMethod,
        ?string $paymentReference = null,
    ): MembershipTransaction {
        return $this->createTransactionRecord($user, $plan, $quote, $paymentMethod, 'pending', $paymentReference);
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function createTransactionRecord(
        User $user,
        MembershipPlan $plan,
        array $quote,
        string $paymentMethod,
        string $status,
        ?string $paymentReference = null,
    ): MembershipTransaction {
        return MembershipTransaction::query()->create([
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'amount' => $quote['amount_due'],
            'amount_charged' => $quote['amount_due'],
            'gross_amount' => $quote['gross_amount'],
            'discount_amount' => $quote['discount_amount'],
            'credit_amount' => $quote['credit_amount'],
            'term_months' => $quote['months'],
            'purchase_type' => $quote['purchase_type'],
            'monthly_price_at_purchase' => $quote['monthly_price'],
            'currency_code' => $quote['currency_code'],
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'status' => $status,
            'checkout_token' => (string) Str::uuid(),
            'ip_address' => request()->ip(),
            'metadata' => [
                'quote' => $quote,
            ],
        ]);
    }

    private function activateFromTransaction(MembershipTransaction $transaction): void
    {
        $transaction->loadMissing(['user', 'membershipPlan']);
        $user = $transaction->user;
        $plan = $transaction->membershipPlan;
        abort_if($user === null || $plan === null, 422, 'Invalid membership transaction.');

        $payload = $this->activationService->activationPayloadFromTransaction($transaction);
        $this->activationService->activate(
            $user,
            $plan,
            $payload['purchase_type'],
            $payload['months'],
            $payload['amount_due'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteFromTransaction(MembershipTransaction $transaction): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        if (is_array($metadata['quote'] ?? null)) {
            return $metadata['quote'];
        }

        return [
            'months' => (int) ($transaction->term_months ?? 1),
            'purchase_type' => (string) ($transaction->purchase_type ?? 'new'),
            'amount_due' => (float) ($transaction->amount_charged ?? $transaction->amount ?? 0),
            'gross_amount' => (float) ($transaction->gross_amount ?? $transaction->amount ?? 0),
            'discount_amount' => (float) ($transaction->discount_amount ?? 0),
            'credit_amount' => (float) ($transaction->credit_amount ?? 0),
            'monthly_price' => (float) ($transaction->monthly_price_at_purchase ?? 0),
            'currency_code' => $transaction->currency_code ?? 'NGN',
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>|null  $action
     * @return array<string, mixed>
     */
    private function formatCheckoutResponse(
        MembershipTransaction $transaction,
        User $user,
        array $quote,
        ?array $action = null,
    ): array {
        return [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code ?? $quote['currency_code'] ?? 'NGN',
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'purchase_type' => $quote['purchase_type'] ?? $transaction->purchase_type,
            'term_months' => $quote['months'] ?? $transaction->term_months,
            'gross_amount' => $quote['gross_amount'] ?? $transaction->gross_amount,
            'discount_amount' => $quote['discount_amount'] ?? $transaction->discount_amount,
            'credit_amount' => $quote['credit_amount'] ?? $transaction->credit_amount,
            'plan' => $transaction->membershipPlan,
            'requires_action' => $action !== null,
            'action' => $action,
            'wallet_balance' => (float) $user->wallet_balance,
        ];
    }
}
