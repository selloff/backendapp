<?php

use App\Modules\Selloff\Notification\Services\EmailOptionGate;
use App\Models\PlatformSetting;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
});

test('email option gate enables known types from platform settings', function () {
    $gate = app(EmailOptionGate::class);

    expect($gate->isEnabled('order_confirmation'))->toBeTrue();
    expect($gate->toggleKeyFor('order_confirmation'))->toBe('email_option_new_order');
});

test('email option gate respects disabled admin toggles', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => 'email_option_new_order'],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();

    $gate = app(EmailOptionGate::class);

    expect($gate->isEnabled('order_confirmation'))->toBeFalse();
});

test('test emails bypass admin toggles', function () {
    $gate = app(EmailOptionGate::class);

    expect($gate->isEnabled('test'))->toBeTrue()
        ->and($gate->toggleKeyFor('test'))->toBeNull();
});

test('email option gate maps transactional email types introduced in passes 2 through 7', function () {
    $gate = app(EmailOptionGate::class);

    expect($gate->toggleKeyFor('welcome'))->toBe('email_option_welcome')
        ->and($gate->toggleKeyFor('reset_password'))->toBe('email_option_reset_password')
        ->and($gate->toggleKeyFor('new_message'))->toBe('email_option_new_message')
        ->and($gate->toggleKeyFor('membership_subscribed'))->toBe('email_option_membership_purchase')
        ->and($gate->toggleKeyFor('membership_expiring'))->toBe('email_option_membership_expiry')
        ->and($gate->toggleKeyFor('vip_boost_applied'))->toBe('email_option_promotion_applied')
        ->and($gate->toggleKeyFor('vendor_feedback_approved'))->toBe('email_option_vendor_feedback')
        ->and($gate->toggleKeyFor('contact_message'))->toBe('email_option_contact_messages')
        ->and($gate->toggleKeyFor('support_reply'))->toBe('email_option_support_system')
        ->and($gate->toggleKeyFor('escrow'))->toBe('email_option_escrow');
});
