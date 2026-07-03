<?php

namespace App\Modules\Selloff\Location\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Location\Support\ContinentRegistry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLocationController extends Controller
{
    public function continents(): JsonResponse
    {
        return ApiResponse::success(['continents' => ContinentRegistry::all()]);
    }

    public function indexCountries(Request $request): JsonResponse
    {
        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $paginate = $request->hasAny(['show', 'page', 'q', 'search', 'per_page']);

        $query = Country::query()
            ->when($search !== '', fn ($builder) => $builder->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name');

        if ($paginate) {
            $perPage = $this->resolvePerPage($request);

            return ApiResponse::success(
                $query->paginate($perPage)->through(fn (Country $country) => $this->formatCountry($country)),
            );
        }

        return ApiResponse::success(
            $query->with('states.cities')->get()->map(fn (Country $country) => $this->formatCountry($country, true)),
        );
    }

    public function bulkUpdateCountryStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:activate,inactivate'],
        ]);

        $status = $data['action'] === 'activate';

        Country::query()->update(['status' => $status]);

        return ApiResponse::success([
            'updated' => Country::query()->count(),
            'status' => $status,
        ]);
    }

    public function storeCountry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
            'continent_code' => ['required', 'string', 'max:10', Rule::in(ContinentRegistry::codes())],
            'status' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->formatCountry(Country::query()->create($data)), 201);
    }

    public function indexStates(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);
        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $countryId = $request->input('country');

        $states = State::query()
            ->with('country:id,name,status')
            ->when($countryId !== null && $countryId !== '', fn ($query) => $query->where('country_id', (int) $countryId))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($perPage);

        $states->getCollection()->transform(fn (State $state) => $this->formatState($state));

        return ApiResponse::success($states);
    }

    public function storeState(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success($this->formatState(State::query()->create($data)->load('country:id,name,status')), 201);
    }

    public function indexCities(Request $request): JsonResponse
    {
        $perPage = $this->resolvePerPage($request);
        $search = trim((string) ($request->input('q') ?: $request->input('search', '')));
        $countryId = $request->input('country');
        $stateId = $request->input('state');

        $cities = City::query()
            ->with(['state.country:id,name'])
            ->when($stateId !== null && $stateId !== '', fn ($query) => $query->where('state_id', (int) $stateId))
            ->when(
                $countryId !== null && $countryId !== '',
                fn ($query) => $query->whereHas('state', fn ($stateQuery) => $stateQuery->where('country_id', (int) $countryId)),
            )
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($perPage);

        $cities->getCollection()->transform(fn (City $city) => $this->formatCity($city));

        return ApiResponse::success($cities);
    }

    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state_id' => ['required', 'exists:states,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        return ApiResponse::success(City::query()->create($data), 201);
    }

    public function updateCountry(Request $request, Country $country): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'name' => ['sometimes', 'string', 'max:255'],
            'continent_code' => ['sometimes', 'string', 'max:10', Rule::in(ContinentRegistry::codes())],
            'status' => ['nullable', 'boolean'],
        ]);

        $country->update($data);

        return ApiResponse::success($this->formatCountry($country->fresh()->load('states.cities'), true));
    }

    public function destroyCountry(Country $country): JsonResponse
    {
        $country->states()->each(fn (State $state) => $state->cities()->delete());
        $country->states()->delete();
        $country->delete();

        return ApiResponse::success(message: 'Country deleted.');
    }

    public function updateState(Request $request, State $state): JsonResponse
    {
        $data = $request->validate([
            'country_id' => ['sometimes', 'exists:countries,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'boolean'],
        ]);

        $state->update($data);

        return ApiResponse::success($this->formatState($state->fresh()->load('country:id,name,status')));
    }

    public function destroyState(State $state): JsonResponse
    {
        $state->cities()->delete();
        $state->delete();

        return ApiResponse::success(message: 'State deleted.');
    }

    public function updateCity(Request $request, City $city): JsonResponse
    {
        $data = $request->validate([
            'state_id' => ['sometimes', 'exists:states,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $city->update($data);

        return ApiResponse::success($city->fresh());
    }

    public function destroyCity(City $city): JsonResponse
    {
        $city->delete();

        return ApiResponse::success(message: 'City deleted.');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', $request->input('show', 15));

        return in_array($perPage, [15, 30, 60, 100], true) ? $perPage : 15;
    }

    private function formatCountry(Country $country, bool $includeRelations = false): array
    {
        $payload = [
            'id' => $country->id,
            'code' => $country->code,
            'name' => $country->name,
            'continent_code' => $country->continent_code,
            'status' => (bool) $country->status,
        ];

        if ($includeRelations && $country->relationLoaded('states')) {
            $payload['states'] = $country->states->map(fn (State $state) => [
                'id' => $state->id,
                'country_id' => $state->country_id,
                'name' => $state->name,
                'code' => $state->code,
                'status' => (bool) $state->status,
                'cities' => $state->relationLoaded('cities')
                    ? $state->cities->map(fn (City $city) => [
                        'id' => $city->id,
                        'state_id' => $city->state_id,
                        'name' => $city->name,
                        'status' => (bool) $city->status,
                    ])->values()->all()
                    : [],
            ])->values()->all();
        }

        return $payload;
    }

    private function formatState(State $state): array
    {
        return [
            'id' => $state->id,
            'country_id' => $state->country_id,
            'name' => $state->name,
            'code' => $state->code,
            'status' => (bool) $state->status,
            'country_name' => $state->country?->name,
            'country_status' => (bool) ($state->country?->status ?? false),
        ];
    }

    private function formatCity(City $city): array
    {
        return [
            'id' => $city->id,
            'state_id' => $city->state_id,
            'name' => $city->name,
            'status' => (bool) $city->status,
            'state_name' => $city->state?->name,
            'country_name' => $city->state?->country?->name,
            'country_id' => $city->state?->country_id,
        ];
    }
}
