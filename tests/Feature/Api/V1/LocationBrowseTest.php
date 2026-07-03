<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Tests\TestCase;

class LocationBrowseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_browse_states_returns_country_totals_popular_and_state_counts(): void
    {
        $country = Country::query()->where('name', 'Nigeria')->firstOrFail();
        $lagos = State::query()->where('country_id', $country->id)->where('name', 'Lagos')->firstOrFail();

        $this->getJson("/api/v1/location/browse/states?country_id={$country->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.country.id', $country->id)
            ->assertJsonPath('data.country.name', 'Nigeria')
            ->assertJsonStructure([
                'data' => [
                    'country' => ['id', 'name', 'total_ads'],
                    'popular' => [['id', 'name', 'ads_count', 'letter']],
                    'states' => [['id', 'name', 'ads_count', 'letter']],
                ],
            ]);

        $states = collect($this->getJson("/api/v1/location/browse/states?country_id={$country->id}")->json('data.states'));
        $lagosRow = $states->firstWhere('id', $lagos->id);

        $this->assertNotNull($lagosRow);
        $this->assertGreaterThan(0, $lagosRow['ads_count']);
        $this->assertSame(
            Product::query()->listed()->where('state_id', $lagos->id)->count(),
            $lagosRow['ads_count'],
        );
    }

    public function test_browse_cities_returns_state_summary_and_city_counts(): void
    {
        $state = State::query()->where('name', 'Lagos')->firstOrFail();
        $ikeja = City::query()->where('state_id', $state->id)->where('name', 'Ikeja')->firstOrFail();

        $this->getJson("/api/v1/location/browse/cities?state_id={$state->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state.id', $state->id)
            ->assertJsonPath('data.state.name', 'Lagos')
            ->assertJsonStructure([
                'data' => [
                    'state' => ['id', 'name', 'country_id', 'country_name', 'ads_count'],
                    'cities' => [['id', 'name', 'ads_count', 'letter', 'subtitle']],
                ],
            ]);

        $cities = collect($this->getJson("/api/v1/location/browse/cities?state_id={$state->id}")->json('data.cities'));
        $ikejaRow = $cities->firstWhere('id', $ikeja->id);

        $this->assertNotNull($ikejaRow);
        $this->assertSame('Lagos', $ikejaRow['subtitle']);
        $this->assertSame(
            Product::query()->listed()->where('city_id', $ikeja->id)->count(),
            $ikejaRow['ads_count'],
        );
    }
}
