<?php

namespace App\Modules\Selloff\Location\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LocationBrowseService
{
    /**
     * @return array<string, mixed>
     */
    public function browseStates(int $countryId, ?string $query = null): array
    {
        $country = Country::query()
            ->where('status', true)
            ->findOrFail($countryId);

        $stateCounts = $this->listedProductCountsByColumn('state_id', fn (Builder $q) => $q->where('country_id', $countryId));

        $states = State::query()
            ->where('country_id', $countryId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'country_id', 'name'])
            ->map(fn (State $state) => $this->formatLocationRow(
                id: $state->id,
                name: $state->name,
                adsCount: (int) ($stateCounts[$state->id] ?? 0),
            ))
            ->values();

        if ($query !== null && trim($query) !== '') {
            $states = $this->filterRows($states, $query);
        }

        $popular = $this->resolvePopularStates($countryId, $states, $stateCounts);

        return [
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'total_ads' => (int) Product::query()->listed()->where('country_id', $countryId)->count(),
            ],
            'popular' => $popular,
            'states' => $states->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function browseCities(int $stateId, ?string $query = null): array
    {
        $state = State::query()
            ->with('country:id,name')
            ->where('status', true)
            ->findOrFail($stateId);

        $cityCounts = $this->listedProductCountsByColumn('city_id', fn (Builder $q) => $q->where('state_id', $stateId));

        $cities = City::query()
            ->where('state_id', $stateId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'state_id', 'name'])
            ->map(fn (City $city) => $this->formatLocationRow(
                id: $city->id,
                name: $city->name,
                adsCount: (int) ($cityCounts[$city->id] ?? 0),
                subtitle: $state->name,
            ))
            ->values();

        if ($query !== null && trim($query) !== '') {
            $cities = $this->filterRows($cities, $query);
        }

        $stateAdsCount = (int) Product::query()->listed()->where('state_id', $stateId)->count();
        $popular = $this->resolvePopularCities($state->name, $cities, $cityCounts);

        return [
            'state' => [
                'id' => $state->id,
                'name' => $state->name,
                'country_id' => $state->country_id,
                'country_name' => $state->country?->name,
                'ads_count' => $stateAdsCount,
            ],
            'popular' => $popular,
            'cities' => $cities->values()->all(),
        ];
    }

    /**
     * @param  callable(Builder): void  $scope
     * @return array<int, int>
     */
    private function listedProductCountsByColumn(string $column, callable $scope): array
    {
        $query = Product::query()->listed()->whereNotNull($column);
        $scope($query);

        return $query
            ->selectRaw("{$column} as location_id, COUNT(*) as ads_count")
            ->groupBy($column)
            ->pluck('ads_count', 'location_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $states
     * @param  array<int, int>  $stateCounts
     * @return list<array<string, mixed>>
     */
    private function resolvePopularStates(int $countryId, Collection $states, array $stateCounts): array
    {
        $configuredNames = config('selloff.location_browse.popular_state_names', []);
        $popular = collect();

        foreach ($configuredNames as $name) {
            $match = $states->first(fn (array $row) => strcasecmp((string) $row['name'], (string) $name) === 0);
            if ($match !== null) {
                $popular->push($match);
            }
        }

        if ($popular->count() < 5) {
            $fallback = $states
                ->sortByDesc('ads_count')
                ->reject(fn (array $row) => $popular->contains(fn (array $item) => $item['id'] === $row['id']))
                ->take(5 - $popular->count());

            $popular = $popular->merge($fallback);
        }

        return $popular
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cities
     * @param  array<int, int>  $cityCounts
     * @return list<array<string, mixed>>
     */
    private function resolvePopularCities(string $stateName, Collection $cities, array $cityCounts): array
    {
        $configuredByState = config('selloff.location_browse.popular_city_names', []);
        $configuredNames = is_array($configuredByState[$stateName] ?? null)
            ? $configuredByState[$stateName]
            : [];
        $popular = collect();

        foreach ($configuredNames as $name) {
            $match = $cities->first(fn (array $row) => strcasecmp((string) $row['name'], (string) $name) === 0);
            if ($match !== null) {
                $popular->push($match);
            }
        }

        if ($popular->count() < 5) {
            $fallback = $cities
                ->sortByDesc('ads_count')
                ->reject(fn (array $row) => $popular->contains(fn (array $item) => $item['id'] === $row['id']))
                ->take(5 - $popular->count());

            $popular = $popular->merge($fallback);
        }

        return $popular
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, ads_count: int, letter: string, subtitle?: string}
     */
    private function formatLocationRow(int $id, string $name, int $adsCount, ?string $subtitle = null): array
    {
        $row = [
            'id' => $id,
            'name' => $name,
            'ads_count' => $adsCount,
            'letter' => $this->letterForName($name),
        ];

        if ($subtitle !== null) {
            $row['subtitle'] = $subtitle;
        }

        return $row;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $rows, string $query): Collection
    {
        $needle = Str::lower(trim($query));

        return $rows->filter(function (array $row) use ($needle) {
            $haystack = Str::lower($row['name'].($row['subtitle'] ?? ''));

            return str_contains($haystack, $needle);
        })->values();
    }

    private function letterForName(string $name): string
    {
        $trimmed = trim($name);

        return $trimmed === '' ? '#' : Str::upper(Str::substr($trimmed, 0, 1));
    }
}
