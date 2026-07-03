<?php

namespace App\Modules\Selloff\Notification\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Notification\Actions\DeleteDeviceTokenAction;
use App\Modules\Selloff\Notification\Actions\RegisterDeviceTokenAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request, RegisterDeviceTokenAction $register): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $deviceToken = $register->execute($request->user(), $data);

        return ApiResponse::success([
            'id' => $deviceToken->id,
            'platform' => $deviceToken->platform,
        ], 201, 'Device token registered.');
    }

    public function destroy(
        Request $request,
        string $token,
        DeleteDeviceTokenAction $delete,
    ): JsonResponse {
        $deleted = $delete->execute($request->user(), urldecode($token));

        if (! $deleted) {
            return ApiResponse::error('Device token not found.', 404);
        }

        return ApiResponse::success(message: 'Device token removed.');
    }
}
