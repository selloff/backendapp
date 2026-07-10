<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\NewsletterSubscriber;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can manage currencies and seo settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $initialCount = Currency::query()->count();

    $this->postJson('/api/v1/admin/currencies', [
        'code' => 'EUR',
        'name' => 'Euro',
        'exchange_rate' => 0.0011,
        'status' => true,
    ], $this->superAdminPinHeaders())
        ->assertCreated()
        ->assertJsonPath('data.code', 'EUR');

    $this->putJson('/api/v1/admin/seo', [
        'homepage_title' => 'Selloff Marketplace',
        'keywords' => 'marketplace, multi-vendor',
        'site_description' => 'Buy and sell on Selloff',
    ], $this->superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.homepage_title', 'Selloff Marketplace');

    expect(Currency::query()->count())->toBe($initialCount + 1);
});

test('newsletter subscribe and admin list', function () {
    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'passh@example.com'])
        ->assertCreated()
        ->assertJsonPath('data.subscribed', true);

    $subscriber = NewsletterSubscriber::query()->where('email', 'passh@example.com')->firstOrFail();

    $this->postJson('/api/v1/newsletter/unsubscribe', [
        'email' => 'passh@example.com',
        'token' => $subscriber->token,
    ])
        ->assertOk()
        ->assertJsonPath('data.unsubscribed', true);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/newsletter/subscribers')
        ->assertOk()
        ->assertJsonFragment(['email' => 'passh@example.com']);
});

test('affiliate links and vendor program settings', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'affiliate_program' => json_encode([
            'status' => true,
            'type' => 'site_based',
            'commission_rate' => 5,
            'discount_rate' => 2,
        ]),
    ], 'general');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/affiliate/join', [
        'first_name' => $buyer->first_name,
        'last_name' => $buyer->last_name,
        'phone_number' => '08000000000',
        'country_id' => $buyer->country_id ?? 1,
        'address' => '12 Test Street',
        'zip_code' => '100001',
        'terms' => true,
    ])->assertOk();

    $this->postJson('/api/v1/affiliate/links', ['product_id' => $product->id])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['link_short']]);

    expect(AffiliateLink::query()->where('referrer_id', $buyer->id)->count())->toBe(1);

    Sanctum::actingAs($vendor);

    $this->putJson('/api/v1/vendor/affiliate/settings', [
        'affiliate_commission_rate' => 7.5,
        'affiliate_discount_rate' => 2,
    ])
        ->assertOk();

    expect((float) ReferralProfile::query()->where('user_id', $vendor->id)->value('affiliate_commission_rate'))->toEqualWithDelta(7.5, 0.01);
});

test('buyer quote request and vendor response', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/quote-requests', [
        'product_id' => $product->id,
        'quantity' => 2,
        'message' => 'Can you offer bulk pricing?',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    $quoteId = QuoteRequest::query()->where('buyer_id', $buyer->id)->value('id');

    Sanctum::actingAs($vendor);

    $this->patchJson('/api/v1/vendor/quote-requests/'.$quoteId, [
        'quoted_price' => 45000,
        'status' => 'quoted',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'quoted');

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/quote-requests/'.$quoteId, [
        'status' => 'accepted',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

test('vendor can bulk import products', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'vendor_bulk_product_upload' => true,
    ], 'preferences');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $before = Product::query()->where('vendor_id', $vendor->id)->count();

    $this->postJson('/api/v1/vendor/products/bulk', [
        'products' => [
            ['title' => 'Bulk Item One', 'price' => 1200, 'stock' => 3],
            ['title' => 'Bulk Item Two', 'price' => 2400, 'stock' => 1],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('data.created_count', 2);

    expect(Product::query()->where('vendor_id', $vendor->id)->count())->toBe($before + 2);
});

test('vendor bulk import is forbidden when disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'vendor_bulk_product_upload' => false,
    ], 'preferences');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/vendor/products/bulk', [
        'products' => [
            ['title' => 'Blocked Bulk Item', 'price' => 1200, 'stock' => 3],
        ],
    ])->assertForbidden();
});

test('admin can view affiliate overview', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/affiliate/links')->assertOk();
    $this->getJson('/api/v1/admin/affiliate/earnings')->assertOk();
});
