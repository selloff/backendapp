<?php

namespace App\Support\Gtm;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Illuminate\Http\Request;

class ServicePaymentGtmService
{
    public function __construct(
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function sellerSubscription(MembershipTransaction $transaction, User $user): array
    {
        $transaction->loadMissing('membershipPlan');
        $plan = $transaction->membershipPlan;
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        return $this->factory->list('seller_subscription', [
            'transaction_id' => (string) $transaction->id,
            'plan_id' => (string) ($transaction->membership_plan_id ?? ''),
            'plan_name' => (string) ($plan?->title ?? ''),
            'plan_request_type' => (string) ($metadata['plan_request_type'] ?? 'new'),
            'user_id' => (string) $user->id,
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'username' => (string) ($user->username ?? $user->slug ?? ''),
            'phone' => (string) ($user->phone_number ?? ''),
            'email' => (string) $user->email,
            'amount' => (float) $transaction->amount,
            'payment_method' => (string) ($transaction->payment_method ?? ''),
            'payment_status' => (string) ($transaction->status ?? ''),
            'date' => $transaction->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function featuredListingPurchase(
        PromotionTransaction $transaction,
        User $vendor,
        Product $product,
        Request $request,
    ): array {
        $product->loadMissing(['translations', 'category.parent.translations', 'category.translations', 'vendor.state']);
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();
        $category = $product->category;
        $categoryTranslation = $category?->translations->firstWhere('locale', 'en')
            ?? $category?->translations->first();
        $parent = $category?->parent;
        $parentTranslation = $parent?->translations->firstWhere('locale', 'en')
            ?? $parent?->translations->first();
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];

        return $this->factory->list('featured_listing_purchase', [
            'item_id' => (string) $product->id,
            'item_title' => (string) ($translation?->title ?? ''),
            'plan_id' => (string) ($metadata['plan_id'] ?? $transaction->promotion_plan_id ?? ''),
            'plan_name' => (string) ($metadata['plan_name'] ?? $transaction->promotion_type ?? ''),
            'amount' => (float) $transaction->amount,
            'payment_method' => (string) ($transaction->payment_method ?? ''),
            'item_category_id' => (string) ($product->category_id ?? ''),
            'item_category' => (string) ($parentTranslation?->name ?? $categoryTranslation?->name ?? ''),
            'item_sub_category' => (string) ($parent ? ($categoryTranslation?->name ?? '') : ''),
            'item_price' => (float) ($product->price_discounted ?? $product->price),
            'item_location' => (string) ($vendor->state?->name ?? ''),
            'seller_id' => (string) $vendor->id,
            'seller_name' => trim($vendor->first_name.' '.$vendor->last_name),
            'seller_username' => (string) ($vendor->username ?? $vendor->slug ?? ''),
            'seller_phone' => (string) ($vendor->phone_number ?? ''),
            'seller_email' => (string) $vendor->email,
        ]);
    }
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function attachMembershipCheckoutGtm(array $payload, User $user): array
    {
        if (($payload['status'] ?? '') !== 'completed' || ! isset($payload['id'])) {
            return $payload;
        }

        $transaction = MembershipTransaction::query()->find($payload['id']);
        if (! $transaction) {
            return $payload;
        }

        $payload['gtm_events'] = $this->markMembershipGtmDelivered($transaction, $user);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function attachPromotionCheckoutGtm(array $payload, User $user, Product $product, Request $request): array
    {
        if (($payload['status'] ?? '') !== 'completed' || ! isset($payload['id'])) {
            return $payload;
        }

        $transaction = PromotionTransaction::query()->find($payload['id']);
        if (! $transaction) {
            return $payload;
        }

        $payload['gtm_events'] = $this->markPromotionGtmDelivered($transaction, $user, $product, $request);

        return $payload;
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function deliverMembershipGtmIfNeeded(MembershipTransaction $transaction, User $user): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        if (($metadata['gtm_delivered_at'] ?? null) !== null) {
            return [];
        }

        return $this->markMembershipGtmDelivered($transaction, $user);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function deliverPromotionGtmIfNeeded(
        PromotionTransaction $transaction,
        User $user,
        Product $product,
        Request $request,
    ): array {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        if (($metadata['gtm_delivered_at'] ?? null) !== null) {
            return [];
        }

        return $this->markPromotionGtmDelivered($transaction, $user, $product, $request);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    private function markMembershipGtmDelivered(MembershipTransaction $transaction, User $user): array
    {
        $events = $this->sellerSubscription($transaction, $user);
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $transaction->update([
            'metadata' => array_merge($metadata, ['gtm_delivered_at' => now()->toIso8601String()]),
        ]);

        return $events;
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    private function markPromotionGtmDelivered(
        PromotionTransaction $transaction,
        User $user,
        Product $product,
        Request $request,
    ): array {
        $events = $this->featuredListingPurchase($transaction, $user, $product, $request);
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $transaction->update([
            'metadata' => array_merge($metadata, ['gtm_delivered_at' => now()->toIso8601String()]),
        ]);

        return $events;
    }
}
