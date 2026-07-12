<?php

use App\Models\User;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Affiliate\Models\AffiliateLink;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Services\CheckoutService;
use App\Modules\Selloff\Referral\Actions\ApplyReferralCodeOnRegisterAction;
use App\Modules\Selloff\Referral\Models\ReferralPointTransaction;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Services\Auth\EmailVerificationService;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);

    $this->disableCookieEncryption();

    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => false,
        'wallet_status' => true,
    ]);
});

test('registration with valid referral code links referrer', function () {
    $referrer = User::factory()->create();
    $profile = ReferralProfile::query()->firstOrCreate(
        ['user_id' => $referrer->id],
        ['referral_code' => 'INVITE10', 'referral_points' => 0],
    );

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Referred',
        'last_name' => 'User',
        'email' => 'referred.user@selloff.test',
        'referral_code' => $profile->referral_code,
    ]))->assertCreated();

    $referred = User::query()->where('email', 'referred.user@selloff.test')->firstOrFail();
    $referredProfile = ReferralProfile::query()->where('user_id', $referred->id)->firstOrFail();

    expect((int) $referredProfile->referral_user_id)->toBe($referrer->id);
    expect($referredProfile->referred_by_code)->toBe('INVITE10');
});

test('registration rejects invalid referral code', function () {
    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Bad',
        'last_name' => 'Code',
        'email' => 'bad.code@selloff.test',
        'referral_code' => 'NOTREAL99',
    ]))->assertStatus(422)
        ->assertJsonValidationErrors(['referral_code']);
});

test('apply referral code rejects self referral', function () {
    $user = User::factory()->create();
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'SELFREF1',
        'referral_points' => 0,
    ]);

    $this->expectException(\Illuminate\Validation\ValidationException::class);

    app(ApplyReferralCodeOnRegisterAction::class)->execute($user, 'SELFREF1');
});

test('points awarded after email verification', function () {
    $referrer = User::factory()->create();
    ReferralProfile::query()->firstOrCreate(
        ['user_id' => $referrer->id],
        ['referral_code' => 'VERIFY10', 'referral_points' => 0],
    );

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Verify',
        'last_name' => 'Me',
        'email' => 'verify.me@selloff.test',
        'referral_code' => 'VERIFY10',
    ]))->assertCreated();

    $referred = User::query()->where('email', 'verify.me@selloff.test')->firstOrFail();
    $token = app(EmailVerificationService::class)->issueToken($referred);

    $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

    $referrerProfile = ReferralProfile::query()->where('user_id', $referrer->id)->firstOrFail();
    expect((int) $referrerProfile->referral_points)->toBe(10);
    expect(ReferralPointTransaction::query()
        ->where('user_id', $referrer->id)
        ->where('type', 'earn')
        ->where('referred_user_id', $referred->id)
        ->where('points', 10)
        ->exists())->toBeTrue();
});

test('email verification award is idempotent', function () {
    $referrer = User::factory()->create();
    ReferralProfile::query()->firstOrCreate(
        ['user_id' => $referrer->id],
        ['referral_code' => 'IDEMPOT', 'referral_points' => 0],
    );

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Once',
        'last_name' => 'Only',
        'email' => 'once.only@selloff.test',
        'referral_code' => 'IDEMPOT',
    ]))->assertCreated();

    $referred = User::query()->where('email', 'once.only@selloff.test')->firstOrFail();
    $token = app(EmailVerificationService::class)->issueToken($referred);

    $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

    $secondVerify = $this->postJson('/api/v1/auth/verify-email/'.$token->token);
    expect([200, 422])->toContain($secondVerify->status());

    expect(ReferralPointTransaction::query()
        ->where('user_id', $referrer->id)
        ->where('type', 'earn')
        ->where('referred_user_id', $referred->id)
        ->count())->toBe(1);
    expect((int) ReferralProfile::query()->where('user_id', $referrer->id)->value('referral_points'))->toBe(10);
});

test('redeem points credits wallet', function () {
    $user = User::factory()->create(['wallet_balance' => 0]);
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'REDEEM01',
        'referral_points' => 200,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 200,
        'points_remaining' => 200,
        'money_per_point' => 10,
        'description' => 'Test earn lot',
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/referral/redeem', [
        'points' => 100,
    ]);

    $response->assertOk()->assertJsonPath('data.points_redeemed', 100);

    $user->refresh();
    expect((float) $user->wallet_balance)->toBe(1000.0);
    expect((int) ReferralProfile::query()->where('user_id', $user->id)->value('referral_points'))->toBe(100);
});

