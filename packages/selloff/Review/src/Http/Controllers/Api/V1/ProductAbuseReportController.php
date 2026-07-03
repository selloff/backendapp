<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Http\Requests\Api\V1\StoreProductAbuseReportRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductAbuseReportController extends Controller
{
    public function store(StoreProductAbuseReportRequest $request, Product $product): JsonResponse
    {
        abort_if((int) $product->vendor_id === (int) $request->user()->id, 422, 'You cannot report your own product.');

        DB::table('abuse_reports')->insert([
            'reporter_id' => $request->user()->id,
            'product_id' => $product->id,
            'item_id' => $product->id,
            'report_type' => $request->string('report_type', 'product')->toString(),
            'description' => $request->string('description')->toString(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(message: 'Report submitted.');
    }
}
