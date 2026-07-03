<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Content\Models\BlogComment;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Content\Models\BlogTag;
use App\Modules\Selloff\Content\Models\Page;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassLBacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_manage_cms_pages(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/cms/pages')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'terms-of-service'])
            ->assertJsonFragment(['page_default_name' => 'contact', 'is_custom' => false]);

        $this->postJson('/api/v1/admin/cms/pages', [
            'title' => 'Pass L Privacy Policy',
            'slug' => 'pass-l-privacy-policy',
            'content' => '<p>Privacy</p>',
            'is_active' => true,
        ])->assertCreated();

        $page = Page::query()->where('slug', 'pass-l-privacy-policy')->firstOrFail();

        $this->putJson("/api/v1/admin/cms/pages/{$page->id}", [
            'title' => 'Privacy Policy Updated',
        ])->assertOk()->assertJsonPath('data.title', 'Privacy Policy Updated');
    }

    public function test_admin_can_manage_quote_requests(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->assertGreaterThan(0, QuoteRequest::query()->count());

        $quote = QuoteRequest::query()->firstOrFail();

        $this->getJson('/api/v1/admin/quote-requests?show=15')
            ->assertOk()
            ->assertJsonFragment(['id' => $quote->id])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        [
                            'id',
                            'status',
                            'quantity',
                            'product',
                            'buyer',
                            'seller',
                            'updated_at',
                        ],
                    ],
                ],
            ]);

        $this->deleteJson("/api/v1/admin/quote-requests/{$quote->id}")
            ->assertOk();

        $this->assertDatabaseMissing('quote_requests', ['id' => $quote->id]);
    }

    public function test_admin_can_search_and_paginate_tags_with_product_counts(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/tags?show=15&q=demo')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'tag', 'products_count'],
                    ],
                ],
            ]);

        $this->putJson('/api/v1/admin/tags/'.\App\Modules\Selloff\Catalog\Models\Tag::query()->firstOrFail()->id, [
            'tag' => 'demo-tag-updated',
        ])->assertOk();
    }

    public function test_account_deletion_request_flow(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/account/request-deletion', [
            'password' => 'password',
        ])->assertOk();

        $buyer->refresh();
        $this->assertNotNull($buyer->account_delete_requested_at);

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/account-deletion-requests')
            ->assertOk()
            ->assertJsonFragment(['email' => 'buyer@selloff.test']);

        $this->postJson("/api/v1/admin/account-deletion-requests/{$buyer->id}/cancel")
            ->assertOk();

        $buyer->refresh();
        $this->assertNull($buyer->account_delete_requested_at);
    }

    public function test_admin_can_moderate_blog_comments(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $comment = BlogComment::query()->firstOrFail();

        $this->getJson('/api/v1/admin/cms/blog/comments?status=pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $comment->id]);

        $this->patchJson("/api/v1/admin/cms/blog/comments/{$comment->id}", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_public_can_list_and_submit_blog_comments(): void
    {
        $post = BlogPost::query()->firstOrFail();
        $comment = BlogComment::query()->where('blog_post_id', $post->id)->firstOrFail();
        $comment->update(['status' => 'approved']);

        $this->getJson("/api/v1/blog/posts/{$post->slug}/comments")
            ->assertOk()
            ->assertJsonPath('data.comments_enabled', true)
            ->assertJsonFragment(['id' => $comment->id]);

        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/blog/posts/{$post->slug}/comments", [
            'comment' => 'Another thoughtful reply.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_public_blog_tag_listing(): void
    {
        $post = BlogPost::query()->firstOrFail();
        BlogTag::query()->firstOrCreate(
            ['blog_post_id' => $post->id, 'tag_slug' => 'demo-tag'],
            ['tag' => 'Demo Tag'],
        );

        $this->getJson('/api/v1/blog/tags/demo-tag')
            ->assertOk()
            ->assertJsonPath('data.tag.tag_slug', 'demo-tag')
            ->assertJsonStructure(['data' => ['posts' => ['data']]]);
    }

    public function test_admin_payment_reports_and_approvals(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $membershipTx = MembershipTransaction::query()->where('status', 'pending')->firstOrFail();
        $this->getJson('/api/v1/admin/membership/transactions')->assertOk();
        $this->postJson("/api/v1/admin/membership/transactions/{$membershipTx->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $walletDeposit = WalletDeposit::query()->where('status', 'pending')->firstOrFail();
        $this->getJson('/api/v1/admin/payments/wallet-deposits')->assertOk();
        $this->postJson("/api/v1/admin/payments/wallet-deposits/{$walletDeposit->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $promoTx = PromotionTransaction::query()->where('status', 'pending')->firstOrFail();
        $this->getJson('/api/v1/admin/promotion-transactions')->assertOk();
        $this->postJson("/api/v1/admin/promotion-transactions/{$promoTx->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->getJson('/api/v1/admin/payments/bank-transfers')->assertOk();
    }

    public function test_admin_can_update_product_and_visual_settings(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'product',
            'settings' => [
                'marketplace_enabled' => true,
                'blog_comments_enabled' => false,
            ],
        ])->assertOk();

        $this->putJson('/api/v1/settings', [
            'group' => 'visual',
            'settings' => [
                'primary_color' => '#ff0000',
            ],
        ])->assertOk()->assertJsonPath('data.settings.primary_color', '#ff0000');
    }

    public function test_admin_can_manage_roles(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/roles/create-meta')->assertOk();
        $this->getJson('/api/v1/roles')->assertOk();

        $this->postJson('/api/v1/roles', [
            'name' => 'content-editor',
            'permissions' => ['admin_panel'],
        ])->assertCreated();
    }
}
