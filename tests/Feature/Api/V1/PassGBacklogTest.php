<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Content\Models\BlogCategory;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Content\Models\HomepageBanner;
use App\Modules\Selloff\Content\Models\Slider;
use App\Support\MediaUrl;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Modules\Selloff\Support\Models\SupportTicket;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can manage blog posts and categories', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/cms/blog/categories', ['name' => 'Marketplace News'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Marketplace News');

    $categoryId = BlogCategory::query()->where('name', 'Marketplace News')->value('id');

    $this->postJson('/api/v1/admin/cms/blog/posts', [
        'title' => 'Pass G launch post',
        'summary' => 'CMS test',
        'is_published' => true,
        'category_id' => $categoryId,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Pass G launch post');

    $post = BlogPost::query()->where('title', 'Pass G launch post')->firstOrFail();

    $this->getJson('/api/v1/admin/cms/blog/posts/'.$post->id)
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.title', 'Pass G launch post');

    $this->getJson('/api/v1/admin/cms/blog/images')->assertOk();

    $this->putJson('/api/v1/admin/cms/blog/posts/'.$post->id, ['is_published' => false])
        ->assertOk()
        ->assertJsonPath('data.is_published', false);

    $this->deleteJson('/api/v1/admin/cms/blog/posts/'.$post->id, [], adminPinHeaders())->assertOk();
});

test('public blog supports categories and pagination', function () {
    $category = BlogCategory::query()->create(['name' => 'Guides', 'slug' => 'guides']);
    BlogPost::query()->create([
        'user_id' => User::query()->first()->id,
        'slug' => 'guide-one',
        'title' => 'Guide One',
        'is_published' => true,
        'published_at' => now(),
    ])->categories()->sync([$category->id]);

    $this->getJson('/api/v1/blog/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'guides']);

    $this->getJson('/api/v1/blog/posts?category_id='.$category->id)
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('admin can manage homepage sliders and banners', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $initialSliders = Slider::query()->count();
    $initialBanners = HomepageBanner::query()->count();

    $sliderImageUrl = MediaUrl::resolve('uploads/slider/202606/slider_test.jpg');

    $this->postJson('/api/v1/admin/cms/homepage/sliders', [
        'title' => 'Hero slide',
        'image_path' => 'uploads/slider/202606/slider_test.jpg',
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('data.image_path', 'uploads/slider/202606/slider_test.jpg')
        ->assertJsonPath('data.image_url', $sliderImageUrl);

    $this->getJson('/api/v1/admin/cms/homepage/sliders')
        ->assertOk()
        ->assertJsonPath('data.0.image_url', $sliderImageUrl);

    $this->postJson('/api/v1/admin/cms/homepage/banners', [
        'title' => 'Promo banner',
        'image_path' => '/storage/banner.jpg',
        'is_active' => true,
    ])->assertCreated();

    $this->getJson('/api/v1/homepage/sliders')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Hero slide'])
        ->assertJsonFragment(['image_url' => $sliderImageUrl]);

    $this->getJson('/api/v1/homepage/banners')
        ->assertOk()
        ->assertJsonFragment(['title' => 'Promo banner']);

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.sliders.0.image_url', $sliderImageUrl)
        ->assertJsonStructure([
            'data' => [
                'sliders',
                'sections',
                'banners',
                'featured_categories',
                'promoted_products',
                'special_offers',
                'category_carousels',
                'brands',
                'blog_posts',
            ],
        ]);

    expect(Slider::query()->count())->toBe($initialSliders + 1);
    expect(HomepageBanner::query()->count())->toBe($initialBanners + 1);
});

test('products index supports discovery filters', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $product->update(['price_discounted' => 79999, 'is_promoted' => true]);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);

    $this->getJson('/api/v1/products?promoted=1')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1', 'is_promoted' => true]);

    $this->getJson('/api/v1/products?discounted=1')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);

    $this->getJson('/api/v1/products?min_price=50000&max_price=100000')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);

    if ($product->category_id) {
        $this->getJson('/api/v1/products?category_id='.$product->category_id)
            ->assertOk()
            ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);
    }
});

test('homepage and catalog include products with legacy numeric visibility', function () {
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $product->update(['visibility' => '1']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);

    $homepage = $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->json('data');

    $sectionCount = collect($homepage['sections'] ?? [])->sum(
        fn (array $section): int => count($section['products'] ?? []),
    );

    $surfaceCount = $sectionCount
        + count($homepage['promoted_products'] ?? [])
        + count($homepage['special_offers'] ?? [])
        + collect($homepage['category_carousels'] ?? [])->sum(
            fn (array $carousel): int => count($carousel['products'] ?? []),
        );

    expect($surfaceCount)->toBeGreaterThan(0);
});

