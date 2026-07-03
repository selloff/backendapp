<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StartSellingPhase4Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_shop_opening_status_includes_document_requirements(): void
    {
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
    }

    public function test_buyer_can_submit_full_start_selling_application(): void
    {
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

        $this->assertSame(1, $buyer->shop_opening_status);
        $this->assertSame('Demo', $buyer->first_name);
        $this->assertSame('demo-new-shop', $buyer->slug);
        $this->assertSame('+2348099988776', $buyer->phone_number);
        $this->assertCount(2, $buyer->vendor_documents);
        $this->assertNotNull($buyer->vendorProfile);
        $this->assertSame('Demo New Shop', $buyer->vendorProfile->shop_name);
    }

    public function test_pending_request_cannot_be_resubmitted(): void
    {
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
    }
}
