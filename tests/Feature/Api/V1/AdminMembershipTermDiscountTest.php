<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can list default term discounts', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/membership-term-discounts')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.months', 1)
        ->assertJsonPath('data.1.discount_percent', '15.00')
        ->assertJsonPath('data.3.months', 12)
        ->assertJsonPath('data.3.discount_percent', '25.00');
});

test('admin can update term discounts', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/admin/membership-term-discounts', [
        'discounts' => [
            ['months' => 1, 'discount_percent' => 0, 'is_active' => true],
            ['months' => 3, 'discount_percent' => 10, 'is_active' => true],
            ['months' => 6, 'discount_percent' => 18, 'is_active' => false],
            ['months' => 12, 'discount_percent' => 30, 'is_active' => true],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.1.discount_percent', '10.00')
        ->assertJsonPath('data.2.is_active', false)
        ->assertJsonPath('data.3.discount_percent', '30.00');

    $this->assertDatabaseHas('membership_term_discounts', [
        'months' => 6,
        'discount_percent' => 18,
        'is_active' => false,
    ]);
});

test('update rejects invalid term months', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/admin/membership-term-discounts', [
        'discounts' => [
            ['months' => 2, 'discount_percent' => 5],
        ],
    ])->assertUnprocessable();
});

test('non admin cannot update term discounts', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/v1/admin/membership-term-discounts', [
        'discounts' => [
            ['months' => 1, 'discount_percent' => 0],
        ],
    ])->assertForbidden();
});
