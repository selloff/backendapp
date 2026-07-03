<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Location\Http\Controllers\Api\V1\LocationController;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;

class MobileLocationController extends Controller
{
    public function countries(LocationController $controller): JsonResponse
    {
        return $this->toMobile($controller->countries());
    }

    public function states(int $countryId, LocationController $controller): JsonResponse
    {
        return $this->toMobile($controller->states($countryId));
    }

    public function cities(int $stateId, LocationController $controller): JsonResponse
    {
        return $this->toMobile($controller->cities($stateId));
    }

    private function toMobile(JsonResponse $response): JsonResponse
    {
        $payload = $response->getData(true);

        return MobileResponse::success($payload['data'] ?? [], 200, $payload['message'] ?? 'OK');
    }
}
