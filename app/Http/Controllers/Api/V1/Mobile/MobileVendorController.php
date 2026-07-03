<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\MobileProductResource;
use App\Modules\Selloff\Vendor\Http\Controllers\Api\V1\VendorShopOpeningController;
use App\Modules\Selloff\Vendor\Services\VendorShopOpeningService;
use App\Services\Mobile\MobileListingService;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileVendorController extends Controller
{
    public function shopOpeningStatus(
        Request $request,
        VendorShopOpeningController $controller,
        VendorShopOpeningService $shopOpening,
    ): JsonResponse {
        return $this->toMobile($controller->status($request, $shopOpening));
    }

    public function startSelling(
        Request $request,
        VendorShopOpeningController $controller,
        VendorShopOpeningService $shopOpening,
    ): JsonResponse {
        return $this->toMobile($controller->submit($request, $shopOpening));
    }

    public function postListingItem(Request $request, MobileListingService $listings): JsonResponse
    {
        try {
            $product = $listings->createListing($request->user(), $request->all());
        } catch (ValidationException $exception) {
            return MobileResponse::error(
                collect($exception->errors())->flatten()->first() ?? 'Unable to create listing.',
                422,
                $exception->errors(),
            );
        }

        return MobileResponse::success(
            new MobileProductResource($product),
            201,
            'Item created successfully.',
        );
    }

    private function toMobile(JsonResponse $response): JsonResponse
    {
        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if (($payload['success'] ?? false) === false) {
            return MobileResponse::error($payload['message'] ?? 'Request failed.', $status, $payload['errors'] ?? null);
        }

        return MobileResponse::success($payload['data'] ?? [], $status, $payload['message'] ?? 'OK');
    }
}