test('redeem below minimum is rejected', function () {
    $user = User::factory()->create(['wallet_balance' => 0]);
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'BELOWMIN',
        'referral_points' => 150,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 150,
        'points_remaining' => 150,
        'money_per_point' => 10,
        'description' => 'Test earn lot',
    ]);

    $this->actingAs($user)->postJson('/api/v1/referral/redeem', [
        'points' => 99,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['points']);
});

test('referral dashboard exposes conversion settings', function () {
    $user = User::factory()->create();
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'DASHBOARD',
        'referral_points' => 120,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 120,
        'points_remaining' => 120,
        'money_per_point' => 10,
        'description' => 'Test earn lot',
    ]);

    $this->actingAs($user)->getJson('/api/v1/referral')
        ->assertOk()
        ->assertJsonPath('data.program.points_per_signup', 10)
        ->assertJsonPath('data.program.min_points_to_redeem', 100)
        ->assertJsonPath('data.program.money_per_point', 10)
        ->assertJsonPath('data.program.min_wallet_amount', 1000)
        ->assertJsonPath('data.program.can_redeem', true)
        ->assertJsonPath('data.program.redemption_value_preview', 1200);
});

test('redeem uses locked rate from earn time not current admin rate', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'referral_program' => [
            'status' => true,
            'points_per_signup' => 1,
            'min_points_to_redeem' => 5,
            'money_per_point' => 50,
            'max_redemptions_per_day' => 3,
        ],
    ], 'general');

    $user = User::factory()->create(['wallet_balance' => 0]);
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'LOCKED',
        'referral_points' => 5,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 5,
        'points_remaining' => 5,
        'money_per_point' => 50,
        'description' => 'Legacy earn lot',
    ]);

    app(PlatformSettingsService::class)->upsertMany([
        'referral_program' => [
            'status' => true,
            'points_per_signup' => 1,
            'min_points_to_redeem' => 5,
            'money_per_point' => 100,
            'max_redemptions_per_day' => 3,
        ],
    ], 'general');

    $this->actingAs($user)->postJson('/api/v1/referral/redeem', [
        'points' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.wallet_amount', 250)
        ->assertJsonPath('data.effective_money_per_point', 50);

    expect((int) ReferralProfile::query()->where('user_id', $user->id)->value('referral_points'))->toBe(0);
    expect((int) ReferralPointTransaction::query()->where('type')->value('points_remaining'))->toBe(0);
});

test('award referral points locks current money per point on earn row', function () {
    $referrer = User::factory()->create();
    ReferralProfile::query()->firstOrCreate(
        ['user_id' => $referrer->id],
        ['referral_code' => 'LOCKRATE', 'referral_points' => 0],
    );

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Locked',
        'last_name' => 'Rate',
        'email' => 'locked.rate@selloff.test',
        'referral_code' => 'LOCKRATE',
    ]))->assertCreated();

    $referred = User::query()->where('email', 'locked.rate@selloff.test')->firstOrFail();
    $token = app(EmailVerificationService::class)->issueToken($referred);
    $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

    $earn = ReferralPointTransaction::query()
        ->where('user_id', $referrer->id)
        ->where('type', 'earn')
        ->firstOrFail();

    expect((float) $earn->money_per_point)->toBe(10.0);
    expect((int) $earn->points_remaining)->toBe(10);
});

test('affiliate join sets membership flag', function () {
    $user = User::factory()->create(['is_affiliate' => 0, 'country_id' => 1]);

    $this->actingAs($user)->postJson('/api/v1/affiliate/join', [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'phone_number' => '08000000000',
        'country_id' => 1,
        'address' => '12 Test Street',
        'zip_code' => '100001',
        'terms' => true,
    ])->assertOk();

    $user->refresh();
    expect((int) $user->is_affiliate)->toBe(1);
    expect($user->address)->toBe('12 Test Street');
});

test('affiliate link delete removes link', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'affiliate_program' => json_encode([
            'status' => true,
            'type' => 'site_based',
            'commission_rate' => 5,
            'discount_rate' => 2,
        ]),
    ], 'general');

    $affiliate = User::factory()->create(['is_affiliate' => 1, 'country_id' => 1]);
    $product = Product::query()->where('sku', 'DEMO-CASE-1')->firstOrFail();

    Sanctum::actingAs($affiliate);

    $linkId = $this->postJson('/api/v1/affiliate/links', ['product_id' => $product->id])
        ->assertCreated()
        ->json('data.id');

    $this->deleteJson('/api/v1/affiliate/links/'.$linkId)
        ->assertOk();

    expect(AffiliateLink::query()->find($linkId))->toBeNull();
});

