<?php

namespace App\Modules\Selloff\Cart\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\AddCartItemRequest;
use App\Modules\Selloff\Cart\Http\Requests\Api\V1\ApplyShippingRequest;
use App\Modules\Selloff\Cart\Http\Resources\Api\V1\CartResource;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Shipping\Services\ShippingQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestCartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ShippingQuoteService $shippingQuotes,
        private readonly CommerceGtmService $gtm,
    ) {}

    public function show(Request $request): JsonResponse
    {
        ['cart' => $cart, 'guest_token' => $guestToken] = $this->cartService->resolveGuestCart(
            $request->header('X-Guest-Cart-Token'),
        );

        return ApiResponse::success([
            'guest_token' => $guestToken,
            'cart' => new CartResource($cart),
        ]);
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $product = Product::query()->with(['translations', 'vendor'])->findOrFail($request->integer('product_id'));
        ['cart' => $cart, 'guest_token' => $guestToken] = $this->cartService->resolveGuestCart(
            $request->header('X-Guest-Cart-Token'),
        );

        $cart = $this->cartService->addItem(
            $cart,
            $product,
            $request->integer('quantity', 1),
            $request->integer('variant_id') ?: null,
            $request->input('product_options_snapshot'),
            $request->filled('product_options_summary') ? $request->string('product_options_summary')->toString() : null,
        );

        return ApiResponse::success([
            'guest_token' => $guestToken,
            'cart' => [
                ...(new CartResource($cart))->resolve($request),
                'gtm_events' => $this->gtm->addToCart($product, $request->integer('quantity', 1)),
            ],
        ], 201);
    }

    public function applyShipping(ApplyShippingRequest $request): JsonResponse
    {
        $guestToken = $request->header('X-Guest-Cart-Token');
        $cart = $this->cartService->findGuestCart((string) $guestToken);

        abort_unless($cart, 404, 'Guest cart not found.');

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

        return ApiResponse::success([
            'guest_token' => $guestToken,
            'cart' => new CartResource($cart),
        ]);
    }
}
