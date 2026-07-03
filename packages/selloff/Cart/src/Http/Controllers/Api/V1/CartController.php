<?php

namespace App\Modules\Selloff\Cart\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\ApplyShippingRequest;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\AddCartItemRequest;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\ApplyCouponRequest;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\MergeGuestCartRequest;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\UpdateCartItemRequest;
use App\Modules\Selloff\Cart\Http\Resources\Api\V1\CartResource;
use App\Modules\Selloff\Cart\Models\CartItem;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Shipping\Services\ShippingQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ShippingQuoteService $shippingQuotes,
        private readonly CommerceGtmService $gtm,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request->user());

        return ApiResponse::success(new CartResource($cart));
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $product = Product::query()->with(['translations', 'vendor'])->findOrFail($request->integer('product_id'));
        $cart = $this->cartService->resolveCart($request->user());
        $cart = $this->cartService->addItem(
            $cart,
            $product,
            $request->integer('quantity', 1),
            $request->integer('variant_id') ?: null,
            $request->input('product_options_snapshot'),
            $request->filled('product_options_summary') ? $request->string('product_options_summary')->toString() : null,
        );

        return ApiResponse::success([
            ...(new CartResource($cart))->resolve($request),
            'gtm_events' => $this->gtm->addToCart($product, $request->integer('quantity', 1), $request->user()),
        ], 201);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        abort_unless((int) $cartItem->cart->user_id === (int) $request->user()->id, 403);

        $cart = $this->cartService->updateItemQuantity($cartItem, $request->integer('quantity'));

        return ApiResponse::success(new CartResource($cart));
    }

    public function removeItem(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless((int) $cartItem->cart->user_id === (int) $request->user()->id, 403);

        $cart = $this->cartService->removeItem($cartItem);

        return ApiResponse::success(new CartResource($cart));
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request->user());
        $cart = $this->cartService->applyCoupon($cart, $request->string('coupon_code')->toString());

        return ApiResponse::success(new CartResource($cart));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request->user());
        $cart = $this->cartService->removeCoupon($cart);

        return ApiResponse::success(new CartResource($cart));
    }

    public function applyShipping(ApplyShippingRequest $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request->user());

        if ($request->filled('seller_shipping')) {
            $cart = $this->shippingQuotes->applyPerSellerToCart(
                $cart,
                $request->input('seller_shipping'),
                $request->integer('country_id') ?: null,
                $request->integer('state_id') ?: null,
            );
        } else {
            $cart = $this->shippingQuotes->applyToCart(
                $cart,
                $request->integer('shipping_method_id'),
                $request->integer('country_id') ?: null,
                $request->integer('state_id') ?: null,
            );
        }

        return ApiResponse::success(new CartResource($cart));
    }

    public function mergeGuest(MergeGuestCartRequest $request): JsonResponse
    {
        $result = $this->cartService->mergeGuestCartToUser(
            $request->user(),
            $request->string('guest_token')->toString(),
        );

        return ApiResponse::success([
            ...(new CartResource($result['cart']))->resolve($request),
            'merged_items' => $result['merged_items'],
            'skipped_items' => $result['skipped_items'],
        ]);
    }

    public function beginCheckoutGtm(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart($request->user());

        return ApiResponse::success([
            'gtm_events' => $this->gtm->beginCheckout($cart, $request->user(), $request),
        ]);
    }
}
