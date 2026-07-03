<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Promotion\Services\FeaturedPromotionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeaturedPricingController extends Controller
{
    public function __construct(
        private readonly FeaturedPromotionService $featuredPromotion,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->featuredPromotion->pricing());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_per_day' => ['required', 'numeric', 'min:0'],
            'price_per_month' => ['required', 'numeric', 'min:0'],
            'free_product_promotion' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->featuredPromotion->updatePricing($data));
    }
}
