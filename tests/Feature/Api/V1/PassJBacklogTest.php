<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Tag;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Models\EmailVerificationToken;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Support\Models\KnowledgeBaseArticle;
use App\Modules\Selloff\Support\Models\KnowledgeBaseCategory;
use App\Modules\Selloff\User\Models\VendorProfile;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('user can delete own account', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/account/delete', [
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Account deleted.');

    $buyer->refresh();
    expect($buyer->is_disable)->toBeTrue();
    expect($buyer->is_enable_login)->toBeFalse();
    $this->assertStringContainsString('@deleted.selloff.local', $buyer->email);
});

test('user can update social media via me endpoint', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->patchJson('/api/v1/auth/me', [
        'social_media_data' => [
            'facebook' => 'https://facebook.com/demo-shop',
            'website' => 'https://demo.selloff.test',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.social_media_data.facebook', 'https://facebook.com/demo-shop')
        ->assertJsonPath('data.social_media_data.website', 'https://demo.selloff.test');

    $profile = VendorProfile::query()->where('user_id', $vendor->id)->firstOrFail();
    expect($profile->social_media_data['facebook'])->toBe('https://facebook.com/demo-shop');
});

test('buyer can update social media on user record', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'social_media_data' => [
            'instagram' => 'https://instagram.com/demo-buyer',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.social_media_data.instagram', 'https://instagram.com/demo-buyer');

    $buyer->refresh();
    expect($buyer->social_media_data['instagram'])->toBe('https://instagram.com/demo-buyer');
});

test('public vendor reviews are paginated', function () {
    $slug = VendorProfile::query()
        ->whereHas('user', fn ($q) => $q->where('email', 'vendor@selloff.test'))
        ->value('slug');

    $this->getJson('/api/v1/vendors/'.$slug.'/reviews')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'rating', 'review', 'user', 'product', 'created_at'],
                ],
                'current_page',
                'last_page',
            ],
        ]);
});

test('vendor can manage feedback inbox', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $feedback = Feedback::query()->updateOrCreate(
        [
            'vendor_id' => $vendor->id,
            'user_id' => $buyer->id,
        ],
        [
            'rating' => 5,
            'feedback_type' => 'positive',
            'feedback' => 'Excellent communication.',
            'status' => 'unread',
        ],
    );

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/feedback')
        ->assertOk()
        ->assertJsonFragment(['feedback' => 'Excellent communication.']);

    $this->patchJson('/api/v1/vendor/feedback/'.$feedback->id, ['status' => 'read'])
        ->assertOk()
        ->assertJsonPath('data.status', 'read');
});

