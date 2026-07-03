<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Promotion\Models\Coupon;
use App\Modules\Selloff\Promotion\Models\CouponUsage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountCouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usages = CouponUsage::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        $codes = $usages->getCollection()->pluck('coupon_code')->unique()->filter()->values();
        $coupons = Coupon::query()->whereIn('coupon_code', $codes)->get()->keyBy('coupon_code');

        $usages->getCollection()->transform(function (CouponUsage $usage) use ($coupons) {
            $coupon = $coupons->get($usage->coupon_code);

            return [
                'id' => $usage->id,
                'coupon_code' => $usage->coupon_code,
                'order_id' => $usage->order_id,
                'used_at' => $usage->created_at,
                'discount_rate' => $coupon?->discount_rate,
                'minimum_order_amount' => $coupon?->minimum_order_amount,
            ];
        });

        return ApiResponse::success($usages);
    }

    public function available(Request $request): JsonResponse
    {
        $coupons = Coupon::query()
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($coupons);
    }
}
