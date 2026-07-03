<?php

namespace App\Modules\Selloff\Vendor\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Vendor\Services\VendorShopOpeningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorShopOpeningController extends Controller
{
    public function status(Request $request, VendorShopOpeningService $shopOpening): JsonResponse
    {
        return ApiResponse::success($shopOpening->status($request->user()));
    }

    public function submit(Request $request, VendorShopOpeningService $shopOpening): JsonResponse
    {
        $result = $shopOpening->submit($request->user(), $request->all());

        return ApiResponse::success($result, 201);
    }
}
