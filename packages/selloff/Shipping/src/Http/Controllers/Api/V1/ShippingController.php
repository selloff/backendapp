<?php

namespace App\Modules\Selloff\Shipping\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Services\ShippingQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function quote(Request $request, ShippingQuoteService $quotes, CartService $carts): JsonResponse
    {
        if ($request->boolean('for_cart')) {
            $cart = $this->resolveCartForQuote($request, $carts);

            if (! $cart) {
                return ApiResponse::success(['sellers' => [], 'methods' => []]);
            }

            $sellers = $quotes->quoteForCart(
                $cart,
                $request->integer('country_id') ?: null,
                $request->integer('state_id') ?: null,
            );

            return ApiResponse::success([
                'sellers' => $sellers,
                'has_multiple_sellers' => count($sellers) > 1,
            ]);
        }

        $methods = $quotes->quote(
            $request->integer('country_id') ?: null,
            $request->integer('state_id') ?: null,
            $request->integer('seller_id') ?: null,
        );

        return ApiResponse::success([
            'methods' => $methods->map(fn (ShippingMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'method_type' => $method->method_type ?: 'flat_rate',
                'flat_rate' => $method->flat_rate,
                'shipping_zone_id' => $method->shipping_zone_id,
            ]),
        ]);
    }

    private function resolveCartForQuote(Request $request, CartService $carts): ?\App\Modules\Selloff\Cart\Models\Cart
    {
        if ($request->user()) {
            return $carts->resolveCart($request->user());
        }

        $token = (string) $request->header('X-Guest-Cart-Token');

        return $token !== '' ? $carts->findGuestCart($token) : null;
    }
}
