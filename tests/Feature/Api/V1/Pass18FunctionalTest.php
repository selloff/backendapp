<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass18FunctionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_public_currencies_endpoint_returns_active_currencies_without_auth(): void
    {
        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['code' => 'NGN'])
            ->assertJsonFragment(['code' => 'USD']);

        $this->assertGreaterThanOrEqual(2, Currency::query()->where('status', true)->count());
    }

    public function test_escrow_token_response_includes_viewer_role_and_allowed_actions(): void
    {
        $this->getJson('/api/v1/escrow/token/demo-buyer-escrow-token')
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'buyer')
            ->assertJsonPath('data.allowed_actions', ['confirm']);

        $this->getJson('/api/v1/escrow/token/demo-seller-escrow-token')
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'seller')
            ->assertJsonPath('data.allowed_actions', ['confirm']);
    }

    public function test_escrow_dispute_requires_both_parties_to_agree(): void
    {
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        $transaction->update([
            'buyer_agreed' => true,
            'seller_agreed' => true,
            'status' => 'seller_agreed',
        ]);

        $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/dispute', [
            'reason' => 'Item not as described',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disputed')
            ->assertJsonPath('data.dispute.raised_by', 'buyer')
            ->assertJsonPath('data.allowed_actions', []);
    }

    public function test_wallet_includes_referral_earnings_for_buyer(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->assertGreaterThan(0, AffiliateEarning::query()->where('referrer_id', $buyer->id)->count());

        $this->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.referral_earnings');
    }

    public function test_blog_post_detail_includes_tags(): void
    {
        $post = BlogPost::query()->where('is_published', true)->firstOrFail();

        $this->getJson('/api/v1/blog/posts/'.$post->slug)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tags.0.tag_slug', 'marketplace-news');
    }

    public function test_account_coupons_available_lists_public_coupons(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->assertGreaterThan(0, Coupon::query()->where('is_public', true)->count());

        $this->getJson('/api/v1/account/coupons/available')
            ->assertOk()
            ->assertJsonFragment(['coupon_code' => 'DEMO10']);
    }

    public function test_admin_can_update_custom_field_and_delete_option(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $field = CustomField::query()->firstOrFail();
        $option = CustomFieldOption::query()->where('custom_field_id', $field->id)->first();

        $this->putJson('/api/v1/admin/catalog/custom-fields/'.$field->id, [
            'label' => 'Updated demo field',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Updated demo field');

        if ($option) {
            $this->deleteJson('/api/v1/admin/catalog/custom-fields/'.$field->id.'/options/'.$option->id)
                ->assertOk()
                ->assertJsonPath('data.deleted', true);
        } else {
            $this->markTestSkipped('No custom field option seeded for delete test.');
        }
    }
}