test('affiliate link delete forbidden for non owner', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'affiliate_program' => json_encode([
            'status' => true,
            'type' => 'site_based',
            'commission_rate' => 5,
            'discount_rate' => 2,
        ]),
    ], 'general');

    $owner = User::factory()->create(['is_affiliate' => 1, 'country_id' => 1]);
    $other = User::factory()->create(['is_affiliate' => 1, 'country_id' => 1]);
    $product = Product::query()->where('sku', 'DEMO-CASE-1')->firstOrFail();

    Sanctum::actingAs($owner);
    $linkId = $this->postJson('/api/v1/affiliate/links', ['product_id' => $product->id])
        ->assertCreated()
        ->json('data.id');

    Sanctum::actingAs($other);
    $this->deleteJson('/api/v1/affiliate/links/'.$linkId)->assertForbidden();
});

test('redeem rejected when wallet disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'wallet_status' => false,
    ]);

    $user = User::factory()->create(['wallet_balance' => 0]);
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'NOWALLET',
        'referral_points' => 200,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 200,
        'points_remaining' => 200,
        'money_per_point' => 10,
        'description' => 'Test earn lot',
    ]);

    $this->actingAs($user)->postJson('/api/v1/referral/redeem', [
        'points' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['points']);
});

test('redeem rejected when daily limit reached', function () {
    $user = User::factory()->create(['wallet_balance' => 0]);
    ReferralProfile::query()->create([
        'user_id' => $user->id,
        'referral_code' => 'DAILYLIM',
        'referral_points' => 500,
    ]);
    ReferralPointTransaction::query()->create([
        'user_id' => $user->id,
        'type' => 'earn',
        'points' => 500,
        'points_remaining' => 500,
        'money_per_point' => 10,
        'description' => 'Test earn lot',
    ]);

    for ($i = 0; $i < 3; $i++) {
        ReferralPointTransaction::query()->create([
            'user_id' => $user->id,
            'type' => 'redeem',
            'points' => 100,
            'money_per_point' => 10,
            'wallet_amount' => 1000,
            'description' => 'Prior redeem',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($user)->postJson('/api/v1/referral/redeem', [
        'points' => 100,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['points']);
});

test('admin referral program settings crud', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/referral/program')
        ->assertOk()
        ->assertJsonPath('data.points_per_signup', 10)
        ->assertJsonPath('data.min_points_to_redeem', 100);

    $this->putJson('/api/v1/admin/referral/program', [
        'points_per_signup' => 15,
        'min_points_to_redeem' => 50,
        'money_per_point' => 20,
    ])
        ->assertOk()
        ->assertJsonPath('data.points_per_signup', 15)
        ->assertJsonPath('data.min_points_to_redeem', 50)
        ->assertJsonPath('data.money_per_point', 20);
});

test('admin referrals report lists signups', function () {
    $referrer = User::factory()->create();
    ReferralProfile::query()->firstOrCreate(
        ['user_id' => $referrer->id],
        ['referral_code' => 'ADMINRPT', 'referral_points' => 0],
    );

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'Admin',
        'last_name' => 'Report',
        'email' => 'admin.report@selloff.test',
        'referral_code' => 'ADMINRPT',
    ]))->assertCreated();

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/referrals')
        ->assertOk()
        ->assertJsonFragment(['email' => 'admin.report@selloff.test'])
        ->assertJsonFragment(['referred_by_code' => 'ADMINRPT']);
});

test('affiliate checkout applies discount and records earning on wallet checkout', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'affiliate_program' => json_encode([
            'status' => true,
            'type' => 'site_based',
            'commission_rate' => 5,
            'discount_rate' => 2,
        ]),
    ], 'general');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $affiliate = User::factory()->create(['is_affiliate' => 1, 'country_id' => 1]);
    $product = Product::query()->where('sku', 'DEMO-CASE-1')->firstOrFail();

    $link = AffiliateLink::query()->create([
        'referrer_id' => $affiliate->id,
        'product_id' => $product->id,
        'seller_id' => $product->vendor_id,
        'link_short' => 'TESTLINK',
    ]);

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated();

    $cart = Cart::query()->where('user_id', $buyer->id)->with('items')->firstOrFail();
    $link->update(['product_id' => (int) $cart->items->first()->product_id]);

    $this->postJson('/api/v1/wallet/deposits', [
        'amount' => 500000,
        'payment_method' => 'demo',
    ])->assertCreated();

    $checkout = app(CheckoutService::class)->createFromCart($buyer->fresh(), 'wallet_balance', $link->id);
    expect((float) data_get($checkout->cart_totals_data, 'affiliate_discount', 0))->toBeGreaterThan(0);

    $order = app(CheckoutService::class)->completeWalletPayment($buyer->fresh(), $checkout->fresh());
    $order->load('items');

    expect((float) data_get($order->affiliate_data, 'discount', 0))->toBeGreaterThan(0);

    expect(AffiliateEarning::query()
        ->where('referrer_id', $affiliate->id)
        ->where('product_id', $link->product_id)
        ->exists())->toBeTrue();
});
