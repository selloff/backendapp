<?php

namespace App\Modules\Selloff\Promotion\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeaturedPromotionService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PaystackGateway $paystack,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        $settings = $this->settings->all();

        return [
            'price_per_day' => (float) ($settings['price_per_day'] ?? 1000),
            'price_per_month' => (float) ($settings['price_per_month'] ?? 25000),
            'free_product_promotion' => filter_var($settings['free_product_promotion'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function updatePricing(array $values): array
    {
        $this->settings->upsertMany([
            'price_per_day' => $values['price_per_day'],
            'price_per_month' => $values['price_per_month'],
            'free_product_promotion' => $values['free_product_promotion'] ?? false,
        ], 'featured_pricing');

        return $this->pricing();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(
        User $vendor,
        Product $product,
        string $planType,
        int $duration,
        string $paymentMethod,
    ): array {
        abort_unless((int) $product->vendor_id === (int) $vendor->id, 403, 'Product does not belong to this vendor.');

        $quote = $this->buildQuote($product, $planType, $duration);

        if ($quote['amount'] <= 0) {
            $transaction = $this->completePromotion($vendor, $product, $quote, $paymentMethod, 'completed');

            return $this->formatCheckoutResponse($transaction, $vendor);
        }

        return match ($paymentMethod) {
            'wallet_balance' => $this->checkoutWithWallet($vendor, $product, $quote),
            'bank_transfer' => $this->checkoutWithBankTransfer($vendor, $product, $quote),
            'paystack' => $this->checkoutWithPaystack($vendor, $product, $quote),
            default => throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method for promotion.'],
            ]),
        };
    }

    public function completePaystackPayment(User $vendor, PromotionTransaction $transaction, string $paymentReference): PromotionTransaction
    {
        abort_unless((int) $transaction->user_id === (int) $vendor->id, 403);
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');
        abort_unless($transaction->payment_method === 'paystack', 422, 'Transaction is not a Paystack payment.');

        $verified = $this->paystack->verify($paymentReference);
        $expectedKobo = (int) round(((float) $transaction->amount) * 100);
        $paidKobo = (int) ($verified->amount ?? 0);
        $currency = strtoupper((string) ($verified->currency ?? ''));

        if ($paidKobo !== $expectedKobo || $currency !== strtoupper((string) ($transaction->currency_code ?? 'NGN'))) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match promotion total.'],
            ]);
        }

        return DB::transaction(function () use ($transaction, $paymentReference) {
            $transaction->update([
                'payment_reference' => $paymentReference,
                'status' => 'completed',
            ]);

            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $product = $transaction->product;
            abort_if($product === null, 422, 'Product not found for promotion transaction.');

            $this->applyPromotionToProduct($product, $metadata);

            return $transaction->fresh(['product.translations']);
        });
    }

    public function approvePending(PromotionTransaction $transaction): PromotionTransaction
    {
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');

        $product = $transaction->product;
        abort_if($product === null, 422, 'Product not found for promotion transaction.');

        return DB::transaction(function () use ($transaction, $product) {
            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $this->applyPromotionToProduct($product, $metadata);
            $transaction->update(['status' => 'completed']);

            return $transaction->fresh(['product.translations']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resumePendingPayment(
        User $vendor,
        PromotionTransaction $transaction,
        ?string $paymentMethod = null,
    ): array {
        abort_unless((int) $transaction->user_id === (int) $vendor->id, 403);
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');

        $vendor = $vendor->fresh() ?? $vendor;
        $transaction->loadMissing('product');
        $method = $paymentMethod ?? $transaction->payment_method;

        if ($method === 'wallet_balance') {
            return $this->completePendingWithWallet($vendor, $transaction);
        }

        if ($method === 'paystack') {
            if ($transaction->payment_method !== 'paystack') {
                $reference = 'PRM-'.Str::upper(Str::random(12));
                $transaction->update([
                    'payment_method' => 'paystack',
                    'payment_reference' => $reference,
                ]);
            }

            return $this->resumePaystackPayment($vendor, $transaction->fresh());
        }

        if ($method === 'bank_transfer') {
            return $this->formatCheckoutResponse($transaction, $vendor);
        }

        throw ValidationException::withMessages([
            'payment_method' => ['This payment method cannot be resumed.'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function completePendingWithWallet(User $vendor, PromotionTransaction $transaction): array
    {
        $amount = (float) $transaction->amount;

        if ((float) $vendor->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance for promotion.'],
            ]);
        }

        return DB::transaction(function () use ($vendor, $transaction, $amount) {
            $newBalance = round((float) $vendor->wallet_balance - $amount, 2);
            $vendor->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $vendor->id,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Product promotion: '.($transaction->product?->slug ?? $transaction->product_id),
            ]);

            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $product = $transaction->product;
            abort_if($product === null, 422, 'Product not found for promotion transaction.');

            $this->applyPromotionToProduct($product, $metadata);
            $transaction->update([
                'status' => 'completed',
                'payment_method' => 'wallet_balance',
            ]);

            return $this->formatCheckoutResponse($transaction->fresh(), $vendor->fresh());
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resumePaystackPayment(User $vendor, PromotionTransaction $transaction): array
    {
        if (! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $reference = $transaction->payment_reference ?: ('PRM-'.Str::upper(Str::random(12)));
        if ($transaction->payment_reference !== $reference) {
            $transaction->update(['payment_reference' => $reference]);
        }

        $config = $this->paystack->enabledConfig();

        return $this->formatCheckoutResponse($transaction, $vendor, [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'] ?? '',
            'email' => $vendor->email,
            'amount_kobo' => (int) round(((float) $transaction->amount) * 100),
            'reference' => $reference,
            'currency' => $transaction->currency_code ?? 'NGN',
        ]);
    }

    /** @deprecated Use checkout() */
    public function promote(User $vendor, Product $product, string $planType, int $duration = 1): PromotionTransaction
    {
        $result = $this->checkout($vendor, $product, $planType, $duration, 'wallet_balance');

        return PromotionTransaction::query()->findOrFail($result['id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutWithWallet(User $vendor, Product $product, array $quote): array
    {
        if ((float) $vendor->wallet_balance < $quote['amount']) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance for promotion.'],
            ]);
        }

        $transaction = DB::transaction(function () use ($vendor, $product, $quote) {
            $amount = (float) $quote['amount'];
            $newBalance = round((float) $vendor->wallet_balance - $amount, 2);
            $vendor->update(['wallet_balance' => $newBalance]);

            WalletTransaction::query()->create([
                'user_id' => $vendor->id,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Product promotion: '.$product->slug,
            ]);

            return $this->completePromotion($vendor, $product, $quote, 'wallet_balance', 'completed');
        });

        return $this->formatCheckoutResponse($transaction, $vendor);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutWithBankTransfer(User $vendor, Product $product, array $quote): array
    {
        $transaction = $this->createPendingTransaction($vendor, $product, $quote, 'bank_transfer');

        return $this->formatCheckoutResponse($transaction, $vendor);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutWithPaystack(User $vendor, Product $product, array $quote): array
    {
        if (! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $reference = 'PRM-'.Str::upper(Str::random(12));
        $transaction = $this->createPendingTransaction($vendor, $product, $quote, 'paystack', $reference);
        $config = $this->paystack->enabledConfig();

        return $this->formatCheckoutResponse($transaction, $vendor, [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'] ?? '',
            'email' => $vendor->email,
            'amount_kobo' => (int) round(((float) $quote['amount']) * 100),
            'reference' => $reference,
            'currency' => $quote['currency_code'],
        ]);
    }

    private function createPendingTransaction(
        User $vendor,
        Product $product,
        array $quote,
        string $paymentMethod,
        ?string $paymentReference = null,
    ): PromotionTransaction {
        return PromotionTransaction::query()->create([
            'user_id' => $vendor->id,
            'product_id' => $product->id,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'amount' => $quote['amount'],
            'currency_code' => $quote['currency_code'],
            'status' => 'pending',
            'checkout_token' => (string) Str::uuid(),
            'purchased_plan' => $quote['purchased_plan'],
            'day_count' => $quote['day_count'],
            'metadata' => [
                'plan_type' => $quote['plan_type'],
                'duration' => $quote['duration'],
                'purchased_plan' => $quote['purchased_plan'],
                'day_count' => $quote['day_count'],
                'expires_at' => $quote['expires_at']->toIso8601String(),
            ],
        ]);
    }

    private function completePromotion(
        User $vendor,
        Product $product,
        array $quote,
        string $paymentMethod,
        string $status,
    ): PromotionTransaction {
        $this->applyPromotionToProduct($product, $quote);

        return PromotionTransaction::query()->create([
            'user_id' => $vendor->id,
            'product_id' => $product->id,
            'payment_method' => $paymentMethod,
            'amount' => $quote['amount'],
            'currency_code' => $quote['currency_code'],
            'status' => $status,
            'purchased_plan' => $quote['purchased_plan'],
            'day_count' => $quote['day_count'],
            'metadata' => [
                'plan_type' => $quote['plan_type'],
                'duration' => $quote['duration'],
                'purchased_plan' => $quote['purchased_plan'],
                'day_count' => $quote['day_count'],
                'expires_at' => $quote['expires_at']->toIso8601String(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function applyPromotionToProduct(Product $product, array $quote): void
    {
        $expiresAt = isset($quote['expires_at']) && is_string($quote['expires_at'])
            ? \Illuminate\Support\Carbon::parse($quote['expires_at'])
            : $quote['expires_at'];

        $product->update([
            'is_promoted' => true,
            'promoted_at' => now(),
            'promoted_until' => $expiresAt,
            'promote_plan' => $quote['purchased_plan'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuote(Product $product, string $planType, int $duration): array
    {
        $pricing = $this->pricing();
        $amount = match ($planType) {
            'daily' => $pricing['price_per_day'] * $duration,
            'monthly' => $pricing['price_per_month'] * $duration,
            default => throw ValidationException::withMessages(['plan_type' => ['Invalid promotion plan type.']]),
        };

        if ($pricing['free_product_promotion']) {
            $amount = 0;
        }

        $dayCount = $planType === 'monthly' ? $duration * 30 : $duration;
        $purchasedPlan = $planType === 'monthly'
            ? "Monthly plan ({$dayCount} days)"
            : "Daily plan ({$duration} days)";

        $expiresAt = $planType === 'monthly'
            ? now()->addMonths($duration)
            : now()->addDays($duration);

        return [
            'plan_type' => $planType,
            'duration' => $duration,
            'day_count' => $dayCount,
            'purchased_plan' => $purchasedPlan,
            'amount' => $amount,
            'currency_code' => $product->currency_code ?? 'NGN',
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $action
     * @return array<string, mixed>
     */
    private function formatCheckoutResponse(
        PromotionTransaction $transaction,
        User $vendor,
        ?array $action = null,
    ): array {
        return [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency_code' => $transaction->currency_code,
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'product_id' => $transaction->product_id,
            'requires_action' => $action !== null,
            'action' => $action,
            'wallet_balance' => (float) $vendor->wallet_balance,
        ];
    }
}
