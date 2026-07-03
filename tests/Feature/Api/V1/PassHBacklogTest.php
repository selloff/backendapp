<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\NewsletterSubscriber;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassHBacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_manage_currencies_and_seo_settings(): void
    {
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

        $this->assertSame($initialCount + 1, Currency::query()->count());
    }

    public function test_newsletter_subscribe_and_admin_list(): void
    {
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
    }

    public function test_affiliate_links_and_vendor_program_settings(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/affiliate/links', ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['link_short']]);

        $this->assertSame(1, AffiliateLink::query()->where('referrer_id', $buyer->id)->count());

        Sanctum::actingAs($vendor);

        $this->putJson('/api/v1/vendor/affiliate/settings', [
            'affiliate_commission_rate' => 7.5,
            'affiliate_discount_rate' => 2,
        ])
            ->assertOk();

        $this->assertEqualsWithDelta(
            7.5,
            (float) ReferralProfile::query()->where('user_id', $vendor->id)->value('affiliate_commission_rate'),
            0.01,
        );
    }

    public function test_buyer_quote_request_and_vendor_response(): void
    {
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
    }

    public function test_vendor_can_bulk_import_products(): void
    {
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

        $this->assertSame($before + 2, Product::query()->where('vendor_id', $vendor->id)->count());
    }

    public function test_vendor_bulk_import_is_forbidden_when_disabled(): void
    {
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
    }

    public function test_admin_can_view_affiliate_overview(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/affiliate/links')->assertOk();
        $this->getJson('/api/v1/admin/affiliate/earnings')->assertOk();
    }
}
