<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\CustomFieldController;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;

class MobileCustomFieldController extends Controller
{
    public function byCategory(int $categoryId, CustomFieldController $controller): JsonResponse
    {
        return $this->toMobile($controller->byCategory($categoryId));
    }

    public function listingFields(int $categoryId, CustomFieldController $controller): JsonResponse
    {
        return $this->toMobile($controller->listingFields($categoryId));
    }

    private function toMobile(JsonResponse $response): JsonResponse
    {
        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        return MobileResponse::success($payload['data'] ?? [], $status, $payload['message'] ?? 'OK');
    }
}
