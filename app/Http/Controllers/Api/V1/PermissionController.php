<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'permissions' => Permission::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }
}
