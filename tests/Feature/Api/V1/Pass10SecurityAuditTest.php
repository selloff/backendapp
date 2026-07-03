<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass10SecurityAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
        $this->getJson('/api/v1/admin/orders')->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_admin_routes_reject_member_without_admin_permission(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/admin/products')->assertForbidden();
        $this->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_stripe_webhook_rejects_payload_when_verification_enabled_without_signature(): void
    {
        config([
            'selloff_payments.stripe.verify_webhook' => true,
            'selloff_payments.stripe.webhook_secret' => 'whsec_test_secret',
        ]);

        $this->postJson('/api/v1/webhooks/stripe', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_invalid', 'metadata' => ['checkout_token' => 'fake']]],
        ])->assertStatus(400);
    }

    public function test_media_upload_rejects_php_disguised_as_image(): void
    {
        Storage::fake('public');

        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $file = UploadedFile::fake()->create('shell.php', 100, 'application/x-php');

        $this->postJson('/api/v1/media/upload', [
            'file' => $file,
            'context' => 'product',
        ])->assertStatus(422);
    }

    public function test_wallet_checkout_requires_authentication(): void
    {
        $this->postJson('/api/v1/checkout/wallet', [
            'checkout_token' => '00000000-0000-0000-0000-000000000000',
        ])->assertUnauthorized();
    }

    public function test_admin_routes_require_pin_verification_for_pending_tokens(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('audit', [\App\Modules\Selloff\Admin\Support\AdminPinContext::ABILITY_PENDING]);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/admin/products')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
    }
}
