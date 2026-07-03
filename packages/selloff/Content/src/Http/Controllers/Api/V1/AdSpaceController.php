<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Models\AdSpace;
use App\Modules\Selloff\Content\Support\AdSpacePresenter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdSpaceController extends Controller
{
    public function show(string $key): JsonResponse
    {
        $adSpace = AdSpace::query()
            ->where('ad_space_key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        return ApiResponse::success(AdSpacePresenter::forBuyer($adSpace));
    }
}
