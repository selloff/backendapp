<?php

namespace App\Modules\Selloff\Cart\Services;

use App\Models\User;
use App\Support\Decimal;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Cart\Models\CartItem;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductPricing;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function resolveCart(User $user): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'currency_code' => 'NGN',
                'currency_code_base' => 'NGN',
                'exchange_rate' => 1,
                'shipping_cost' => 0,
            ],
        );

        return $this->reloadCart($cart);
    }

    /**
     * @return array{cart: Cart, guest_token: string}
     */
    public function resolveGuestCart(?string $token = null): array
    {
        if ($token) {
            $cart = Cart::query()
                ->where('guest_token', $token)
                ->whereNull('user_id')
                ->first();

            if ($cart) {
                return [
                    'cart' => $this->reloadCart($cart),
                    'guest_token' => $token,
                ];
            }
        }

        $guestToken = (string) Str::uuid();

        $cart = Cart::query()->create([
            'guest_token' => $guestToken,
            'currency_code' => 'NGN',
            'currency_code_base' => 'NGN',
            'exchange_rate' => 1,
            'shipping_cost' => 0,
        ]);

        return [
            'cart' => $this->reloadCart($cart),
            'guest_token' => $guestToken,
        ];
    }

    public function findGuestCart(string $token): ?Cart
    {
        return Cart::query()
            ->where('guest_token', $token)
            ->whereNull('user_id')
            ->first();
    }

    /**
     * @return array{cart: Cart, merged_items: int, skipped_items: int}
     */
    public function mergeGuestCartToUser(User $user, string $guestToken): array
    {
        $guestCart = $this->findGuestCart($guestToken);

        if (! $guestCart || $guestCart->items()->count() === 0) {
            return [
                'cart' => $this->resolveCart($user),
                'merged_items' => 0,
                'skipped_items' => 0,
            ];
        }

        $userCart = $this->resolveCart($user);
        $merged = 0;
        $skipped = 0;

        foreach ($guestCart->items()->with('product')->get() as $guestItem) {
            $product = $guestItem->product;

            if (! $product) {
                $skipped++;
                $guestItem->delete();
                continue;
            }

            $isDigital = $product->type === 'digital' || $product->listing_type === 'license_key';
            $existing = $userCart->items()->where('item_hash', $guestItem->item_hash)->first();

            if ($existing && ! $isDigital) {
                try {
                    $this->updateItemQuantity($existing, $existing->quantity + $guestItem->quantity);
                    $merged++;
                } catch (ValidationException) {
                    $skipped++;
                }
                $guestItem->delete();
            } elseif ($existing) {
                $skipped++;
                $guestItem->delete();
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
                $merged++;
            }
        }

        $guestCart->delete();

        return [
            'cart' => $this->reloadCart($userCart),
            'merged_items' => $merged,
            'skipped_items' => $skipped,
        ];
    }

    public function addItem(
        Cart $cart,
        Product $product,
        int $quantity,
        ?int $variantId = null,
        ?array $optionsSnapshot = null,
        ?string $optionsSummary = null,
    ): Cart {
        $variant = null;
        if ($variantId) {
            $variant = $product->variants()->with('optionValues.option')->find($variantId);
            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['Selected variant is invalid.'],
                ]);
            }
        }

        $listingType = (string) ($product->listing_type ?? 'sell_on_site');
        if (! in_array($listingType, ['sell_on_site', 'license_key'], true)) {
            throw ValidationException::withMessages([
                'product_id' => ['This listing type cannot be purchased through the cart.'],
            ]);
        }

        if (! ProductPricing::isPurchasable($product, $quantity, $variant)) {
            throw ValidationException::withMessages([
                'product_id' => ['Product is unavailable or out of stock.'],
            ]);
        }

        $translation = $product->translations()->where('locale', 'en')->first();
        $unitPrice = ProductPricing::unitPrice($product, $variant);
        $itemHash = md5($product->id.':'.($variant?->id ?? 'default'));

        $existing = $cart->items()->where('item_hash', $itemHash)->first();

        if ($existing) {
            $newQuantity = $existing->quantity + $quantity;

            if (! ProductPricing::isPurchasable($product->fresh(), $newQuantity, $variant)) {
                throw ValidationException::withMessages([
                    'quantity' => ['Not enough stock available.'],
                ]);
            }

            $existing->update([
                'quantity' => $newQuantity,
                'total_price' => Decimal::multiply($unitPrice, $newQuantity),
                'is_stock_available' => true,
            ]);
        } else {
            $cart->items()->create([
                'item_hash' => $itemHash,
                'product_id' => $product->id,
                'seller_id' => $product->vendor_id,
                'product_type' => $product->type,
                'listing_type' => $product->listing_type,
                'product_title' => $translation?->title ?? $product->slug,
                'product_sku' => $variant?->sku ?? $product->sku,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_price_base' => $unitPrice,
                'total_price' => Decimal::multiply($unitPrice, $quantity),
                'variant_hash' => $variant?->variant_hash,
                'product_options_snapshot' => $optionsSnapshot,
                'product_options_summary' => $optionsSummary,
                'is_stock_available' => true,
            ]);
        }

        $this->syncCartCurrency($cart);
        $this->clearShippingSelections($cart);

        return $this->reloadCart($cart);
    }

    public function updateItemQuantity(CartItem $item, int $quantity): Cart
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        $product = $item->product;

        if (! $product || ! ProductPricing::isPurchasable($product, $quantity)) {
            throw ValidationException::withMessages([
                'quantity' => ['Not enough stock available.'],
            ]);
        }

        $item->update([
            'quantity' => $quantity,
            'total_price' => Decimal::multiply($item->unit_price, $quantity),
        ]);

        $this->clearShippingSelections($item->cart);

        return $this->reloadCart($item->cart);
    }

    public function removeItem(CartItem $item): Cart
    {
        $cart = $item->cart;
        $item->delete();
        $this->clearShippingSelections($cart);

        return $this->reloadCart($cart);
    }

    public function applyCoupon(Cart $cart, string $couponCode): Cart
    {
        $coupon = Coupon::query()
            ->whereRaw('LOWER(coupon_code) = ?', [strtolower($couponCode)])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Invalid coupon code.'],
            ]);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has expired.'],
            ]);
        }

        $totals = $this->calculateTotals($cart, $coupon);

        if ($totals['discount_amount'] <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Coupon does not apply to items in your cart.'],
            ]);
        }

        $cart->update(['coupon_code' => $coupon->coupon_code]);

        return $this->reloadCart($cart);
    }

    public function removeCoupon(Cart $cart): Cart
    {
        $cart->update(['coupon_code' => null]);

        return $this->reloadCart($cart);
    }

    /**
     * @return array{
     *     subtotal: float,
     *     discount_amount: float,
     *     shipping_cost: float,
     *     total: float,
     *     currency_code: string,
     *     item_count: int,
     *     is_valid: bool
     * }
     */
    public function calculateTotals(Cart $cart, ?Coupon $coupon = null, ?int $affiliateLinkId = null): array
    {
        $cart->loadMissing('items.product');

        $subtotal = 0.0;
        $itemCount = 0;
        $isValid = true;

        foreach ($cart->items as $item) {
            if (! $item->product || ! ProductPricing::isPurchasable($item->product, $item->quantity)) {
                $isValid = false;
            }

            $subtotal += (float) $item->total_price;
            $itemCount += $item->quantity;
        }

        if ($coupon === null && $cart->coupon_code) {
            $coupon = Coupon::query()
                ->whereRaw('LOWER(coupon_code) = ?', [strtolower($cart->coupon_code)])
                ->first();
        }

        $discountAmount = $coupon ? $this->calculateCouponDiscount($cart, $coupon) : 0.0;

        $affiliateData = app(\App\Modules\Selloff\Affiliate\Services\AffiliateAttributionService::class)
            ->calculateForCart($cart->items, $affiliateLinkId);
        $affiliateDiscount = (float) ($affiliateData['discount'] ?? 0);

        $shippingCost = (float) ($cart->shipping_cost ?? 0);
        $total = max(0, $subtotal - $discountAmount - $affiliateDiscount + $shippingCost);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'affiliate_discount' => round($affiliateDiscount, 2),
            'affiliate_discount_rate' => (float) ($affiliateData['discount_rate'] ?? 0),
            'affiliate_data' => $affiliateData['id'] ? $affiliateData : null,
            'shipping_cost' => round($shippingCost, 2),
            'total' => round($total, 2),
            'currency_code' => $cart->currency_code ?? 'NGN',
            'item_count' => $itemCount,
            'is_valid' => $isValid && $itemCount > 0,
        ];
    }

    private function calculateCouponDiscount(Cart $cart, Coupon $coupon): float
    {
        $eligibleSubtotal = 0.0;

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            if ($coupon->seller_id && (int) $coupon->seller_id !== (int) $item->seller_id) {
                continue;
            }

            if (! empty($coupon->category_ids) && ! in_array((int) $item->product->category_id, $coupon->category_ids, true)) {
                continue;
            }

            $eligibleSubtotal += (float) $item->total_price;
        }

        if ($eligibleSubtotal <= 0 || ! $coupon->discount_rate) {
            return 0.0;
        }

        if ((float) $coupon->minimum_order_amount > $eligibleSubtotal) {
            return 0.0;
        }

        return round($eligibleSubtotal * ((float) $coupon->discount_rate / 100), 2);
    }

    private function reloadCart(Cart $cart): Cart
    {
        return $cart->fresh()->load([
            'items.product.translations',
            'items.product.images',
            'items.seller.vendorProfile',
        ]);
    }

    private function clearShippingSelections(Cart $cart): void
    {
        $cart->update([
            'shipping_cost' => 0,
            'shipping_data' => null,
            'shipping_cost_data' => null,
        ]);
    }

    private function syncCartCurrency(Cart $cart): void
    {
        $defaultCurrency = Currency::query()->where('code', 'NGN')->first();

        $cart->update([
            'currency_code' => $defaultCurrency?->code ?? 'NGN',
            'currency_code_base' => $defaultCurrency?->code ?? 'NGN',
            'exchange_rate' => $defaultCurrency?->exchange_rate ?? 1,
        ]);
    }
}
