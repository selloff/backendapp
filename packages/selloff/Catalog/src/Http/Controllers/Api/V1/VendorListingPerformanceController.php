<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Services\VendorListingPerformanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorListingPerformanceController extends Controller
{
    public function show(Request $request, VendorListingPerformanceService $performance): JsonResponse
    {
        $period = $request->string('period', '7d')->toString();

        return ApiResponse::success($performance->summary($request->user(), $period));
    }
}
