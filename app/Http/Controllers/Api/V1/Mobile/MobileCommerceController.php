<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\MobileProductResource;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\CheckoutService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileCommerceController extends Controller
{
    public function addToCart(Request $request, CartService $cartService): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $cart = $cartService->resolveCart($request->user());
        $cart = $cartService->addItem($cart, $product, $data['quantity'] ?? 1);

        return MobileResponse::success([
            'item_count' => $cart->items()->count(),
            'totals' => $cartService->calculateTotals($cart),
        ], 201, 'Added to cart.');
    }

    public function walletCheckout(Request $request, CheckoutService $checkout): JsonResponse
    {
        try {
            $session = $checkout->createFromCart($request->user(), 'wallet_balance');
            $order = $checkout->completeWalletPayment($request->user(), $session);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Checkout failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success(
            new OrderResource($order),
            201,
            'Order placed successfully.',
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $paginator = Order::query()
            ->with(['items', 'buyer'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 50));

        $paginator->through(fn (Order $order) => new OrderResource($order));

        return MobileResponse::success(
            OrderResource::collection($paginator->items())->resolve(),
            200,
            'Orders fetched successfully.',
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }
}
