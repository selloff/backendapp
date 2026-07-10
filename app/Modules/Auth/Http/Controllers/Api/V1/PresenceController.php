<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\User\Services\UserPresenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function store(Request $request, UserPresenceService $presence): JsonResponse
    {
        $presence->recordActivity($request->user());

        return ApiResponse::success(message: 'Presence recorded.');
    }
}
