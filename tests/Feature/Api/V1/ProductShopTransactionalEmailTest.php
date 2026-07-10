<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();
});

function disablePass3EmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

test('admin approve product queues vendor product approved email job', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/products/'.$pending->id.'/approve')->assertOk();

    $job = EmailJob::query()->where('email_type', 'product_approved')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('item-approved')
        ->and($job->status)->toBe('pending')
        ->and($job->template_data['productTitle'] ?? null)->not->toBeNull();
});

test('admin reject product queues vendor product rejected email job with reason', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/products/'.$pending->id.'/reject', [
        'reason' => 'Images do not meet guidelines.',
    ])->assertOk();

    $job = EmailJob::query()->where('email_type', 'product_rejected')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('vendor@selloff.test')
        ->and($job->template)->toBe('item-rejected')
        ->and($job->template_data['rejectReason'] ?? null)->toBe('Images do not meet guidelines.');
});

test('product moderation emails are skipped when moderation toggle is disabled', function () {
    disablePass3EmailOption('email_option_product_moderation');

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/products/'.$pending->id.'/approve')->assertOk();

    expect(EmailJob::query()->whereIn('email_type', ['product_approved', 'product_rejected'])->count())->toBe(0);
});

test('vendor publishing a listing queues admin new product email job', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Published listing alert test',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'price' => 15000,
        'status' => 'published',
    ])->assertCreated();

    $job = EmailJob::query()->where('email_type', 'new_product')->first();

    expect($job)->not->toBeNull()
        ->and($job->template)->toBe('main')
        ->and($job->to_email)->toBe('support@selloff.ng');
});

test('new product admin email is skipped when product added toggle is disabled', function () {
    disablePass3EmailOption('email_option_new_product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'No admin alert product',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'price' => 12000,
        'status' => 'published',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'new_product')->count())->toBe(0);
});

test('draft product publish does not queue duplicate new product admin email', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $response = $this->postJson('/api/v1/products', [
        'title' => 'Draft then publish',
        'type' => 'physical',
        'listing_type' => 'sell_on_site',
        'price' => 9000,
        'status' => 'draft',
    ])->assertCreated();

    expect(EmailJob::query()->where('email_type', 'new_product')->count())->toBe(0);

    $productId = (int) $response->json('data.id');

    $this->putJson("/api/v1/products/{$productId}", [
        'status' => 'published',
    ])->assertOk();

    expect(EmailJob::query()->where('email_type', 'new_product')->count())->toBe(1);
});

test('shop opening submit queues applicant and admin alert email jobs', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['shop_opening_status' => 0, 'vendor_documents' => []]);
    Sanctum::actingAs($buyer);

    $country = Country::query()->firstOrFail();
    $state = State::query()->where('country_id', $country->id)->firstOrFail();
    $city = City::query()->where('state_id', $state->id)->firstOrFail();

    $this->postJson('/api/v1/start-selling-verification', [
        'first_name' => 'Demo',
        'last_name' => 'Seller',
        'shop_name' => 'Demo New Shop',
        'phone_number' => '+2348099988776',
        'country_id' => $country->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
        'about_me' => 'Phones and accessories in Lagos.',
        'terms_accepted' => true,
        'documents' => [
            ['name' => 'proof_of_id', 'path' => 'support/file_demo_id.jpg'],
            ['name' => 'selfie_with_id', 'path' => 'support/file_demo_selfie.jpg'],
        ],
    ])->assertCreated();

    $submitted = EmailJob::query()->where('email_type', 'shop_opening_submitted')->first();
    $adminAlert = EmailJob::query()->where('email_type', 'shop_opening_admin_alert')->first();

    expect($submitted)->not->toBeNull()
        ->and($submitted->to_email)->toBe('buyer@selloff.test')
        ->and($adminAlert)->not->toBeNull()
        ->and($adminAlert->to_email)->toBe('support@selloff.ng');
});

test('admin shop opening approve queues user approval email job', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['shop_opening_status' => 1]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/shop-opening/requests/{$buyer->id}/approve")->assertOk();

    $job = EmailJob::query()->where('email_type', 'shop_opening_approved')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('buyer@selloff.test')
        ->and($job->template)->toBe('main');
});

test('shop opening emails are skipped when shop opening toggle is disabled', function () {
    disablePass3EmailOption('email_option_shop_opening_request');

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['shop_opening_status' => 1]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/shop-opening/requests/{$buyer->id}/approve")->assertOk();

    expect(EmailJob::query()->where('email_type', 'shop_opening_approved')->count())->toBe(0);
});