test('vendor can moderate product comments', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    $comment = ProductComment::query()->create([
        'product_id' => $product->id,
        'comment' => 'Do you ship internationally?',
        'is_approved' => false,
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/comments')
        ->assertOk()
        ->assertJsonFragment(['comment' => 'Do you ship internationally?']);

    $this->patchJson('/api/v1/vendor/comments/'.$comment->id, [
        'is_approved' => true,
        'vendor_reply' => 'Yes, we ship worldwide.',
    ])
        ->assertOk()
        ->assertJsonPath('data.is_approved', true)
        ->assertJsonPath('data.vendor_reply', 'Yes, we ship worldwide.');
});

test('admin can read and update feedback', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $feedback = Feedback::query()->create([
        'vendor_id' => $vendor->id,
        'rating' => 4,
        'feedback_type' => 'positive',
        'feedback' => 'Admin moderation test',
        'status' => 'unread',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/feedback')
        ->assertOk()
        ->assertJsonFragment(['feedback' => 'Admin moderation test']);

    $this->patchJson('/api/v1/admin/feedback/'.$feedback->id, ['status' => 'archived'])
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');
});

test('admin can manage custom fields and tags', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $category = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/catalog/custom-fields', [
        'field_type' => 'dropdown',
        'label' => 'Pass J Color',
        'category_ids' => [$category->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Pass J Color');

    $fieldId = CustomField::query()->where('label', 'Pass J Color')->value('id');

    $this->postJson('/api/v1/admin/catalog/custom-fields/'.$fieldId.'/options', [
        'option_key' => 'blue',
        'label' => 'Blue',
    ])->assertCreated();

    $this->postJson('/api/v1/admin/tags', ['tag' => 'pass-j-demo', 'lang_id' => 1])
        ->assertCreated()
        ->assertJsonPath('data.tag', 'pass-j-demo');

    $tag = Tag::query()->where('tag', 'pass-j-demo')->firstOrFail();

    $this->putJson('/api/v1/admin/tags/'.$tag->id, ['tag' => 'pass-j-updated', 'lang_id' => 1])
        ->assertOk()
        ->assertJsonPath('data.tag', 'pass-j-updated');
});

test('admin can view earnings reports and update payout settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    expect(VendorEarning::query()->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/admin/earnings/summary')
        ->assertOk()
        ->assertJsonStructure(['data' => ['total_earned', 'order_count', 'by_vendor']]);

    $this->getJson('/api/v1/admin/earnings/seller-balances')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data' => [['seller_id', 'total_earned', 'available_balance']]]]);

    $this->getJson('/api/v1/admin/earnings?show=15')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data' => [['id', 'earned_amount', 'order_number']]]]);

    $this->putJson('/api/v1/admin/earnings/payout-settings', [
        'min_payout_amount' => 2500,
        'payout_bank_enabled' => true,
        'payout_description' => 'Weekly payouts on Fridays.',
    ])
        ->assertOk()
        ->assertJsonPath('data.min_payout_amount', 2500);
});

test('admin can moderate reviews and list digital sales', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $review = ProductReview::query()->updateOrCreate(
        ['product_id' => $product->id, 'user_id' => $buyer->id],
        ['rating' => 3, 'review' => 'Needs admin check', 'is_approved' => false],
    );

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/reviews')
        ->assertOk()
        ->assertJsonFragment(['review' => 'Needs admin check']);

    $this->patchJson('/api/v1/admin/reviews/'.$review->id, ['is_approved' => true])
        ->assertOk()
        ->assertJsonPath('data.is_approved', true);

    expect(DigitalSale::query()->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/admin/digital-sales')
        ->assertOk()
        ->assertJsonFragment(['purchase_code' => 'DEMO-DL-001']);
});

test('public and admin knowledge base crud', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/support/kb')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'how-to-track-orders']);

    $this->postJson('/api/v1/admin/support/kb/categories', [
        'name' => 'Pass J FAQ',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pass J FAQ');

    $categoryId = KnowledgeBaseCategory::query()->where('name', 'Pass J FAQ')->value('id');

    $this->postJson('/api/v1/admin/support/kb/articles', [
        'knowledge_base_category_id' => $categoryId,
        'title' => 'Pass J Returns',
        'slug' => 'pass-j-returns',
        'content' => '<p>Return within 7 days.</p>',
    ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'pass-j-returns');

    $this->getJson('/api/v1/support/kb/articles/pass-j-returns')
        ->assertOk()
        ->assertJsonPath('data.title', 'Pass J Returns');
});

test('admin can moderate conversations', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    $conversation = Conversation::query()->create([
        'sender_id' => $buyer->id,
        'receiver_id' => $vendor->id,
        'subject' => 'Abusive chat test',
        'last_message_at' => now(),
    ]);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $buyer->id,
        'receiver_id' => $vendor->id,
        'message' => 'Inappropriate content',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/messages/conversations')
        ->assertOk()
        ->assertJsonFragment(['subject' => 'Abusive chat test'])
        ->assertJsonPath('data.data.0.buyer.id', $buyer->id)
        ->assertJsonPath('data.data.0.vendor.id', $vendor->id);

    $this->getJson('/api/v1/admin/messages/conversations/'.$conversation->id)
        ->assertOk()
        ->assertJsonFragment(['message' => 'Inappropriate content'])
        ->assertJsonPath('data.conversation.buyer.id', $buyer->id)
        ->assertJsonPath('data.messages.0.role', 'buyer');

    $this->patchJson('/api/v1/admin/messages/conversations/'.$conversation->id, ['is_flagged' => true])
        ->assertOk()
        ->assertJsonPath('data.is_flagged', true);

    $this->deleteJson('/api/v1/admin/messages/conversations/'.$conversation->id, [], adminPinHeaders())
        ->assertOk();

    $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
});

test('guest can checkout with bank transfer', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $cartResponse = $this->postJson('/api/v1/guest/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertCreated();

    $guestToken = $cartResponse->json('data.guest_token');
    expect($guestToken)->not->toBeEmpty();

    $orderResponse = $this->postJson('/api/v1/checkout/guest', [
        'guest_email' => 'guest.checkout@selloff.test',
        'payment_method' => 'bank_transfer',
        'payment_note' => 'Guest transfer ref',
    ], [
        'X-Guest-Cart-Token' => $guestToken,
    ])
        ->assertCreated()
        ->assertJsonPath('data.order.guest_email', 'guest.checkout@selloff.test');

    $orderNumber = $orderResponse->json('data.order.order_number');
    $this->assertDatabaseHas('orders', [
        'order_number' => $orderNumber,
        'guest_email' => 'guest.checkout@selloff.test',
        'buyer_id' => null,
    ]);
});

test('vendor can purchase membership and list transactions', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['wallet_balance' => 50000]);
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/membership-plans/'.$plan->id.'/purchase', [
        'payment_method' => 'wallet_balance',
        'months' => 1,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('membership_transactions', [
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'status' => 'completed',
    ]);

    $this->getJson('/api/v1/vendor/membership/transactions')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Demo Vendor Pro']);
});

