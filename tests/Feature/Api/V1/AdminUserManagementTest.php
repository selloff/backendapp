<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can confirm email toggle ban and change role', function () {
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
});

test('admin can assign membership plan to vendor', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/users/{$vendor->id}/assign-membership-plan", [
        'plan_id' => $plan->id,
    ])->assertOk();
});

test('admin can impersonate user', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/users/{$buyer->id}/impersonate")
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'me' => ['user', 'roles', 'permissions']]]);
});

test('users index meta includes membership plans and affiliate flag', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/users/index-meta')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['roles', 'membership_plans', 'affiliate_program_enabled'],
        ]);
});

test('admin can update user profile fields', function () {
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
});
