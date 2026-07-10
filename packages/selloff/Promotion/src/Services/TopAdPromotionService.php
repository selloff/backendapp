<?php

namespace App\Modules\Selloff\Promotion\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Services\PromotionEmailService;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TopAdPromotionService
{
    public const KIND = 'top_ad';

  /** @var list<int> */
    public const ALLOWED_DURATIONS = [7, 14, 30, 60];

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PaystackGateway $paystack,
        private readonly PromotionEmailService $promotionEmails,
    ) {}

    /**
     * @return array{options: list<array<string, mixed>>, badge_label: string, stack_weight_bonus: int}
     */
    public function pricing(): array
    {
        $settings = $this->settings->all();

        $options = [];
        foreach (self::ALLOWED_DURATIONS as $days) {
            $options[] = [
                'duration_days' => $days,
                'price' => (float) ($settings["top_ad_price_{$days}"] ?? $this->defaultPriceForDays($days)),
                'rank_weight' => (int) ($settings["top_ad_weight_{$days}"] ?? $this->defaultWeightForDays($days)),
                'label' => "{$days} days",
            ];
        }

        return [
            'options' => $options,
            'badge_label' => (string) ($settings['top_ad_badge_label'] ?? 'TOP'),
            'stack_weight_bonus' => max(0, (int) ($settings['top_ad_stack_weight_bonus'] ?? 75)),
            'currency_code' => 'NGN',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(User $vendor, Product $product, int $durationDays, string $paymentMethod): array
    {
        abort_unless((int) $product->vendor_id === (int) $vendor->id, 403, 'Product does not belong to this vendor.');

        if ($product->is_draft || $product->status === 'draft' || $product->is_deleted) {
            throw ValidationException::withMessages([
                'product' => ['Only published listings can receive a TOP Ad boost.'],
            ]);
        }

        $quote = $this->buildQuote($product, $durationDays);

        if ($quote['amount'] <= 0) {
            $transaction = $this->completeTopAd($vendor, $product, $quote, $paymentMethod, 'completed');

            return $this->formatCheckoutResponse($transaction, $vendor);
        }

        return match ($paymentMethod) {
            'wallet_balance' => $this->checkoutWithWallet($vendor, $product, $quote),
            'bank_transfer' => $this->checkoutWithBankTransfer($vendor, $product, $quote),
            'paystack' => $this->checkoutWithPaystack($vendor, $product, $quote),
            default => throw ValidationException::withMessages([
                'payment_method' => ['Unsupported payment method for TOP Ad.'],
            ]),
        };
    }

    public function completePaystackPayment(User $vendor, PromotionTransaction $transaction, string $paymentReference): PromotionTransaction
    {
        $this->assertTopAdTransaction($transaction);
        abort_unless((int) $transaction->user_id === (int) $vendor->id, 403);
        abort_unless($transaction->status === 'pending', 422, 'Transaction is not pending.');
        abort_unless($transaction->payment_method === 'paystack', 422, 'Transaction is not a Paystack payment.');

        $verified = $this->paystack->verify($paymentReference);
        $expectedKobo = (int) round(((float) $transaction->amount) * 100);
        $paidKobo = (int) ($verified->amount ?? 0);
        $currency = strtoupper((string) ($verified->currency ?? ''));

        if ($paidKobo !== $expectedKobo || $currency !== strtoupper((string) ($transaction->currency_code ?? 'NGN'))) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match TOP Ad total.'],
            ]);
        }

        return DB::transaction(function () use ($transaction, $paymentReference) {
            $transaction->update([
                'payment_reference' => $paymentReference,
                'status' => 'completed',
            ]);

            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $product = $transaction->product;
            abort_if($product === null, 422, 'Product not found for TOP Ad transaction.');

            $this->applyTopAdToProduct($product, $metadata, (float) $transaction->amount);

            return $transaction->fresh(['product.translations']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function resumePendingPayment(User $vendor, PromotionTransaction $transaction, ?string $paymentMethod = null): array
    {
        $this->assertTopAdTransaction($transaction);
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
                $reference = 'TAD-'.Str::upper(Str::random(12));
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

    public function isTopAdTransaction(PromotionTransaction $transaction): bool
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        return ($metadata['kind'] ?? null) === self::KIND;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function applyTopAdToProduct(Product $product, array $metadata, ?float $amount = null): Product
    {
        $durationDays = (int) ($metadata['duration_days'] ?? 0);
        $tierWeight = (int) ($metadata['rank_weight'] ?? 0);
        $stackBonus = max(0, (int) ($metadata['stack_weight_bonus'] ?? $this->pricing()['stack_weight_bonus']));
        $badgeLabel = (string) ($metadata['badge_label'] ?? $this->pricing()['badge_label']);

        $currentExpiry = $product->top_boost_expires_at;
        $base = $currentExpiry !== null && $currentExpiry->isFuture()
            ? $currentExpiry->copy()
            : now();

        $expiresAt = $base->addDays(max(1, $durationDays));
        $stackCount = (int) $product->top_boost_stack_count + 1;
        $weight = max($tierWeight, (int) $product->top_boost_weight) + (($stackCount - 1) * $stackBonus);

        $product->forceFill([
            'top_boost_active' => true,
            'top_boost_expires_at' => $expiresAt,
            'top_boost_weight' => $weight,
            'top_boost_badge_label' => $badgeLabel,
            'top_boost_stack_count' => $stackCount,
            'last_bumped_at' => now(),
        ])->save();

        $product = $product->fresh();
        $resolvedAmount = (float) ($amount ?? $metadata['amount'] ?? 0);
        $quote = array_merge(['currency_code' => $product->currency_code ?? 'NGN'], $metadata);
        $this->promotionEmails->queueVipBoost($product, $quote, $resolvedAmount);

        return $product;
    }

    private function assertTopAdTransaction(PromotionTransaction $transaction): void
    {
        if (! $this->isTopAdTransaction($transaction)) {
            throw ValidationException::withMessages([
                'transaction' => ['This transaction is not a TOP Ad purchase.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuote(Product $product, int $durationDays): array
    {
        if (! in_array($durationDays, self::ALLOWED_DURATIONS, true)) {
            throw ValidationException::withMessages([
                'duration_days' => ['TOP Ad duration must be 7, 14, 30, or 60 days.'],
            ]);
        }

        $pricing = $this->pricing();
        $option = collect($pricing['options'])->firstWhere('duration_days', $durationDays);
        abort_if($option === null, 422, 'TOP Ad pricing is not configured for this duration.');

        $expiresAt = $this->resolveNewExpiry($product, $durationDays);

        return [
            'kind' => self::KIND,
            'duration_days' => $durationDays,
            'day_count' => $durationDays,
            'rank_weight' => (int) $option['rank_weight'],
            'stack_weight_bonus' => (int) $pricing['stack_weight_bonus'],
            'badge_label' => (string) $pricing['badge_label'],
            'purchased_plan' => "TOP Ad ({$durationDays} days)",
            'amount' => (float) $option['price'],
            'currency_code' => $product->currency_code ?? $pricing['currency_code'],
            'expires_at' => $expiresAt,
        ];
    }

    private function resolveNewExpiry(Product $product, int $durationDays): Carbon
    {
        $currentExpiry = $product->top_boost_expires_at;
        $base = $currentExpiry !== null && $currentExpiry->isFuture()
            ? $currentExpiry->copy()
            : now();

        return $base->addDays($durationDays);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutWithWallet(User $vendor, Product $product, array $quote): array
    {
        if ((float) $vendor->wallet_balance < $quote['amount']) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance for TOP Ad.'],
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
                'description' => 'TOP Ad: '.$product->slug,
            ]);

            return $this->completeTopAd($vendor, $product, $quote, 'wallet_balance', 'completed');
        });

        return $this->formatCheckoutResponse($transaction, $vendor->fresh());
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

        $reference = 'TAD-'.Str::upper(Str::random(12));
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

    /**
     * @return array<string, mixed>
     */
    private function completePendingWithWallet(User $vendor, PromotionTransaction $transaction): array
    {
        $amount = (float) $transaction->amount;

        if ((float) $vendor->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance for TOP Ad.'],
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
                'description' => 'TOP Ad: '.($transaction->product?->slug ?? $transaction->product_id),
            ]);

            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $product = $transaction->product;
            abort_if($product === null, 422, 'Product not found for TOP Ad transaction.');

            $this->applyTopAdToProduct($product, $metadata, (float) $transaction->amount);
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

        $reference = $transaction->payment_reference ?: ('TAD-'.Str::upper(Str::random(12)));
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
            'metadata' => $this->metadataPayload($quote),
        ]);
    }

    private function completeTopAd(
        User $vendor,
        Product $product,
        array $quote,
        string $paymentMethod,
        string $status,
    ): PromotionTransaction {
        $this->applyTopAdToProduct($product, $quote);

        return PromotionTransaction::query()->create([
            'user_id' => $vendor->id,
            'product_id' => $product->id,
            'payment_method' => $paymentMethod,
            'amount' => $quote['amount'],
            'currency_code' => $quote['currency_code'],
            'status' => $status,
            'purchased_plan' => $quote['purchased_plan'],
            'day_count' => $quote['day_count'],
            'metadata' => $this->metadataPayload($quote),
        ]);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function metadataPayload(array $quote): array
    {
        return [
            'kind' => self::KIND,
            'duration_days' => $quote['duration_days'],
            'day_count' => $quote['day_count'],
            'rank_weight' => $quote['rank_weight'],
            'stack_weight_bonus' => $quote['stack_weight_bonus'],
            'badge_label' => $quote['badge_label'],
            'purchased_plan' => $quote['purchased_plan'],
            'expires_at' => $quote['expires_at'] instanceof Carbon
                ? $quote['expires_at']->toIso8601String()
                : (string) $quote['expires_at'],
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
            'purchased_plan' => $transaction->purchased_plan,
            'duration_days' => $transaction->metadata['duration_days'] ?? $transaction->day_count,
        ];
    }

    private function defaultPriceForDays(int $days): float
    {
        return match ($days) {
            7 => 1500,
            14 => 2800,
            30 => 5000,
            60 => 9000,
            default => 1500,
        };
    }

    private function defaultWeightForDays(int $days): int
    {
        return match ($days) {
            7 => 100,
            14 => 175,
            30 => 300,
            60 => 500,
            default => 100,
        };
    }
}
