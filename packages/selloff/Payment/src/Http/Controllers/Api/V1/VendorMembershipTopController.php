<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Services\MembershipTopBoostService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorMembershipTopController extends Controller
{
    public function __construct(
        private readonly MembershipTopBoostService $topBoost,
    ) {}

    public function topCredits(Request $request): JsonResponse
    {
        return ApiResponse::success($this->topBoost->topCreditsPayload($request->user()));
    }

    public function apply(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $durationDays = array_key_exists('duration_days', $data)
            ? (int) $data['duration_days']
            : null;

        return ApiResponse::success(
            $this->topBoost->apply($request->user(), $product, $durationDays),
        );
    }
}
