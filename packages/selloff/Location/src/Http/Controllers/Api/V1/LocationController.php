<?php

namespace App\Modules\Selloff\Location\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Location\Services\LocationBrowseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationBrowseService $browse,
    ) {}

    public function countries(): JsonResponse
    {
        return ApiResponse::success(
            Country::query()->where('status', true)->orderBy('name')->get(['id', 'code', 'name', 'status']),
        );
    }

    public function browseStates(Request $request): JsonResponse
    {
        $countryId = $request->integer('country_id');
        abort_unless($countryId > 0, 422, 'country_id is required.');

        return ApiResponse::success(
            $this->browse->browseStates($countryId, $request->string('q')->toString() ?: null),
        );
    }

    public function browseCities(Request $request): JsonResponse
    {
        $stateId = $request->integer('state_id');
        abort_unless($stateId > 0, 422, 'state_id is required.');

        return ApiResponse::success(
            $this->browse->browseCities($stateId, $request->string('q')->toString() ?: null),
        );
    }

    public function states(int $countryId): JsonResponse
    {
        return ApiResponse::success(
            State::query()
                ->where('country_id', $countryId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'country_id', 'code', 'name', 'status']),
        );
    }

    public function cities(int $stateId): JsonResponse
    {
        return ApiResponse::success(
            City::query()
                ->where('state_id', $stateId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'state_id', 'name', 'status']),
        );
    }
}
