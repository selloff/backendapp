<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\EscrowController;
use App\Support\MobileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileEscrowController extends Controller
{
    public function show(Request $request, int $id, EscrowController $controller): JsonResponse
    {
        $response = $controller->show($request, $id, app(\App\Modules\Selloff\Escrow\Services\EscrowService::class));

        return $this->toMobile($response);
    }

    public function initiate(Request $request, EscrowController $controller): JsonResponse
    {
        return $this->toMobile($controller->initiate($request, app(\App\Modules\Selloff\Escrow\Services\EscrowService::class)));
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
