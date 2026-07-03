<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassKBacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_list_and_update_escrow_transactions(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->assertGreaterThan(0, EscrowTransaction::query()->count());

        $tx = EscrowTransaction::query()->firstOrFail();

        $this->getJson('/api/v1/admin/escrow/transactions')
            ->assertOk()
            ->assertJsonPath('data.total', fn ($total) => $total > 0)
            ->assertJsonFragment(['ref' => $tx->ref]);

        $this->patchJson("/api/v1/admin/escrow/transactions/{$tx->id}/status", [
            'status' => 'processing',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_admin_can_manage_featured_pricing(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/featured-pricing')
            ->assertOk()
            ->assertJsonStructure(['data' => ['price_per_day', 'price_per_month', 'free_product_promotion']]);

        $this->putJson('/api/v1/admin/featured-pricing', [
            'price_per_day' => 1500,
            'price_per_month' => 30000,
            'free_product_promotion' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.price_per_day', 1500)
            ->assertJsonPath('data.price_per_month', 30000);
    }

    public function test_admin_can_manage_languages_and_translations(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $english = Language::query()->where('code', 'en')->firstOrFail();

        $this->getJson('/api/v1/admin/languages')
            ->assertOk()
            ->assertJsonFragment(['code' => 'en']);

        $this->postJson('/api/v1/admin/languages', [
            'name' => 'French',
            'code' => 'fr',
            'status' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'fr');

        $this->postJson("/api/v1/admin/languages/{$english->id}/translations", [
            'label' => 'welcome',
            'translation' => 'Welcome',
        ])
            ->assertCreated()
            ->assertJsonPath('data.translation', 'Welcome');

        $this->getJson("/api/v1/admin/languages/{$english->id}/translations")
            ->assertOk()
            ->assertJsonFragment(['label' => 'welcome']);
    }

    public function test_vendor_can_read_membership_status(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/membership/status')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['has_active_membership', 'is_expired', 'can_add_products', 'expires_at', 'plan'],
            ]);
    }

    public function test_vendor_membership_status_reflects_expired_plan(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

        UserMembershipPlan::query()->updateOrCreate(
            ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
            ['is_active' => true, 'expires_at' => now()->subDay()],
        );

        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/membership/status')
            ->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.can_add_products', false);
    }

    public function test_vendor_can_promote_own_product(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/promotion-pricing')->assertOk();

        $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
            'plan_type' => 'daily',
            'duration' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $product->refresh();
        $this->assertTrue($product->is_promoted);
        $this->assertNotNull($product->promoted_until);
    }

    public function test_buyer_can_submit_start_selling_verification(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $buyer->update(['shop_opening_status' => 0, 'vendor_documents' => []]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/shop-opening-request-status')
            ->assertOk()
            ->assertJsonPath('data.is_active_shop_request', 0);

        $this->postJson('/api/v1/start-selling-verification', [
            'first_name' => 'Demo',
            'last_name' => 'Buyer',
            'shop_name' => 'Demo Buyer Shop',
            'phone_number' => '+2348012345678',
            'country_id' => Country::query()->value('id'),
            'state_id' => State::query()->value('id'),
            'city_id' => City::query()->value('id'),
            'terms_accepted' => true,
            'documents' => [
                ['name' => 'proof_of_id', 'path' => 'uploads/demo/id.jpg'],
                ['name' => 'selfie_with_id', 'path' => 'uploads/demo/selfie.jpg'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_active_shop_request', 1);
    }
}
