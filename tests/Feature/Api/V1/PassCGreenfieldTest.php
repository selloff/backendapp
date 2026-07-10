<?php

use App\Models\User;
use App\Modules\Selloff\Content\Models\Page;
use App\Modules\Selloff\Support\Models\ContactMessage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public can view cms page by slug', function () {
    $this->getJson('/api/v1/pages/about-us')
        ->assertOk()
        ->assertJsonPath('data.slug', 'about-us')
        ->assertJsonPath('data.title', 'About Selloff');
});

test('public can submit contact form', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'subject' => 'Order question',
        'message' => 'When will my order ship?',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('contact_messages', [
        'email' => 'ada@example.com',
        'subject' => 'Order question',
        'status' => 'pending',
    ]);
});

test('public can list vendor shops directory', function () {
    $this->getJson('/api/v1/vendors')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['data']]);

    $count = count($this->getJson('/api/v1/vendors')->json('data.data'));
    expect($count)->toBeGreaterThanOrEqual(2);
});

test('vendor can save wallet payout account', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->putJson('/api/v1/wallet/payout-account', [
        'bank_name' => 'Demo Bank',
        'account_name' => 'Demo Vendor Shop',
        'account_number' => '0123456789',
        'swift_code' => 'DEMOXX',
    ])
        ->assertOk()
        ->assertJsonPath('data.payout_account.account_number', '0123456789');

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('data.payout_account.bank_name', 'Demo Bank');
});

test('inactive cms page is not public', function () {
    Page::query()->where('slug', 'about-us')->update(['is_active' => false]);

    $this->getJson('/api/v1/pages/about-us')->assertNotFound();
});
