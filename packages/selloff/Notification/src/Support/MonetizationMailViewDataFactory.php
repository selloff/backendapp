<?php

namespace App\Modules\Selloff\Notification\Support;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class MonetizationMailViewDataFactory
{
    public function __construct(
        private readonly ProductMailViewDataFactory $products,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forMembership(
        User $user,
        MembershipPlan $plan,
        UserMembershipPlan $subscription,
        string $purchaseType,
        int $months,
        float $amountPaid,
    ): array {
        $base = $this->spaBase();
        $currency = (string) ($plan->currency_code ?? 'NGN');
        $planName = trim((string) ($plan->title ?? 'Membership plan'));

        return [
            'firstName' => $this->userFirstName($user),
            'planName' => $planName,
            'purchaseType' => $this->formatPurchaseType($purchaseType),
            'termMonths' => $months,
            'amountPaid' => $this->formatMoney($amountPaid, $currency),
            'expiresAt' => $this->formatDate($subscription->expires_at),
            'membershipUrl' => "{$base}/vendor/membership",
            'buttonText' => 'View membership',
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function forPromotion(
        Product $product,
        array $quote,
        float $amount,
        string $headline,
        string $summary,
    ): array {
        $productData = $this->products->forProduct($product);
        $currency = (string) ($quote['currency_code'] ?? $product->currency_code ?? 'NGN');
        $expiresAt = $this->resolveExpiry($quote);

        return array_merge($productData, [
            'headline' => $headline,
            'summary' => $summary,
            'planLabel' => (string) ($quote['purchased_plan'] ?? 'Promotion'),
            'amountPaid' => $this->formatMoney($amount, $currency),
            'expiresAt' => $this->formatDate($expiresAt),
            'buttonText' => 'View listing',
        ]);
    }

    private function userFirstName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));

        if ($first !== '') {
            return $first;
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function formatPurchaseType(string $purchaseType): string
    {
        return match ($purchaseType) {
            'extend' => 'Renewal / extension',
            'upgrade' => 'Plan upgrade',
            default => 'New subscription',
        };
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function resolveExpiry(array $quote): ?Carbon
    {
        $expiresAt = $quote['expires_at'] ?? null;

        if ($expiresAt instanceof DateTimeInterface) {
            return Carbon::instance($expiresAt);
        }

        if (is_string($expiresAt) && $expiresAt !== '') {
            return Carbon::parse($expiresAt);
        }

        return null;
    }

    private function formatDate(?DateTimeInterface $value): string
    {
        if ($value === null) {
            return '—';
        }

        return Carbon::instance($value)->format('M j, Y g:i A');
    }

    private function formatMoney(mixed $amount, ?string $currency = null): string
    {
        $formatted = number_format((float) $amount, 2);

        return trim($formatted.' '.($currency ?? ''));
    }

    private function spaBase(): string
    {
        return rtrim((string) config('selloff.spa_url', config('app.url')), '/');
    }
}
