<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin routes reject unauthenticated requests', function () {
    $this->getJson('/api/v1/admin/products')->assertUnauthorized();
    $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

test('admin routes reject member without admin permission', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/admin/products')->assertForbidden();
    $this->getJson('/api/v1/admin/orders')->assertForbidden();
    $this->getJson('/api/v1/users')->assertForbidden();
});

test('stripe webhook rejects payload when verification enabled without signature', function () {
    config([
        'selloff_payments.stripe.verify_webhook' => true,
        'selloff_payments.stripe.webhook_secret' => 'whsec_test_secret',
    ]);

    $this->postJson('/api/v1/webhooks/stripe', [
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_invalid', 'metadata' => ['checkout_token' => 'fake']]],
    ])->assertStatus(400);
});

test('media upload rejects php disguised as image', function () {
    Storage::fake('public');

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $file = UploadedFile::fake()->create('shell.php', 100, 'application/x-php');

    $this->postJson('/api/v1/media/upload', [
        'file' => $file,
        'context' => 'product',
    ])->assertStatus(422);
});

test('wallet checkout requires authentication', function () {
    $this->postJson('/api/v1/checkout/wallet', [
        'checkout_token' => '00000000-0000-0000-0000-000000000000',
    ])->assertUnauthorized();
});

test('admin routes require pin verification for pending tokens', function () {
    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $token = $superAdmin->createToken('audit', [\App\Modules\Selloff\Admin\Support\AdminPinContext::ABILITY_PENDING]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/products')
        ->assertForbidden()
        ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
});
