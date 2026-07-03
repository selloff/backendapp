<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductViewGtmService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductViewGtmController extends Controller
{
    public function show(Request $request, string $product, ProductViewGtmService $gtm): JsonResponse
    {
        $model = $this->resolvePublishedProduct($product);

        return ApiResponse::success([
            'gtm_events' => $gtm->trackViewItem($model, $request->user(), $request),
        ]);
    }

    private function resolvePublishedProduct(string $product): Product
    {
        $query = Product::query()
            ->with([
                'translations',
                'vendor.state',
                'category.translations',
                'category.parent.translations',
            ]);

        $model = is_numeric($product)
            ? $query->where('id', (int) $product)->firstOrFail()
            : $query->where('slug', $product)->firstOrFail();

        abort_unless(
            $model->status === 'published'
            && $model->visibility === 'visible'
            && $model->is_active,
            404,
        );

        return $model;
    }
}
