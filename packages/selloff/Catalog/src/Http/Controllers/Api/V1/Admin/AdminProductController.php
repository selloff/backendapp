<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductController;
use App\Modules\Selloff\Catalog\Http\Requests\Api\V1\UpdateProductRequest;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductAdminResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\AdminProductBulkService;
use App\Modules\Selloff\Catalog\Services\AdminProductExportService;
use App\Modules\Selloff\Catalog\Services\AdminProductFeaturedService;
use App\Modules\Selloff\Catalog\Services\AdminProductSpecialOfferService;
use App\Modules\Selloff\Catalog\Services\ProductModerationService;
use App\Modules\Selloff\Catalog\Support\AdminProductQueryFilter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $query = Product::query()
            ->with($this->listRelations());

        AdminProductQueryFilter::apply($query, $request);
        AdminProductQueryFilter::applySort($query, $request);

        $paginator = $query->paginate($perPage);
        $paginator->through(fn (Product $product) => new ProductAdminResource($product));

        return ApiResponse::success($paginator);
    }

    public function export(Request $request, AdminProductExportService $export): StreamedResponse
    {
        $format = (string) $request->input('format', 'csv');
        if (! in_array($format, ['csv', 'xml', 'excel', 'xlsx'], true)) {
            abort(422, 'Invalid export format.');
        }

        return $export->export($format, function ($query) use ($request): void {
            AdminProductQueryFilter::apply($query, $request);
            AdminProductQueryFilter::applySort($query, $request);
        });
    }

    public function bulk(Request $request, AdminProductBulkService $bulk): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete,delete_permanently,restore,approve,reject'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'min:1'],
            'reason' => ['required_if:action,reject', 'string', 'max:1000'],
        ]);

        $count = match ($data['action']) {
            'delete' => $bulk->softDelete($data['product_ids']),
            'delete_permanently' => $bulk->deletePermanently($data['product_ids']),
            'restore' => $bulk->restore($data['product_ids']),
            'approve' => $bulk->approve($data['product_ids']),
            'reject' => $bulk->reject($data['product_ids'], $data['reason']),
        };

        return ApiResponse::success([
            'action' => $data['action'],
            'processed' => $count,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, ProductController $catalogProducts): JsonResponse
    {
        abort_unless($request->user()?->can('products'), 403);

        $catalogProducts->update($request, $product);

        $product->refresh()->load($this->detailRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    public function approve(Product $product, ProductModerationService $moderation): JsonResponse
    {
        return ApiResponse::success(new ProductAdminResource($moderation->approve($product)));
    }

    public function reject(Request $request, Product $product, ProductModerationService $moderation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return ApiResponse::success(new ProductAdminResource($moderation->reject($product, $data['reason'])));
    }

    public function addFeatured(Request $request, Product $product, AdminProductFeaturedService $featured): JsonResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $product = $featured->add($product, $data['days']);
        $product->load($this->listRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    public function removeFeatured(Product $product, AdminProductFeaturedService $featured): JsonResponse
    {
        $product = $featured->remove($product);
        $product->load($this->listRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    public function addSpecialOffer(Product $product, AdminProductSpecialOfferService $specialOffers): JsonResponse
    {
        $product = $specialOffers->add($product);
        $product->load($this->listRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    public function removeSpecialOffer(Product $product, AdminProductSpecialOfferService $specialOffers): JsonResponse
    {
        $product = $specialOffers->remove($product);
        $product->load($this->listRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    public function show(Product $product): JsonResponse
    {
        $product->load($this->detailRelations());

        return ApiResponse::success(new ProductAdminResource($product));
    }

    /** @return list<string> */
    private function listRelations(): array
    {
        return ['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images'];
    }

    /** @return list<string> */
    private function detailRelations(): array
    {
        return [
            'translations',
            'vendor.vendorProfile',
            'vendor.state',
            'vendor.city',
            'category.translations',
            'category.parent.translations',
            'brand',
            'images',
            'options.values',
            'variants.optionValues',
            'digitalFiles',
            'licenseKeys',
            'country',
            'state',
            'city',
            'tags',
            'customFieldProducts.customField',
            'customFieldProducts.option',
        ];
    }
}
