<?php

namespace App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAffiliateController extends Controller
{
    public function links(Request $request): JsonResponse
    {
        $links = AffiliateLink::query()
            ->with(['product.translations', 'referrer', 'seller'])
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success($links);
    }

    public function earnings(Request $request): JsonResponse
    {
        $earnings = AffiliateEarning::query()
            ->with(['product.translations', 'referrer', 'seller'])
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::success($earnings);
    }
}
