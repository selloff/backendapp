<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Services\ServicePaymentCompletionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicePaymentCompletionController extends Controller
{
    public function show(Request $request, ServicePaymentCompletionService $service): JsonResponse
    {
        $data = $request->validate([
            'service_type' => ['required', 'in:membership,promote,add_funds'],
            'transaction_id' => ['required', 'integer', 'min:1'],
        ]);

        $payload = $service->resolve(
            $request->user(),
            $data['service_type'],
            (int) $data['transaction_id'],
            $request,
        );

        return ApiResponse::success($payload);
    }
}
