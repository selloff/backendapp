<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Promotion\Models\Coupon;
use App\Modules\Selloff\Promotion\Models\CouponProduct;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $coupons = Coupon::query()
            ->with('products:id')
            ->where('seller_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 100));

        return ApiResponse::success($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $this->validated($request, required: true);
        $productIds = $data['product_ids'] ?? [];
        unset($data['product_ids']);

        $coupon = Coupon::query()->create([
            ...$data,
            'seller_id' => $request->user()->id,
        ]);

        $this->syncProducts($coupon, $productIds);

        return ApiResponse::success($coupon->fresh()->load('products:id'), 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        abort_unless((int) $coupon->seller_id === (int) $request->user()->id, 403);

        $data = $this->validated($request, required: false);
        $productIds = array_key_exists('product_ids', $data) ? $data['product_ids'] : null;
        unset($data['product_ids']);

        if ($data !== []) {
            $coupon->update($data);
        }

        if ($productIds !== null) {
            $this->syncProducts($coupon, $productIds);
        }

        return ApiResponse::success($coupon->fresh()->load('products:id'));
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        abort_unless((int) $coupon->seller_id === (int) $request->user()->id, 403);

        $coupon->delete();

        return ApiResponse::success(message: 'Coupon deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $required): array
    {
        $codeRule = $required ? ['required', 'string', 'max:100'] : ['sometimes', 'string', 'max:100'];
        $rateRule = $required ? ['required', 'integer', 'min:1', 'max:100'] : ['sometimes', 'integer', 'min:1', 'max:100'];

        return $request->validate([
            'coupon_code' => $codeRule,
            'discount_rate' => $rateRule,
            'coupon_count' => ['nullable', 'integer', 'min:1'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'usage_type' => ['nullable', 'string', 'max:20'],
            'expires_at' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);
    }

    /** @param  list<int>  $productIds */
    private function syncProducts(Coupon $coupon, array $productIds): void
    {
        CouponProduct::query()->where('coupon_id', $coupon->id)->delete();

        foreach ($productIds as $productId) {
            CouponProduct::query()->create([
                'coupon_id' => $coupon->id,
                'product_id' => $productId,
            ]);
        }
    }
}
