<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductContactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductContactController extends Controller
{
    public function viewContact(Request $request, string $product, ProductContactService $contacts): JsonResponse
    {
        $model = $this->resolvePublishedProduct($product);

        return ApiResponse::success($contacts->revealPhone($model, $request->user(), $request));
    }

    public function clickToCall(Request $request, string $product, ProductContactService $contacts): JsonResponse
    {
        $model = $this->resolvePublishedProduct($product);

        return ApiResponse::success($contacts->trackClickToCall($model, $request->user(), $request));
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
