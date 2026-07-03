<?php

namespace App\Modules\Selloff\Affiliate\Services;

use App\Models\User;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use Illuminate\Http\Request;

class AffiliateAttributionService
{
    public function __construct(
        private readonly AffiliateRateService $rates,
    ) {}

    public function linkIdFromCookie(?string $cookieValue): ?int
    {
        if ($cookieValue === null || $cookieValue === '' || ! ctype_digit($cookieValue)) {
            return null;
        }

        $linkId = (int) $cookieValue;

        return $linkId > 0 ? $linkId : null;
    }

    public function linkIdFromRequest(Request $request): ?int
    {
        return $this->linkIdFromCookie($request->cookie('aff_id'));
    }

    public function resolveLinkIdFromRequest(Request $request): ?int
    {
        $fromCookie = $this->linkIdFromRequest($request);
        if ($fromCookie) {
            return $fromCookie;
        }

        if ($request->filled('affiliate_link_id')) {
            return $this->linkIdFromCookie((string) $request->input('affiliate_link_id'));
        }

        return null;
    }

    /**
     * @param  iterable<int, object{product_id: int, total_price: float|string}>  $cartItems
     * @return array<string, mixed>
     */
    public function calculateForCart(iterable $cartItems, ?int $affiliateLinkId): array
    {
        $empty = [
            'id' => null,
            'referrer_id' => null,
            'seller_id' => null,
            'product_id' => null,
            'commission_rate' => 0,
            'commission' => 0,
            'discount_rate' => 0,
            'discount' => 0,
        ];

        if (! $affiliateLinkId) {
            return $empty;
        }

        $link = AffiliateLink::query()->with('product')->find($affiliateLinkId);

        if (! $link || ! $link->product) {
            return $empty;
        }

        $referrer = User::query()->find($link->referrer_id);

        if (! $referrer || ! $this->rates->canPromoteProduct($link->product, $referrer)) {
            return $empty;
        }

        foreach ($cartItems as $cartItem) {
            if ((int) $cartItem->product_id !== (int) $link->product_id) {
                continue;
            }

            $rateData = $this->rates->ratesForProduct($link->product);
            $commissionRate = (float) $rateData['commission_rate'];
            $discountRate = (float) $rateData['discount_rate'];
            $lineTotal = (float) $cartItem->total_price;

            $commission = 0.0;
            $discount = 0.0;

            if ($commissionRate > 0 && $commissionRate < 100) {
                $commission = round($lineTotal * $commissionRate / 100, 2);
            }

            if ($discountRate > 0 && $discountRate < 100) {
                $discount = round($lineTotal * $discountRate / 100, 2);
            }

            return [
                'id' => $link->id,
                'referrer_id' => $link->referrer_id,
                'seller_id' => $link->seller_id,
                'product_id' => $link->product_id,
                'commission_rate' => $commissionRate,
                'commission' => $commission,
                'discount_rate' => $discountRate,
                'discount' => $discount,
            ];
        }

        return $empty;
    }
}
