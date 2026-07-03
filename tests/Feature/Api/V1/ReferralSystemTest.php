<?php

namespace Tests\Feature\Api\V1;

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
use Tests\TestCase;

class ReferralSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);

        $this->disableCookieEncryption();

        app(PlatformSettingsService::class)->upsertMany([
            'turnstile_status' => false,
            'wallet_status' => true,
        ]);
    }

    public function test_registration_with_valid_referral_code_links_referrer(): void
    {
        $referrer = User::factory()->create();
        $profile = ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'INVITE10', 'referral_points' => 0],
        );

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Referred',
            'last_name' => 'User',
            'email' => 'referred.user@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => $profile->referral_code,
        ])->assertCreated();

        $referred = User::query()->where('email', 'referred.user@selloff.test')->firstOrFail();
        $referredProfile = ReferralProfile::query()->where('user_id', $referred->id)->firstOrFail();

        $this->assertSame($referrer->id, (int) $referredProfile->referral_user_id);
        $this->assertSame('INVITE10', $referredProfile->referred_by_code);
    }

    public function test_registration_rejects_invalid_referral_code(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Bad',
            'last_name' => 'Code',
            'email' => 'bad.code@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'NOTREAL99',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['referral_code']);
    }

    public function test_apply_referral_code_rejects_self_referral(): void
    {
        $user = User::factory()->create();
        ReferralProfile::query()->create([
            'user_id' => $user->id,
            'referral_code' => 'SELFREF1',
            'referral_points' => 0,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ApplyReferralCodeOnRegisterAction::class)->execute($user, 'SELFREF1');
    }

    public function test_points_awarded_after_email_verification(): void
    {
        $referrer = User::factory()->create();
        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'VERIFY10', 'referral_points' => 0],
        );

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Verify',
            'last_name' => 'Me',
            'email' => 'verify.me@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'VERIFY10',
        ])->assertCreated();

        $referred = User::query()->where('email', 'verify.me@selloff.test')->firstOrFail();
        $token = app(EmailVerificationService::class)->issueToken($referred);

        $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

        $referrerProfile = ReferralProfile::query()->where('user_id', $referrer->id)->firstOrFail();
        $this->assertSame(10, (int) $referrerProfile->referral_points);
        $this->assertTrue(
            ReferralPointTransaction::query()
                ->where('user_id', $referrer->id)
                ->where('type', 'earn')
                ->where('referred_user_id', $referred->id)
                ->where('points', 10)
                ->exists()
        );
    }

    public function test_email_verification_award_is_idempotent(): void
    {
        $referrer = User::factory()->create();
        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'IDEMPOT', 'referral_points' => 0],
        );

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Once',
            'last_name' => 'Only',
            'email' => 'once.only@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'IDEMPOT',
        ])->assertCreated();

        $referred = User::query()->where('email', 'once.only@selloff.test')->firstOrFail();
        $token = app(EmailVerificationService::class)->issueToken($referred);

        $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

        $secondVerify = $this->postJson('/api/v1/auth/verify-email/'.$token->token);
        $this->assertContains($secondVerify->status(), [200, 422]);

        $this->assertSame(1, ReferralPointTransaction::query()
            ->where('user_id', $referrer->id)
            ->where('type', 'earn')
            ->where('referred_user_id', $referred->id)
            ->count());
        $this->assertSame(10, (int) ReferralProfile::query()->where('user_id', $referrer->id)->value('referral_points'));
    }

    public function test_redeem_points_credits_wallet(): void
    {
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
        $this->assertSame(1000.0, (float) $user->wallet_balance);
        $this->assertSame(100, (int) ReferralProfile::query()->where('user_id', $user->id)->value('referral_points'));
    }

    public function test_redeem_below_minimum_is_rejected(): void
    {
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
    }

    public function test_referral_dashboard_exposes_conversion_settings(): void
    {
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
    }

    public function test_redeem_uses_locked_rate_from_earn_time_not_current_admin_rate(): void
    {
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

        $this->assertSame(0, (int) ReferralProfile::query()->where('user_id', $user->id)->value('referral_points'));
        $this->assertSame(0, (int) ReferralPointTransaction::query()->where('type', 'earn')->value('points_remaining'));
    }

    public function test_award_referral_points_locks_current_money_per_point_on_earn_row(): void
    {
        $referrer = User::factory()->create();
        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'LOCKRATE', 'referral_points' => 0],
        );

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Locked',
            'last_name' => 'Rate',
            'email' => 'locked.rate@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'LOCKRATE',
        ])->assertCreated();

        $referred = User::query()->where('email', 'locked.rate@selloff.test')->firstOrFail();
        $token = app(EmailVerificationService::class)->issueToken($referred);
        $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

        $earn = ReferralPointTransaction::query()
            ->where('user_id', $referrer->id)
            ->where('type', 'earn')
            ->firstOrFail();

        $this->assertSame(10.0, (float) $earn->money_per_point);
        $this->assertSame(10, (int) $earn->points_remaining);
    }

    public function test_affiliate_join_sets_membership_flag(): void
    {
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
        $this->assertSame(1, (int) $user->is_affiliate);
        $this->assertSame('12 Test Street', $user->address);
    }

    public function test_affiliate_link_delete_removes_link(): void
    {
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

        $this->assertNull(AffiliateLink::query()->find($linkId));
    }

    public function test_affiliate_link_delete_forbidden_for_non_owner(): void
    {
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
    }

    public function test_redeem_rejected_when_wallet_disabled(): void
    {
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
    }

    public function test_redeem_rejected_when_daily_limit_reached(): void
    {
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
    }

    public function test_admin_referral_program_settings_crud(): void
    {
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
    }

    public function test_admin_referrals_report_lists_signups(): void
    {
        $referrer = User::factory()->create();
        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['referral_code' => 'ADMINRPT', 'referral_points' => 0],
        );

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Admin',
            'last_name' => 'Report',
            'email' => 'admin.report@selloff.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'referral_code' => 'ADMINRPT',
        ])->assertCreated();

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/referrals')
            ->assertOk()
            ->assertJsonFragment(['email' => 'admin.report@selloff.test'])
            ->assertJsonFragment(['referred_by_code' => 'ADMINRPT']);
    }

    public function test_affiliate_checkout_applies_discount_and_records_earning_on_wallet_checkout(): void
    {
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
        $this->assertGreaterThan(0, (float) data_get($checkout->cart_totals_data, 'affiliate_discount', 0));

        $order = app(CheckoutService::class)->completeWalletPayment($buyer->fresh(), $checkout->fresh());
        $order->load('items');

        $this->assertGreaterThan(0, (float) data_get($order->affiliate_data, 'discount', 0));

        $this->assertTrue(
            AffiliateEarning::query()
                ->where('referrer_id', $affiliate->id)
                ->where('product_id', $link->product_id)
                ->exists()
        );
    }
}
