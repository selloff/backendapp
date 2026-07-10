<?php

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('browse states returns country totals popular and state counts', function () {
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

    expect($lagosRow)->not->toBeNull();
    expect($lagosRow['ads_count'])->toBeGreaterThan(0);
    expect($lagosRow['ads_count'])->toBe(Product::query()->listed()->where('state_id', $lagos->id)->count());
});

test('browse cities returns state summary and city counts', function () {
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

    expect($ikejaRow)->not->toBeNull();
    expect($ikejaRow['subtitle'])->toBe('Lagos');
    expect($ikejaRow['ads_count'])->toBe(Product::query()->listed()->where('city_id', $ikeja->id)->count());
});
