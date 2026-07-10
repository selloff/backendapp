<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('all legacy mobile route shims respond without server error', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $country = Country::query()->where('code', 'NG')->firstOrFail();

    $publicRoutes = [
        ['GET', '/api/v1/generate-referral-code'],
        ['GET', '/api/v1/promoted-products'],
        ['GET', '/api/v1/products/paginated'],
        ['GET', '/api/v1/products/paginated-by-latest-listings'],
        ['GET', '/api/v1/products/paginated-by-promoted-listings'],
        ['GET', '/api/v1/products/product-images/'.$product->id],
        ['GET', '/api/v1/products/paginated-by-declutter'],
        ['GET', '/api/v1/products/paginated-by-freebies'],
        ['GET', '/api/v1/products/paginated-by-category-slug?slug=smartphones'],
        ['GET', '/api/v1/products/related/'.$product->id.'/5'],
        ['GET', '/api/v1/products/paginated-listing-search/phone'],
        ['GET', '/api/v1/products/category-slug-limited/electronics/5'],
        ['GET', '/api/v1/products/latest-listing-limited/5'],
        ['GET', '/api/v1/products/promoted-listing-limited/5'],
        ['GET', '/api/v1/products/listing-custom-fields/'.$category->id],
        ['GET', '/api/v1/parent-categories'],
        ['GET', '/api/v1/categories-json'],
        ['GET', '/api/v1/customfields/custom-fields-by-category-all-data-new/'.$category->id],
        ['GET', '/api/v1/users'],
        ['GET', '/api/v1/vendors'],
        ['GET', '/api/v1/location/countries'],
        ['GET', '/api/v1/location/states/'.$country->id],
        ['GET', '/api/v1/location/cities/'.$country->states()->first()->id],
    ];

    foreach ($publicRoutes as [$method, $uri]) {
        $response = $this->json($method, $uri);
        expect($response->getStatusCode())->toBeLessThan(500, "Server error on {$method} {$uri}");
        if ($uri === '/api/v1/users') {
            expect([401, 403])->toContain($response->getStatusCode());
            continue;
        }
        if ($uri === '/api/v1/vendors') {
            continue;
        }
        $response->assertOk()->assertJsonPath('status', '1');
    }

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->getJson('/api/v1/products/'.$product->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $product->id);

    $this->getJson('/api/v1/vendors')
        ->assertOk()
        ->assertJsonPath('success', true);

    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $imagesResponse = $this->getJson('/api/v1/products/product-images/'.$product->id)
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($imagesResponse->json('data.images'))->not->toBeEmpty();

    $relatedResponse = $this->getJson('/api/v1/products/related/'.$product->id.'/5')
        ->assertOk()
        ->assertJsonPath('status', '1');
    expect($relatedResponse->json('data'))->not->toBeEmpty();

    Sanctum::actingAs($buyer);

    $conversation = Conversation::query()->create([
        'sender_id' => $buyer->id,
        'receiver_id' => $vendor->id,
        'subject' => 'Demo chat',
        'last_message_at' => now(),
    ]);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $buyer->id,
        'receiver_id' => $vendor->id,
        'message' => 'Hello vendor',
        'is_read' => false,
    ]);

    $authRoutes = [
        ['GET', '/api/v1/users/profile'],
        ['POST', '/api/v1/users/profile'],
        ['POST', '/api/v1/users/delete-account'],
        ['POST', '/api/v1/app-user-feedback'],
        ['POST', '/api/v1/products/add-remove-wishlist', ['product_id' => $product->id]],
        ['POST', '/api/v1/products/follow-seller'],
        ['POST', '/api/v1/products/report-seller'],
        ['POST', '/api/v1/products/report-user'],
        ['POST', '/api/v1/products/report-item'],
        ['GET', '/api/v1/products/paginated-by-fovourite-listings'],
        ['GET', '/api/v1/escrow-transaction/'.(\App\Modules\Selloff\Escrow\Models\EscrowTransaction::query()->first()?->id ?? 1)],
        ['POST', '/api/v1/initiate-escrow', ['product_id' => $product->id]],
        ['GET', '/api/v1/shop-opening-request-status'],
        ['POST', '/api/v1/start-selling-verification'],
        ['POST', '/api/v1/post-listing-item'],
        ['GET', '/api/v1/messages/latest-conversations'],
        ['GET', '/api/v1/messages/'.$conversation->id],
        ['GET', '/api/v1/messages/unread-conversations/'.$buyer->id],
        ['GET', '/api/v1/messages/read-conversations/'.$buyer->id],
        ['GET', '/api/v1/messages/conversations/'.$buyer->id],
        ['GET', '/api/v1/messages/unread-conversations-count'],
        ['GET', '/api/v1/messages/set-conversation-messages-as-read/'.$conversation->id],
        ['POST', '/api/v1/messages/send-conversation-message', [
            'conversation_id' => $conversation->id,
            'message' => 'Follow-up',
        ]],
        ['POST', '/api/v1/messages/send-new-conversation-message', [
            'receiver_id' => $vendor->id,
            'message' => 'New thread',
        ]],
    ];

    $skipMobileStatusChecks = [
        '/api/v1/initiate-escrow',
        '/api/v1/start-selling-verification',
        '/api/v1/post-listing-item',
    ];

    foreach ($authRoutes as $route) {
        [$method, $uri] = $route;
        $payload = $route[2] ?? [];
        $response = $this->json($method, $uri, $payload);
        $this->assertNotSame(500, $response->getStatusCode(), "Server error on {$method} {$uri}");
        if (in_array($uri, $skipMobileStatusChecks, true)) {
            continue;
        }
        expect($response->json('status') ?? $response->json('success'))->not->toBeNull("Missing mobile status on {$method} {$uri}");
    }
});

test('canonical mobile modules return mobile envelope', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $country = Country::query()->where('code', 'NG')->firstOrFail();

    $this->getJson('/api/v1/mobile/location/countries')
        ->assertOk()
        ->assertJsonPath('status', '1');

    $this->getJson('/api/v1/mobile/custom-fields/category/'.$category->id)
        ->assertOk()
        ->assertJsonPath('status', '1');

    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/mobile/messages/conversations')
        ->assertOk()
        ->assertJsonPath('status', '1');

    $this->getJson('/api/v1/mobile/vendor/shop-opening-status')
        ->assertOk()
        ->assertJsonPath('data.is_active_shop_request', 1);

    $this->getJson('/api/v1/escrow/token/demo-buyer-escrow-token')
        ->assertOk()
        ->assertJsonPath('success', true);
});
