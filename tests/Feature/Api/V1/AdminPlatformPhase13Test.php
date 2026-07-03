<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Modules\Selloff\Content\Models\BlogComment;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformPhase13Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_bulk_moderate_blog_comments(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $comment = BlogComment::query()->firstOrFail();

        $this->getJson('/api/v1/admin/cms/blog/comments?status=pending&q='.urlencode(substr($comment->comment ?? 'a', 0, 3)))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/admin/cms/blog/comments/bulk', [
            'ids' => [$comment->id],
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('data.approved', 1);
    }

    public function test_admin_can_manage_platform_preferences_newsletter_and_affiliate_program(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/platform/preferences')
            ->assertOk()
            ->assertJsonPath('data.system.multi_vendor_system', true)
            ->assertJsonPath('data.wallet.wallet_status', true);

        $this->putJson('/api/v1/admin/platform/preferences', [
            'tab' => 'shop',
            'settings' => [
                'auto_approve_orders' => true,
                'auto_approve_orders_days' => 14,
                'show_customer_email_seller' => false,
            ],
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.shop.auto_approve_orders', true)
            ->assertJsonPath('data.shop.auto_approve_orders_days', 14)
            ->assertJsonPath('data.shop.show_customer_email_seller', false);

        $this->putJson('/api/v1/admin/platform/storage', [
            'storage' => 'aws_s3',
            'aws_key' => 'demo-key',
            'aws_secret' => 'demo-secret',
            'aws_bucket' => 'demo-bucket',
            'aws_region' => 'us-east-1',
            'r2_key' => '',
            'r2_secret' => '',
            'r2_bucket' => '',
            'r2_endpoint_url' => '',
            'r2_public_url' => '',
            'b2_key' => '',
            'b2_secret' => '',
            'b2_bucket' => '',
            'b2_endpoint_url' => '',
            'b2_public_url' => '',
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.storage', 'aws_s3')
            ->assertJsonPath('data.aws_bucket', 'demo-bucket');

        $this->putJson('/api/v1/admin/platform/ai-writer', [
            'status' => true,
            'api_key' => 'sk-demo-key',
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', true)
            ->assertJsonPath('data.api_key', 'sk-demo-key');

        $this->getJson('/api/v1/admin/newsletter/settings')
            ->assertOk()
            ->assertJsonPath('data.newsletter_status', true);

        $this->putJson('/api/v1/admin/newsletter/settings', [
            'newsletter_popup_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.newsletter_popup_active', true);

        $this->getJson('/api/v1/admin/newsletter/users?q=superadmin')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/affiliate/program')
            ->assertOk()
            ->assertJsonPath('data.type', 'seller_based');

        $this->putJson('/api/v1/admin/affiliate/program', [
            'section' => 'settings',
            'commission_rate' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('data.commission_rate', 8);

        $this->putJson('/api/v1/admin/affiliate/program', [
            'section' => 'description',
            'lang_id' => 1,
            'description' => [
                'title' => 'Earn with Selloff',
                'description' => 'Earn by sharing Selloff.',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.description.description', 'Earn by sharing Selloff.');

        $stored = app(PlatformSettingsService::class)->all();
        $program = json_decode((string) ($stored['affiliate_program'] ?? '{}'), true);
        $this->assertSame(8, (int) ($program['commission_rate'] ?? 0));
    }

    public function test_admin_can_delete_abuse_reports(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $product = DB::table('products')->first();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        DB::table('abuse_reports')->insert([
            'reporter_id' => $buyer->id,
            'product_id' => $product->id,
            'report_type' => 'product',
            'description' => 'Phase 13 delete test',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = (int) DB::table('abuse_reports')->orderByDesc('id')->value('id');

        $this->deleteJson("/api/v1/admin/abuse-reports/{$reportId}", [], [
            AdminPinContext::HEADER_ADMIN_PIN => '196001',
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('abuse_reports', ['id' => $reportId]);
    }
}
