<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Catalog\Support\ProductDraftStatusSync;
use App\Modules\Selloff\Payment\Services\MembershipListingGuardService;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VendorBulkProductController extends Controller
{
    public function store(
        Request $request,
        PlatformSettingsService $settings,
        MembershipListingGuardService $membershipListingGuard,
    ): JsonResponse {
        abort_unless(
            filter_var($settings->all()['vendor_bulk_product_upload'] ?? false, FILTER_VALIDATE_BOOLEAN),
            Response::HTTP_FORBIDDEN,
            'Bulk product upload is disabled.',
        );

        $data = $request->validate([
            'products' => ['required', 'array', 'min:1', 'max:50'],
            'products.*.title' => ['required', 'string', 'max:500'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
            'products.*.stock' => ['nullable', 'integer', 'min:0'],
            'products.*.category_id' => ['nullable', 'exists:categories,id'],
            'products.*.sku' => ['nullable', 'string', 'max:100'],
            'products.*.currency_code' => ['nullable', 'string', 'max:10'],
            'products.*.description' => ['nullable', 'string'],
        ]);

        $created = [];
        $errors = [];

        foreach ($data['products'] as $index => $row) {
            try {
                $createAttributes = ProductDraftStatusSync::apply([
                    'category_id' => $row['category_id'] ?? null,
                    'status' => 'published',
                    'visibility' => 'visible',
                    'is_active' => true,
                    'is_draft' => false,
                    'is_deleted' => false,
                ]);

                $membershipListingGuard->assertCanConsumeListingSlot(
                    $request->user(),
                    isset($createAttributes['category_id']) ? (int) $createAttributes['category_id'] : null,
                    null,
                    $createAttributes,
                );

                $title = $row['title'];
                $slugBase = Str::slug($title);
                $slug = $slugBase;
                $suffix = 1;
                while (Product::query()->where('slug', $slug)->exists()) {
                    $slug = $slugBase.'-'.$suffix;
                    $suffix++;
                }

                $product = Product::query()->create([
                    'vendor_id' => $request->user()->id,
                    'category_id' => $row['category_id'] ?? null,
                    'sku' => $row['sku'] ?? null,
                    'slug' => $slug,
                    'price' => $row['price'],
                    'stock' => $row['stock'] ?? 0,
                    'currency_code' => $row['currency_code'] ?? 'NGN',
                    'status' => 'published',
                    'visibility' => 'visible',
                    'is_active' => true,
                ]);

                ProductTranslation::query()->create([
                    'product_id' => $product->id,
                    'locale' => 'en',
                    'title' => $title,
                    'description' => $row['description'] ?? null,
                ]);

                $created[] = ['index' => $index, 'id' => $product->id, 'slug' => $product->slug];
            } catch (\Throwable $exception) {
                $errors[] = [
                    'index' => $index,
                    'message' => $exception instanceof \Illuminate\Validation\ValidationException
                        ? collect($exception->errors())->flatten()->first()
                        : $exception->getMessage(),
                ];
            }
        }

        return ApiResponse::success([
            'created_count' => count($created),
            'created' => $created,
            'errors' => $errors,
        ], count($created) > 0 ? 201 : 422);
    }
}