test('admin can list membership transactions', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    expect(MembershipTransaction::query()->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/admin/membership/transactions')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Demo Vendor Pro']);
});

test('vendor can list promotion transactions', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    expect(PromotionTransaction::query()->where('user_id', $vendor->id)->count())->toBeGreaterThan(0);

    $this->getJson('/api/v1/vendor/promotion-transactions')
        ->assertOk()
        ->assertJsonPath('data.total', fn ($total) => $total > 0);
});

test('registration queues email verification and token verifies email', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Pass',
        'last_name' => 'J6',
        'email' => 'passj6.verify@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email_verification_required', true);

    $user = User::query()->where('email', 'passj6.verify@selloff.test')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    $token = EmailVerificationToken::query()->where('user_id', $user->id)->value('token');
    expect($token)->not->toBeEmpty();
    expect(EmailJob::query()->where('to_email', 'passj6.verify@selloff.test')->count())->toBeGreaterThan(0);

    $this->postJson('/api/v1/auth/verify-email/'.$token)
        ->assertOk()
        ->assertJsonPath('data.me.user.email_verified', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('user can update location preference on me endpoint', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $country = Country::query()->firstOrFail();
    $state = State::query()->where('country_id', $country->id)->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'country_id' => $country->id,
        'state_id' => $state->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.country_id', $country->id)
        ->assertJsonPath('data.user.state_id', $state->id);
});

test('admin can update grouped platform settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'email',
        'settings' => [
            'mail_from_address' => 'noreply@selloff.test',
            'mail_from_name' => 'Selloff',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.mail_from_address', 'noreply@selloff.test');

    $this->putJson('/api/v1/settings', [
        'group' => 'maintenance',
        'settings' => [
            'maintenance_mode' => true,
            'maintenance_message' => 'Scheduled maintenance',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.maintenance_mode', true);

    $this->putJson('/api/v1/settings', [
        'group' => 'visual',
        'settings' => [
            'watermark_text' => 'Selloff.ng',
            'watermark_product_enabled' => true,
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.watermark_text', 'Selloff.ng')
        ->assertJsonPath('data.settings.watermark_product_enabled', true);

    $this->putJson('/api/v1/settings', [
        'group' => 'product',
        'settings' => [
            'image_file_format' => 'PNG',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.image_file_format', 'PNG');
});
