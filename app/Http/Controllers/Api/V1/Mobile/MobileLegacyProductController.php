<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileCatalogCompatService;
use App\Services\Mobile\MobileUserCompatService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileLegacyProductController extends Controller
{
    public function index(MobileCatalogController $catalog): JsonResponse
    {
        return $catalog->paginated(request());
    }

    public function paginated(Request $request, MobileCatalogController $catalog): JsonResponse
    {
        return $catalog->paginated($request);
    }

    public function promoted(Request $request, MobileCatalogController $catalog): JsonResponse
    {
        $request->merge(['featured' => 1]);

        return $catalog->paginated($request);
    }

    public function emptyList(string $message = 'No records found.'): JsonResponse
    {
        return MobileResponse::success([], 200, $message);
    }

    public function productImages(int $productId, MobileCatalogCompatService $catalog): JsonResponse
    {
        return MobileResponse::success(
            $catalog->productImages($productId),
            200,
            'Product images fetched successfully.',
        );
    }

    public function related(int $productId, int $limit, MobileCatalogCompatService $catalog): JsonResponse
    {
        return MobileResponse::success(
            $catalog->relatedProductPayload($productId, $limit),
            200,
            'Related products fetched successfully.',
        );
    }

    public function listingSearch(string $query, MobileCatalogController $catalog): JsonResponse
    {
        request()->merge(['search' => $query]);

        return $catalog->paginated(request());
    }

    public function categorySlugLimited(string $slug, int $limit, MobileCatalogController $catalog): JsonResponse
    {
        request()->merge(['category_slug' => $slug, 'limit' => $limit]);

        return $catalog->paginated(request());
    }

    public function latestLimited(int $limit, MobileCatalogController $catalog): JsonResponse
    {
        request()->merge(['limit' => $limit]);

        return $catalog->paginated(request());
    }

    public function promotedLimited(int $limit, MobileCatalogController $catalog): JsonResponse
    {
        request()->merge(['limit' => $limit]);

        return $this->promoted(request(), $catalog);
    }

    public function stubAction(Request $request): JsonResponse
    {
        return MobileResponse::success([], 200, 'OK');
    }

    public function followSeller(Request $request, MobileUserCompatService $users): JsonResponse
    {
        try {
            $data = $request->validate([
                'seller_id' => ['required', 'integer', 'exists:users,id'],
            ]);
            $result = $users->toggleFollowSeller($request->user(), $data['seller_id']);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Request failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([], 200, $result['message']);
    }

    public function reportSeller(Request $request, MobileUserCompatService $users): JsonResponse
    {
        try {
            $data = $request->validate([
                'seller_id' => ['required', 'integer', 'exists:users,id'],
                'message' => ['nullable', 'string', 'max:5000'],
            ]);

            $users->reportSeller($request->user(), $data['seller_id'], $data['message'] ?? null);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Validation failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([], 200, 'Seller has been reported.');
    }

    public function reportUser(Request $request, MobileUserCompatService $users): JsonResponse
    {
        try {
            $data = $request->validate([
                'sender_id' => ['required', 'integer', 'exists:users,id'],
                'message' => ['nullable', 'string', 'max:5000'],
            ]);

            $users->reportUser($request->user(), $data['sender_id'], $data['message'] ?? null);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Validation failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([], 200, 'User has been reported.');
    }

    public function reportItem(Request $request, MobileUserCompatService $users): JsonResponse
    {
        try {
            $data = $request->validate([
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'message' => ['nullable', 'string', 'max:5000'],
            ]);

            $users->reportProduct($request->user(), $data['product_id'], $data['message'] ?? null);
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Validation failed.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success([], 200, 'Item has been reported.');
    }
}
