<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Models\RouteSlug;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRouteController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            RouteSlug::query()->orderBy('legacy_id')->orderBy('id')->get(['id', 'route_key', 'slug']),
        );
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'routes' => ['required', 'array', 'min:1'],
            'routes.*.id' => ['required', 'integer', 'exists:route_slugs,id'],
            'routes.*.slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        foreach ($data['routes'] as $row) {
            RouteSlug::query()->whereKey($row['id'])->update(['slug' => $row['slug']]);
        }

        return ApiResponse::success(
            RouteSlug::query()->orderBy('legacy_id')->orderBy('id')->get(['id', 'route_key', 'slug']),
        );
    }
}
