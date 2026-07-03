<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_confirm_email_toggle_ban_and_change_role(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $buyer->forceFill(['email_verified_at' => null, 'is_banned' => false])->save();

        $this->postJson("/api/v1/users/{$buyer->id}/confirm-email")
            ->assertOk()
            ->assertJsonPath('data.email_confirmed', true);

        $this->postJson("/api/v1/users/{$buyer->id}/toggle-ban")
            ->assertOk()
            ->assertJsonPath('data.is_banned', true);

        $this->postJson("/api/v1/users/{$buyer->id}/change-role", ['role' => 'member'])
            ->assertOk()
            ->assertJsonPath('data.primary_role.name', 'member');
    }

    public function test_admin_can_assign_membership_plan_to_vendor(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = MembershipPlan::query()->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$vendor->id}/assign-membership-plan", [
            'plan_id' => $plan->id,
        ])->assertOk();
    }

    public function test_admin_can_impersonate_user(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/users/{$buyer->id}/impersonate")
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'me' => ['user', 'roles', 'permissions']]]);
    }

    public function test_users_index_meta_includes_membership_plans_and_affiliate_flag(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/users/index-meta')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['roles', 'membership_plans', 'affiliate_program_enabled'],
            ]);
    }

    public function test_admin_can_update_user_profile_fields(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/users/{$vendor->id}", [
            'first_name' => 'Vendor',
            'last_name' => 'Edited',
            'username' => 'demo-vendor-shop',
            'phone_number' => '08012345678',
            'about_me' => 'Updated shop description',
            'address' => '12 Market Road',
            'zip_code' => '100001',
            'social_media_data' => [
                'facebook_url' => 'https://facebook.com/demo-vendor',
            ],
            'commission_mode' => 'custom',
            'commission_rate' => 7.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Vendor')
            ->assertJsonPath('data.phone_number', '08012345678')
            ->assertJsonPath('data.address', '12 Market Road')
            ->assertJsonPath('data.is_commission_set', true)
            ->assertJsonPath('data.commission_rate', 7.5)
            ->assertJsonPath('data.social_media_data.facebook_url', 'https://facebook.com/demo-vendor');
    }
}