test('product detail supports comments feedback and reports', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $vendorSlug = $product->vendor?->vendorProfile?->slug ?? 'demo-electronics';

    $this->getJson('/api/v1/products/'.$product->id.'/comments')
        ->assertOk()
        ->assertJsonFragment(['comment' => 'Is this still available in black?']);

    $this->getJson('/api/v1/vendors/'.$vendorSlug.'/feedback')
        ->assertOk()
        ->assertJsonFragment(['feedback' => 'Great seller — fast shipping on my order.']);

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/products/'.$product->id.'/comments', [
        'comment' => 'Does it include a charger?',
    ])
        ->assertCreated()
        ->assertJsonPath('data.comment', 'Does it include a charger?')
        ->assertJsonPath('data.is_approved', false);

    $this->postJson('/api/v1/products/'.$product->id.'/report', [
        'description' => 'Listing photos do not match the actual item.',
    ])->assertOk()
        ->assertJsonPath('success', true);
});

test('admin can read contact messages and moderate comments', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $message = ContactMessage::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'subject' => 'Help',
        'message' => 'Need support',
        'status' => 'pending',
    ]);

    $this->getJson('/api/v1/admin/contact/messages')
        ->assertOk()
        ->assertJsonFragment(['email' => 'ada@example.com'])
        ->assertJsonStructure(['data' => ['counts' => ['pending', 'read', 'archived', 'all']]]);

    $this->getJson('/api/v1/admin/contact/messages/'.$message->id)
        ->assertOk()
        ->assertJsonPath('data.subject', 'Help')
        ->assertJsonPath('data.reply_from_email', 'support@selloff.ng')
        ->assertJsonPath('data.replies', []);

    Mail::fake();

    $this->postJson('/api/v1/admin/contact/messages/'.$message->id.'/reply', [
        'message' => 'We are looking into this.',
    ])
        ->assertOk()
        ->assertJsonPath('data.sent_to', 'ada@example.com')
        ->assertJsonPath('data.reply_subject', 'Re: Help')
        ->assertJsonPath('data.thread.status', 'read')
        ->assertJsonPath('data.thread.replies.0.message', 'We are looking into this.')
        ->assertJsonPath('data.thread.replies.0.email_subject', 'Re: Help')
        ->assertJsonPath('data.thread.replies.0.sent_from', 'support@selloff.ng');

    $this->getJson('/api/v1/admin/contact/messages/'.$message->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.replies')
        ->assertJsonPath('data.replies.0.message', 'We are looking into this.');

    $this->assertDatabaseHas('contact_message_replies', [
        'contact_message_id' => $message->id,
        'message' => 'We are looking into this.',
        'sent_to' => 'ada@example.com',
        'sent_from' => 'support@selloff.ng',
        'email_subject' => 'Re: Help',
    ]);

    $this->patchJson('/api/v1/admin/contact/messages/'.$message->id, ['status' => 'archived'])
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    $this->deleteJson('/api/v1/admin/contact/messages/'.$message->id, [], adminPinHeaders())
        ->assertOk();

    $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);

    $product = Product::query()->firstOrFail();
    $comment = ProductComment::query()->create([
        'product_id' => $product->id,
        'name' => 'Philip Chimaobi',
        'email' => 'philchima@gmail.com',
        'comment' => 'Is this available?',
        'ip_address' => '102.88.36.178',
        'is_approved' => false,
    ]);

    $this->getJson('/api/v1/admin/comments?approved=0')
        ->assertOk()
        ->assertJsonFragment(['comment' => 'Is this available?'])
        ->assertJsonFragment(['name' => 'Philip Chimaobi'])
        ->assertJsonFragment(['email' => 'philchima@gmail.com'])
        ->assertJsonFragment(['ip_address' => '102.88.36.178']);

    $this->patchJson('/api/v1/admin/comments/'.$comment->id, ['is_approved' => true])
        ->assertOk()
        ->assertJsonPath('data.is_approved', true);
});

test('admin can manage brands', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/brands', ['name' => 'Pass G Brand', 'show_on_slider' => true])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Pass G Brand');

    $brand = Brand::query()->where('name', 'Pass G Brand')->firstOrFail();

    $this->putJson('/api/v1/admin/brands/'.$brand->id, ['show_on_slider' => false])
        ->assertOk()
        ->assertJsonPath('data.show_on_slider', false);
});

test('buyer can close support ticket', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $ticket = SupportTicket::query()->where('user_id', $buyer->id)->firstOrFail();

    $this->patchJson('/api/v1/support/tickets/'.$ticket->id.'/close')
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});
