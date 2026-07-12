<?php

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

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can list and update escrow transactions', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    expect(EscrowTransaction::query()->count())->toBeGreaterThan(0);

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
});

test('admin can manage featured pricing', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/featured-pricing')
        ->assertOk()
        ->assertJsonStructure(['data' => ['price_per_day', 'price_per_month', 'free_product_promotion']]);

    $this->putJson('/api/v1/admin/featured-pricing', [
        'price_per_day' => 1500,
        'price_per_month' => 30000,
        'free_product_promotion' => false,
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.price_per_day', 1500)
        ->assertJsonPath('data.price_per_month', 30000);
});

test('admin can manage languages and translations', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $english = Language::query()->where('code', 'en')->firstOrFail();

    $this->getJson('/api/v1/admin/languages')
        ->assertOk()
        ->assertJsonFragment(['code' => 'en']);

    $this->postJson('/api/v1/admin/languages', [
        'name' => 'French',
        'code' => 'fr',
        'language_code' => 'fr-FR',
        'text_direction' => 'ltr',
        'language_order' => 2,
        'text_editor_lang' => 'fr_FR',
        'status' => true,
    ], superAdminPinHeaders())
        ->assertCreated()
        ->assertJsonPath('data.code', 'fr');

    $this->postJson("/api/v1/admin/languages/{$english->id}/translations", [
        'label' => 'welcome',
        'translation' => 'Welcome',
    ], superAdminPinHeaders())
        ->assertCreated()
        ->assertJsonPath('data.translation', 'Welcome');

    $this->getJson("/api/v1/admin/languages/{$english->id}/translations")
        ->assertOk()
        ->assertJsonFragment(['label' => 'welcome']);
});

test('vendor can read membership status', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/status')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['has_active_membership', 'is_expired', 'can_add_products', 'expires_at', 'plan'],
        ]);
});

test('vendor membership status reflects expired plan', function () {
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
});

test('vendor can promote own product with default duration', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed');

    $product->refresh();
    expect($product->is_promoted)->toBeTrue();
    expect($product->promoted_until)->not->toBeNull();
    expect(now()->diffInDays($product->promoted_until))->toBeGreaterThanOrEqual(6);
});

test('vendor can promote own product', function () {
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
    expect($product->is_promoted)->toBeTrue();
    expect($product->promoted_until)->not->toBeNull();
});

test('buyer can submit start selling verification', function () {
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
});
