<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();
});

function disableMonetizationEmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

test('wallet membership purchase queues membership subscribed email job', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
        ->where('title', 'Demo Vendor Pro')
        ->firstOrFail();
    $vendor->update(['wallet_balance' => 100000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/membership-plans/{$plan->id}/purchase", [
        'months' => 1,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'membership_subscribed')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('membership-subscribed')
        ->and($job->template_data['planName'] ?? null)->toBe('Demo Vendor Pro')
        ->and($job->template_data['termMonths'] ?? null)->toBe(1);
});

test('membership subscribed email is skipped when membership purchase toggle is disabled', function () {
    disableMonetizationEmailOption('email_option_membership_purchase');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
        ->where('title', 'Demo Vendor Pro')
        ->firstOrFail();
    $vendor->update(['wallet_balance' => 100000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/membership-plans/{$plan->id}/purchase", [
        'months' => 1,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'membership_subscribed')->count())->toBe(0);
});

test('wallet featured promotion queues promotion applied email job', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 1,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'promotion_applied')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('promotion-applied')
        ->and($job->template_data['planLabel'] ?? null)->toContain('Daily plan');
});

test('wallet top ad purchase queues vip boost applied email job', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()
        ->where('vendor_id', $vendor->id)
        ->where('is_draft', false)
        ->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
        'duration_days' => 7,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'vip_boost_applied')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('promotion-applied')
        ->and($job->template_data['planLabel'] ?? null)->toContain('TOP Ad');
});

test('promotion emails are skipped when promotion applied toggle is disabled', function () {
    disableMonetizationEmailOption('email_option_promotion_applied');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 1,
        'payment_method' => 'wallet_balance',
    ])->assertCreated();

    expect(EmailJob::query()->whereIn('email_type', ['promotion_applied', 'vip_boost_applied'])->count())->toBe(0);
});

test('pending bank transfer promotion does not queue email until applied', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $vendor->update(['wallet_balance' => 0]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 2,
        'payment_method' => 'bank_transfer',
    ])->assertCreated();

    expect(EmailJob::query()->whereIn('email_type', ['promotion_applied', 'vip_boost_applied'])->count())->toBe(0);
});
