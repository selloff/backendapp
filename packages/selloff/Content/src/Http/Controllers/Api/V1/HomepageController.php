<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Http\Resources\Api\V1\SliderResource;
use App\Modules\Selloff\Content\Models\HomepageBanner;
use App\Modules\Selloff\Content\Models\Slider;
use App\Modules\Selloff\Content\Services\HomepageAssemblyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageController extends Controller
{
    public function index(Request $request, HomepageAssemblyService $homepage): JsonResponse
    {
        $priority = \App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery::fromRequest($request);
        $scope = $request->string('scope', 'full')->toString();
        if (! in_array($scope, ['full', 'hero', 'deferred'], true)) {
            $scope = 'full';
        }

        return ApiResponse::success($homepage->build(
            $priority['priority_state_id'],
            $priority['priority_city_id'],
            $scope,
        ));
    }

    public function sliders(): JsonResponse
    {
        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(SliderResource::collection($sliders)->resolve());
    }

    public function banners(): JsonResponse
    {
        $banners = HomepageBanner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($banners);
    }
}
