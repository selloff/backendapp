<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Services\VendorListingMetricsRecorder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingImpressionController extends Controller
{
    public function store(Request $request, VendorListingMetricsRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:20'],
            'product_ids.*' => ['integer', 'min:1'],
        ]);

        $recorded = $recorder->recordImpressions($data['product_ids'], $request);

        return ApiResponse::success([
            'recorded' => $recorded,
        ]);
    }
}
