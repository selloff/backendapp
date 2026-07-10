<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public currencies endpoint returns active currencies without auth', function () {
    $this->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['code' => 'NGN'])
        ->assertJsonFragment(['code' => 'USD']);

    expect(Currency::query()->where('status', true)->count())->toBeGreaterThanOrEqual(2);
});

test('escrow token response includes viewer role and allowed actions', function () {
    $this->getJson('/api/v1/escrow/token/demo-buyer-escrow-token')
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'buyer')
        ->assertJsonPath('data.allowed_actions', ['confirm']);

    $this->getJson('/api/v1/escrow/token/demo-seller-escrow-token')
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'seller')
        ->assertJsonPath('data.allowed_actions', ['confirm']);
});

test('escrow dispute requires both parties to agree', function () {
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
});

test('wallet includes referral earnings for buyer', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    expect(AffiliateEarning::query()->where('referrer_id', $buyer->id)->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.referral_earnings');
});

test('blog post detail includes tags', function () {
    $post = BlogPost::query()->where('is_published', true)->firstOrFail();

    $this->getJson('/api/v1/blog/posts/'.$post->slug)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tags.0.tag_slug', 'marketplace-news');
});

test('account coupons available lists public coupons', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    expect(Coupon::query()->where('is_public', true)->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/account/coupons/available')
        ->assertOk()
        ->assertJsonFragment(['coupon_code' => 'DEMO10']);
});

test('admin can update custom field and delete option', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $field = CustomField::query()->first();
    if (! $field) {
        $category = Category::query()->firstOrFail();
        $field = CustomField::query()->create([
            'field_type' => 'single_select',
            'label' => 'Demo field',
            'is_required' => false,
            'status' => true,
        ]);
        $field->categories()->sync([$category->id]);
    }

    $option = CustomFieldOption::query()->firstOrCreate(
        ['custom_field_id' => $field->id, 'option_key' => 'pass18_option'],
        ['label' => 'Pass 18 option'],
    );

    $this->putJson('/api/v1/admin/catalog/custom-fields/'.$field->id, [
        'label' => 'Updated demo field',
    ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Updated demo field');

    $this->deleteJson('/api/v1/admin/catalog/custom-fields/'.$field->id.'/options/'.$option->id, [], adminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.deleted', true);
});
