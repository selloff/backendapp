<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountDownloadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $downloads = DigitalSale::query()
            ->with(['product.translations', 'order'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        $downloads->through(fn (DigitalSale $sale) => [
            'id' => $sale->id,
            'license_key' => $sale->license_key,
            'purchase_code' => $sale->purchase_code,
            'order_id' => $sale->order_id,
            'order_number' => $sale->order?->order_number,
            'product' => $sale->product ? new ProductResource($sale->product) : null,
            'created_at' => $sale->created_at,
        ]);

        return ApiResponse::success($downloads);
    }
}
