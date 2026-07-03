<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Support\Gtm\ServicePaymentGtmService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServicePaymentCompletionService
{
    public function __construct(
        private readonly ServicePaymentGtmService $gtm,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(User $user, string $serviceType, int $transactionId, Request $request): array
    {
        return match ($serviceType) {
            'membership' => $this->resolveMembership($user, $transactionId),
            'promote' => $this->resolvePromotion($user, $transactionId, $request),
            'add_funds' => $this->resolveWalletDeposit($user, $transactionId),
            default => throw ValidationException::withMessages([
                'service_type' => ['Unsupported service type.'],
            ]),
        };
    }

    /** @return array<string, mixed> */
    private function resolveMembership(User $user, int $transactionId): array
    {
        $transaction = MembershipTransaction::query()
            ->with('membershipPlan')
            ->where('user_id', $user->id)
            ->findOrFail($transactionId);

        $isPending = $transaction->status === 'pending';

        $payload = [
            'service_type' => 'membership',
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency_code' => 'NGN',
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'is_pending' => $isPending,
            'transaction_number' => (string) $transaction->id,
            'title' => $transaction->membershipPlan?->title ?? 'Membership plan',
            'message' => $isPending
                ? 'Your membership payment is pending bank transfer approval.'
                : 'Your membership plan is now active.',
            'plan' => $transaction->membershipPlan ? [
                'id' => $transaction->membershipPlan->id,
                'title' => $transaction->membershipPlan->title,
            ] : null,
        ];

        if (! $isPending) {
            $gtmEvents = $this->gtm->deliverMembershipGtmIfNeeded($transaction, $user);
            if ($gtmEvents !== []) {
                $payload['gtm_events'] = $gtmEvents;
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function resolvePromotion(User $user, int $transactionId, Request $request): array
    {
        $transaction = PromotionTransaction::query()
            ->with(['product.translations'])
            ->where('user_id', $user->id)
            ->findOrFail($transactionId);

        $isPending = $transaction->status === 'pending';

        $payload = [
            'service_type' => 'promote',
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code ?? 'NGN',
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'is_pending' => $isPending,
            'transaction_number' => (string) $transaction->id,
            'title' => 'Product promotion',
            'message' => $isPending
                ? 'Your promotion payment is pending bank transfer approval.'
                : 'Your product promotion payment was completed.',
            'product' => $transaction->product ? [
                'id' => $transaction->product->id,
                'slug' => $transaction->product->slug,
                'title' => $transaction->product->translations->firstWhere('locale', 'en')?->title
                    ?? $transaction->product->translations->first()?->title,
            ] : null,
        ];

        if (! $isPending && $transaction->product) {
            $gtmEvents = $this->gtm->deliverPromotionGtmIfNeeded($transaction, $user, $transaction->product, $request);
            if ($gtmEvents !== []) {
                $payload['gtm_events'] = $gtmEvents;
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function resolveWalletDeposit(User $user, int $transactionId): array
    {
        $deposit = WalletDeposit::query()
            ->where('user_id', $user->id)
            ->findOrFail($transactionId);

        $isPending = $deposit->status === 'pending';

        return [
            'service_type' => 'add_funds',
            'transaction_id' => $deposit->id,
            'amount' => $deposit->amount,
            'currency_code' => 'NGN',
            'payment_method' => $deposit->payment_method,
            'status' => $deposit->status,
            'is_pending' => $isPending,
            'transaction_number' => $deposit->transaction_id ?? (string) $deposit->id,
            'title' => 'Wallet deposit',
            'message' => $isPending
                ? ($deposit->payment_method === 'paystack'
                    ? 'Complete your Paystack payment to fund your wallet.'
                    : 'Your wallet deposit is pending bank transfer approval.')
                : 'Your wallet deposit was completed.',
        ];
    }
}
