<?php

use App\Models\User;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('shop opening status includes document requirements', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/shop-opening-request-status')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.request_documents_required', true)
        ->assertJsonStructure([
            'data' => [
                'is_active_shop_request',
                'rejection_reason',
                'request_documents_required',
                'documents_explanation',
            ],
        ]);
});

test('buyer can submit full start selling application', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['shop_opening_status' => 0, 'vendor_documents' => []]);
    Sanctum::actingAs($buyer);

    $country = Country::query()->firstOrFail();
    $state = State::query()->where('country_id', $country->id)->firstOrFail();
    $city = City::query()->where('state_id', $state->id)->firstOrFail();

    $payload = [
        'first_name' => 'Demo',
        'last_name' => 'Seller',
        'shop_name' => 'Demo New Shop',
        'phone_number' => '+2348099988776',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'about_me' => 'Phones and accessories in Lagos.',
        'terms_accepted' => true,
        'documents' => [
            ['name' => 'proof_of_id', 'path' => 'support/file_demo_id.jpg'],
            ['name' => 'selfie_with_id', 'path' => 'support/file_demo_selfie.jpg'],
        ],
    ];

    $this->postJson('/api/v1/start-selling-verification', $payload)
        ->assertCreated()
        ->assertJsonPath('data.is_active_shop_request', 1);

    $buyer->refresh()->load('vendorProfile');

    expect($buyer->shop_opening_status)->toBe(1);
    expect($buyer->first_name)->toBe('Demo');
    expect($buyer->slug)->toBe('demo-new-shop');
    expect($buyer->phone_number)->toBe('+2348099988776');
    expect($buyer->vendor_documents)->toHaveCount(2);
    expect($buyer->vendorProfile)->not->toBeNull();
    expect($buyer->vendorProfile->shop_name)->toBe('Demo New Shop');
});

test('pending request cannot be resubmitted', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['shop_opening_status' => 1]);
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/start-selling-verification', [
        'first_name' => 'Demo',
        'last_name' => 'Seller',
        'shop_name' => 'Another Shop',
        'phone_number' => '+2348011111111',
        'country_id' => 1,
        'state_id' => 1,
        'city_id' => 1,
        'terms_accepted' => true,
        'documents' => [
            ['name' => 'proof_of_id', 'path' => 'support/id.jpg'],
            ['name' => 'selfie_with_id', 'path' => 'support/selfie.jpg'],
        ],
    ])->assertStatus(422);
});
