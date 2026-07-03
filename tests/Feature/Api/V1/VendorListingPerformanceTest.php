<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorListingPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_fetch_listing_performance_summary(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/listing-performance?period=7d');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', '7d')
            ->assertJsonPath('data.period_label', 'Last 7 days')
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'period_label',
                    'range_label',
                    'currency_code',
                    'series',
                    'totals' => [
                        'traffic',
                        'visitors',
                        'impressions',
                        'contact_views',
                        'chats',
                        'promotion_spend',
                    ],
                    'top_listings' => [
                        '*' => [
                            'product_id',
                            'title',
                            'slug',
                            'views',
                            'is_promoted',
                        ],
                    ],
                    'recent' => [
                        'contact_views',
                        'chats',
                    ],
                ],
            ]);
    }
}
