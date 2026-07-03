<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Support\Gtm\GtmEventFactory;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GtmRewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_gtm_event_factory_returns_nested_shape(): void
    {
        $event = app(GtmEventFactory::class)->make('purchase', ['order_id' => '1']);

        $this->assertSame('purchase', $event['event']);
        $this->assertSame('1', $event['eventData']['order_id']);
        $this->assertArrayHasKey('timestamp', $event);
    }

    public function test_view_gtm_endpoint_returns_view_item_event(): void
    {
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $response = $this->getJson('/api/v1/products/'.$product->slug.'/view-gtm')
            ->assertOk()
            ->assertJsonPath('success', true);

        $events = $response->json('data.gtm_events');
        $this->assertSame('view_item', $events[0]['event']);
        $this->assertSame((string) $product->id, $events[0]['eventData']['item_id']);

        $this->getJson('/api/v1/products/'.$product->slug.'/view-gtm')
            ->assertOk()
            ->assertJsonPath('data.gtm_events', []);
    }

    public function test_login_returns_user_login_gtm_event(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'buyer@selloff.test',
            'password' => 'password',
            'device_name' => 'test',
        ])->assertOk();

        $events = $response->json('data.gtm_events');
        $this->assertSame('user_login', $events[0]['event']);
    }
}
