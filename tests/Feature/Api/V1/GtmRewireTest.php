<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Support\Gtm\GtmEventFactory;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('gtm event factory returns nested shape', function () {
    $event = app(GtmEventFactory::class)->make('purchase', ['order_id' => '1']);

    expect($event['event'])->toBe('purchase');
    expect($event['eventData']['order_id'])->toBe('1');
    expect($event)->toHaveKey('timestamp');
});

test('view gtm endpoint returns view item event', function () {
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $response = $this->getJson('/api/v1/products/'.$product->slug.'/view-gtm')
        ->assertOk()
        ->assertJsonPath('success', true);

    $events = $response->json('data.gtm_events');
    expect($events[0]['event'])->toBe('view_item');
    expect($events[0]['eventData']['item_id'])->toBe((string) $product->id);

    $this->getJson('/api/v1/products/'.$product->slug.'/view-gtm')
        ->assertOk()
        ->assertJsonPath('data.gtm_events', []);
});

test('login returns user login gtm event', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'buyer@selloff.test',
        'password' => 'password',
        'device_name' => 'test',
    ])->assertOk();

    $events = $response->json('data.gtm_events');
    expect($events[0]['event'])->toBe('user_login');
});
