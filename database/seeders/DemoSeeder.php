<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Support\DemoCatalogData;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Admin\Models\Language;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CategoryTranslation;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductOption;
use App\Modules\Selloff\Catalog\Models\ProductOptionValue;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Catalog\Models\ProductVariant;
use App\Modules\Selloff\Media\Models\ProductImage;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Content\Models\Page;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use App\Modules\Selloff\Order\Services\CheckoutService;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Services\PayoutService;
use App\Modules\Selloff\Promotion\Models\Coupon;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Modules\Selloff\Shipping\Models\DeliveryTimeOption;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use App\Modules\Selloff\Shipping\Models\ShippingZoneLocation;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Modules\Selloff\User\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    private ?int $demoCountryId = null;

    private ?int $demoStateId = null;

    private ?int $demoCityId = null;

    public function run(): void
    {
        $this->seedAdminBasics();
        $this->seedDefaultCmsPages();
        $this->seedLocations();
        $this->seedShipping();

        $users = $this->seedDemoUsers();
        $this->seedVendorShipping($users);
        $catalog = $this->seedExpandedCatalog($users);
        $this->seedProductShowcase($users['vendor']);
        $smartphones = Category::query()->where('slug', 'smartphones')->firstOrFail();
        $this->seedPromotion($users['vendor'], $smartphones);
        $this->seedSampleCommerce($users);
        $this->seedProductReviews($users['buyer']);
        $this->seedPass16Content($users);
        $this->seedPassHContent($users);
        $this->seedPassIContent($users);
        $this->seedPassJ5Content($users);
        $this->seedPassJ6Content($users);
        $this->seedPassKContent($users);
        $this->seedPassLContent($users);
        $this->seedMembershipRevampPlans();
        $this->seedPass19MobileContent($users);
        $this->seedHomepageContent();
    }

    private function seedMembershipRevampPlans(): void
    {
        $mapper = app(\App\Modules\Selloff\Payment\Services\MembershipLegacyEntitlementMapper::class);

        foreach ($mapper->demoTiers() as $tier) {
            $definition = $mapper->demoPlanDefinition($tier);
            $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->updateOrCreate(
                ['title' => $definition['title']],
                $definition,
            );
            $mapper->syncCategoryLimitsForPlan($plan, $tier);
        }

        $silver = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
            ->where('title', 'Silver Membership')
            ->first();

        if ($silver) {
            $demoVendorPro = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->updateOrCreate(
                ['title' => 'Demo Vendor Pro'],
                [
                    'description' => $silver->description,
                    'price' => $silver->price,
                    'currency_code' => $silver->currency_code,
                    'duration_days' => $silver->duration_days,
                    'is_active' => true,
                    'plan_order' => $silver->plan_order,
                    'is_popular' => true,
                    'is_free' => $silver->is_free,
                    'visibility_multiplier' => $silver->visibility_multiplier,
                    'global_listing_limit' => $silver->global_listing_limit,
                    'auto_bump_interval_hours' => $silver->auto_bump_interval_hours,
                    'top_credits_per_period' => $silver->top_credits_per_period,
                    'top_badge_label' => $silver->top_badge_label,
                    'top_rank_weight' => $silver->top_rank_weight,
                    'allow_website_link' => $silver->allow_website_link,
                    'allow_social_links' => $silver->allow_social_links,
                    'allow_whatsapp_link' => $silver->allow_whatsapp_link,
                    'hide_seller_feedback' => $silver->hide_seller_feedback,
                    'marketing_benefits' => $silver->marketing_benefits,
                    'features' => $silver->features,
                ],
            );

            $mapper->syncCategoryLimitsForPlan($demoVendorPro, 'silver');
        }
    }

    private function seedPass19MobileContent(array $users): void
    {
        $phones = Category::query()->where('slug', 'smartphones')->first();
        $vendor = $users['vendor'];

        $freebie = Product::query()->firstOrCreate(
            ['sku' => 'DEMO-FREEBIE-1'],
            [
                'vendor_id' => $vendor->id,
                'category_id' => $phones?->id,
                'slug' => 'demo-free-sample-'.Str::lower(Str::random(4)),
                'type' => 'physical',
                'listing_type' => 'sell_on_site',
                'status' => 'draft',
                'is_draft' => true,
                'visibility' => 'visible',
                'is_active' => false,
                'price' => 0,
                'currency_code' => 'NGN',
                'stock' => 100,
            ],
        );

        $freebie->update([
            'status' => 'published',
            'is_draft' => false,
            'is_active' => true,
            'visibility' => 'visible',
        ]);

        ProductTranslation::query()->firstOrCreate(
            ['product_id' => $freebie->id, 'locale' => 'en'],
            ['title' => 'Demo Free Sample Pack', 'description' => 'Free promotional sample for mobile API demos.'],
        );

        $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->first();
        if ($audio) {
            \App\Modules\Selloff\Catalog\Models\Wishlist::query()->firstOrCreate(
                ['user_id' => $users['buyer']->id, 'product_id' => $audio->id],
            );
        }
    }

    private function seedHomepageContent(): void
    {
        \App\Modules\Selloff\Content\Models\Slider::query()->firstOrCreate(
            ['title' => 'Shop the latest tech'],
            [
                'image_path' => 'https://images.unsplash.com/photo-1468495244123-6c6c332eeece?w=1200&h=500&fit=crop',
                'link' => '/products?category_id='.(Category::query()->where('slug', 'phones-and-tablets')->value('id') ?? ''),
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        \App\Modules\Selloff\Content\Models\Slider::query()->firstOrCreate(
            ['title' => 'Discover fashion deals'],
            [
                'image_path' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1200&h=500&fit=crop',
                'link' => '/products?category_id='.(Category::query()->where('slug', 'fashion')->value('id') ?? ''),
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        \App\Modules\Selloff\Content\Models\HomepageBanner::query()->updateOrCreate(
            ['title' => 'Electronics promo'],
            [
                'image_path' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&h=400&fit=crop',
                'link' => '/products',
                'banner_location' => 'new_arrivals',
                'banner_width' => 50,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        \App\Modules\Selloff\Content\Models\HomepageBanner::query()->updateOrCreate(
            ['title' => 'Marketplace deals'],
            [
                'image_path' => 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&h=400&fit=crop',
                'link' => '/products',
                'banner_location' => 'new_arrivals',
                'banner_width' => 50,
                'sort_order' => 2,
                'is_active' => true,
            ],
        );
    }

    private function seedPassHContent(array $users): void
    {
        \App\Modules\Selloff\Notification\Models\NewsletterSubscriber::query()->firstOrCreate(
            ['email' => 'newsletter@selloff.test'],
            ['is_active' => true, 'token' => 'demo-newsletter-token'],
        );

        ReferralProfile::query()->firstOrCreate(
            ['user_id' => $users['vendor']->id],
            [
                'referral_code' => 'VENDORREF',
                'affiliate_commission_rate' => 5,
                'affiliate_discount_rate' => 1,
                'vendor_affiliate_status' => 2,
            ],
        );

        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'affiliate_program' => json_encode([
                'status' => true,
                'type' => 'seller_based',
                'commission_rate' => 5,
                'discount_rate' => 1,
                'image_path' => null,
                'image_storage' => '',
                'localized' => [],
            ]),
        ], 'general');

        Product::query()->where('sku', 'DEMO-PHONE-1')->update([
            'is_affiliate' => true,
            'is_commission_set' => true,
            'commission_rate' => 8,
        ]);

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if ($product) {
            QuoteRequest::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'buyer_id' => $users['buyer']->id,
                ],
                [
                    'seller_id' => $users['vendor']->id,
                    'quantity' => 1,
                    'message' => 'Demo quote request',
                    'status' => 'pending',
                ],
            );
        }
    }

    private function seedPassIContent(array $users): void
    {
        VendorProfile::query()->where('user_id', $users['vendor']->id)->update([
            'shop_policies' => '<p>Demo shop: 7-day returns on unused items.</p>',
            'social_media_data' => [
                'facebook' => 'https://facebook.com/selloff-demo',
                'website' => 'https://demo.selloff.test',
            ],
        ]);

        \App\Modules\Selloff\Catalog\Models\Tag::query()->firstOrCreate(
            ['tag' => 'demo-tag', 'lang_id' => 1],
            ['lang_id' => 1],
        );

        $kbCategory = \App\Modules\Selloff\Support\Models\KnowledgeBaseCategory::query()->firstOrCreate(
            ['name' => 'Getting started'],
            ['sort_order' => 1, 'is_active' => true],
        );

        \App\Modules\Selloff\Support\Models\KnowledgeBaseArticle::query()->firstOrCreate(
            ['slug' => 'how-to-track-orders'],
            [
                'knowledge_base_category_id' => $kbCategory->id,
                'title' => 'How to track your orders',
                'content' => '<p>Visit <strong>Orders</strong> in your account to see shipment status.</p>',
                'is_active' => true,
            ],
        );

        \App\Modules\Selloff\Support\Models\Feedback::query()->firstOrCreate(
            [
                'vendor_id' => $users['vendor']->id,
                'user_id' => $users['buyer']->id,
                'feedback' => 'Great seller — fast shipping on my order.',
            ],
            [
                'rating' => 5,
                'feedback_type' => 'positive',
                'status' => 'unread',
                'moderation_status' => 'approved',
            ],
        );

        \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->updateOrCreate(
            ['title' => 'Demo Vendor Pro'],
            [
                'description' => 'Featured listings and lower commission.',
                'price' => 9999,
                'currency_code' => 'NGN',
                'duration_days' => 30,
                'is_active' => true,
                'plan_order' => 2,
                'is_popular' => true,
                'features' => [
                    'Unlimited product listings',
                    'Featured placement in search',
                    'Lower commission rate',
                    'Priority vendor support',
                ],
            ],
        );

        \App\Modules\Selloff\Content\Models\AdSpace::query()->firstOrCreate(
            ['ad_space_key' => 'homepage_sidebar'],
            [
                'title' => 'Homepage sidebar',
                'ad_code' => '<!-- demo ad -->',
                'is_active' => true,
                'desktop_width' => 336,
                'desktop_height' => 280,
                'mobile_width' => 300,
                'mobile_height' => 250,
            ],
        );

        foreach ([
            'index_1' => 'Homepage index 1',
            'index_2' => 'Homepage index 2',
            'products_1' => 'Products listing 1',
            'products_2' => 'Products listing 2',
            'product_1' => 'Product detail 1',
            'product_2' => 'Product detail 2',
            'blog_1' => 'Blog Ad Space 1',
            'blog_2' => 'Blog Ad Space 2',
        ] as $key => $title) {
            \App\Modules\Selloff\Content\Models\AdSpace::query()->firstOrCreate(
                ['ad_space_key' => $key],
                [
                    'title' => $title,
                    'ad_code' => "<!-- demo {$key} ad -->",
                    'is_active' => true,
                    'desktop_width' => 728,
                    'desktop_height' => 90,
                    'mobile_width' => 300,
                    'mobile_height' => 250,
                ],
            );
        }

        $coupon = Coupon::query()->first();
        if ($coupon) {
            \App\Modules\Selloff\Promotion\Models\CouponUsage::query()->firstOrCreate(
                ['user_id' => $users['buyer']->id, 'coupon_code' => $coupon->coupon_code],
                ['order_id' => Order::query()->where('buyer_id', $users['buyer']->id)->value('id')],
            );
        }

        $order = Order::query()->where('buyer_id', $users['buyer']->id)->first();
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if ($order && $phone) {
            $lineItem = $order->items()->where('product_id', $phone->id)->first();
            \App\Modules\Selloff\Order\Models\DigitalSale::query()->firstOrCreate(
                ['purchase_code' => 'DEMO-DL-001'],
                [
                    'order_id' => $order->id,
                    'product_id' => $phone->id,
                    'product_title' => $phone->translations()->first()?->title,
                    'buyer_id' => $users['buyer']->id,
                    'seller_id' => $users['vendor']->id,
                    'license_key' => 'DEMO-LICENSE-KEY',
                    'price' => $lineItem?->total_price ?? $order->price_total,
                    'currency_code' => $order->currency_code ?? 'NGN',
                ],
            );
        }
    }

    private function seedPassJ5Content(array $users): void
    {
        $users['vendor']->update(['wallet_balance' => 100000]);

        $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
            ->where('title', 'Demo Vendor Pro')
            ->first();

        if ($plan) {
            \App\Modules\Selloff\Payment\Models\MembershipTransaction::query()->firstOrCreate(
                [
                    'user_id' => $users['vendor']->id,
                    'membership_plan_id' => $plan->id,
                    'payment_method' => 'wallet_balance',
                ],
                [
                    'amount' => $plan->price,
                    'status' => 'completed',
                ],
            );
        }

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if ($product) {
            \App\Modules\Selloff\Promotion\Models\PromotionTransaction::query()->firstOrCreate(
                [
                    'user_id' => $users['vendor']->id,
                    'product_id' => $product->id,
                ],
                [
                    'amount' => 2500,
                    'currency_code' => 'NGN',
                    'status' => 'completed',
                ],
            );
        }
    }

    private function seedPassKContent(array $users): void
    {
        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'price_per_day'],
            ['value' => 1000, 'group' => 'featured_pricing'],
        );

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'price_per_month'],
            ['value' => 25000, 'group' => 'featured_pricing'],
        );

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'free_product_promotion'],
            ['value' => false, 'group' => 'featured_pricing'],
        );

        foreach ([7 => [1500, 100], 14 => [2800, 175], 30 => [5000, 300], 60 => [9000, 500]] as $days => [$price, $weight]) {
            \App\Models\PlatformSetting::query()->updateOrCreate(
                ['key' => "top_ad_price_{$days}"],
                ['value' => $price, 'group' => 'top_ad_pricing'],
            );
            \App\Models\PlatformSetting::query()->updateOrCreate(
                ['key' => "top_ad_weight_{$days}"],
                ['value' => $weight, 'group' => 'top_ad_pricing'],
            );
        }

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'top_ad_stack_weight_bonus'],
            ['value' => 75, 'group' => 'top_ad_pricing'],
        );

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'top_ad_badge_label'],
            ['value' => 'TOP', 'group' => 'top_ad_pricing'],
        );

        $english = Language::query()->where('code', 'en')->first();
        if ($english) {
            \App\Modules\Selloff\Admin\Models\LanguageTranslation::query()->firstOrCreate(
                ['language_id' => $english->id, 'label' => 'welcome'],
                ['translation' => 'Welcome to Selloff'],
            );
        }

        $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
            ->where('title', 'Demo Vendor Pro')
            ->first();

        if ($plan) {
            \App\Modules\Selloff\Payment\Models\UserMembershipPlan::query()->updateOrCreate(
                ['user_id' => $users['vendor']->id, 'membership_plan_id' => $plan->id],
                ['is_active' => true, 'expires_at' => now()->addDays(30)],
            );
        }

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if ($product) {
            $product->update([
                'is_promoted' => true,
                'promoted_until' => now()->addDays(7),
                'price_discounted' => 79999,
            ]);
        }
    }

    private function seedPassLContent(array $users): void
    {
        $post = \App\Modules\Selloff\Content\Models\BlogPost::query()->first();
        if ($post) {
            \App\Modules\Selloff\Content\Models\BlogTag::query()->firstOrCreate(
                ['blog_post_id' => $post->id, 'tag_slug' => 'marketplace-news'],
                ['tag' => 'Marketplace News'],
            );

            \App\Modules\Selloff\Content\Models\BlogComment::query()->firstOrCreate(
                ['blog_post_id' => $post->id, 'user_id' => $users['buyer']->id, 'comment' => 'Great update!'],
                ['name' => $users['buyer']->first_name, 'email' => $users['buyer']->email, 'status' => 'pending'],
            );
        }

        \App\Modules\Selloff\Content\Models\Page::query()->firstOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms of Service',
                'content' => '<p>Demo terms page.</p>',
                'is_active' => true,
                'is_custom' => true,
                'location' => 'information',
            ],
        );

        \App\Modules\Selloff\Payment\Models\MembershipTransaction::query()->firstOrCreate(
            [
                'user_id' => $users['vendor2']->id,
                'membership_plan_id' => \App\Modules\Selloff\Payment\Models\MembershipPlan::query()->value('id'),
                'payment_method' => 'bank_transfer',
            ],
            ['amount' => 5000, 'status' => 'pending'],
        );

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if ($product) {
            \App\Modules\Selloff\Promotion\Models\PromotionTransaction::query()->firstOrCreate(
                ['user_id' => $users['vendor']->id, 'product_id' => $product->id, 'status' => 'pending'],
                ['amount' => 1000, 'currency_code' => 'NGN'],
            );
        }

        \App\Modules\Selloff\Payment\Models\WalletDeposit::query()->firstOrCreate(
            ['user_id' => $users['buyer']->id, 'payment_method' => 'bank_transfer', 'status' => 'pending'],
            ['amount' => 2500, 'currency_code' => 'NGN'],
        );
    }

    private function seedPassJ6Content(array $users): void
    {
        if ($this->demoCountryId && $this->demoStateId) {
            $users['buyer']->update([
                'country_id' => $this->demoCountryId,
                'state_id' => $this->demoStateId,
            ]);
        }

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'mail_from_address'],
            ['value' => 'noreply@selloff.test', 'group' => 'email'],
        );

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'social_login_enabled'],
            ['value' => true, 'group' => 'social_login'],
        );

        \App\Models\PlatformSetting::query()->updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => false, 'group' => 'maintenance'],
        );

        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'site_announcement' => 'Welcome to the Selloff demo marketplace.',
            'facebook_url' => 'https://facebook.com/selloff-demo',
            'twitter_url' => 'https://twitter.com/selloff-demo',
            'instagram_url' => 'https://instagram.com/selloff-demo',
            'request_documents_vendors' => true,
            'explanation_documents_vendors' => 'Upload a government-issued ID and a selfie holding your ID with today\'s date and Selloff.ng written on paper.',
        ], 'general');
    }

    private function seedPass16Content(array $users): void
    {
        \App\Modules\Selloff\Content\Models\BlogPost::query()->firstOrCreate(
            ['slug' => 'welcome-to-selloff'],
            [
                'user_id' => $users['vendor']->id,
                'title' => 'Welcome to Selloff',
                'summary' => 'Marketplace migration demo post.',
                'content' => '<p>Selloff SPA and API parity demo content.</p>',
                'is_published' => true,
                'published_at' => now(),
            ],
        );

        \App\Modules\Selloff\Content\Models\Page::query()->firstOrCreate(
            ['slug' => 'about-us'],
            [
                'title' => 'About Selloff',
                'content' => '<p>Selloff is a multi-vendor marketplace demo environment.</p>',
                'is_active' => true,
                'is_custom' => true,
                'location' => 'information',
            ],
        );

        \App\Modules\Selloff\Content\Models\Page::query()->firstOrCreate(
            ['slug' => 'terms-and-conditions'],
            [
                'title' => 'Terms and Conditions',
                'content' => '<p>Standard marketplace terms apply to all buyers and sellers.</p>',
                'is_active' => true,
                'is_custom' => true,
                'location' => 'information',
            ],
        );

        \App\Modules\Selloff\Content\Models\Page::query()->firstOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'content' => '<p>We respect your privacy and protect your personal data.</p>',
                'is_active' => true,
                'is_custom' => true,
                'location' => 'information',
            ],
        );

        $category = Category::query()->where('slug', 'smartphones')->first();
        if ($category) {
            $field = \App\Modules\Selloff\Catalog\Models\CustomField::query()->updateOrCreate(
                ['legacy_id' => 9001],
                [
                    'field_type' => 'single_select',
                    'label' => 'Condition',
                    'is_required' => true,
                    'status' => true,
                    'is_product_filter' => true,
                    'product_filter_key' => 'condition',
                    'field_order' => 1,
                ],
            );
            $field->categories()->syncWithoutDetaching([$category->id]);

            $likeNew = \App\Modules\Selloff\Catalog\Models\CustomFieldOption::query()->firstOrCreate(
                ['custom_field_id' => $field->id, 'option_key' => 'like_new'],
                ['label' => 'Like new'],
            );
            $used = \App\Modules\Selloff\Catalog\Models\CustomFieldOption::query()->firstOrCreate(
                ['custom_field_id' => $field->id, 'option_key' => 'used'],
                ['label' => 'Used'],
            );

            foreach (
                [
                    ['sku' => 'DEMO-PHONE-1', 'option' => $likeNew],
                    ['sku' => 'DEMO-PHONE-2', 'option' => $used],
                ] as $assignment
            ) {
                $product = Product::query()->where('sku', $assignment['sku'])->first();
                if (! $product) {
                    continue;
                }

                \App\Modules\Selloff\Catalog\Models\CustomFieldProduct::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'custom_field_id' => $field->id,
                    ],
                    [
                        'custom_field_option_id' => $assignment['option']->id,
                        'product_filter_key' => 'condition',
                    ],
                );
            }
        }

        $ticket = \App\Modules\Selloff\Support\Models\SupportTicket::query()->firstOrCreate(
            ['subject' => 'Demo support ticket'],
            ['user_id' => $users['buyer']->id, 'status' => 'open'],
        );

        \App\Modules\Selloff\Support\Models\SupportMessage::query()->firstOrCreate(
            ['support_ticket_id' => $ticket->id, 'user_id' => $users['buyer']->id],
            ['message' => 'How do I track my order?', 'is_admin' => false],
        );

        \App\Modules\Selloff\Support\Models\ContactMessage::query()->firstOrCreate(
            ['email' => 'demo-contact@selloff.test'],
            [
                'name' => 'Demo Contact',
                'subject' => 'Demo inquiry',
                'message' => 'This is a seeded contact message for admin inbox tests.',
                'status' => 'pending',
            ],
        );

        \App\Modules\Selloff\Content\Models\BlogCategory::query()->firstOrCreate(
            ['slug' => 'marketplace-updates'],
            ['name' => 'Marketplace Updates'],
        );

        \App\Modules\Selloff\Content\Models\HomepageBanner::query()->firstOrCreate(
            ['title' => 'Legacy demo banner'],
            [
                'image_path' => '/homepage/banner-deals.svg',
                'link' => '/products',
                'banner_location' => 'default',
                'banner_width' => 100,
                'sort_order' => 99,
                'is_active' => false,
            ],
        );

        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->first();
        if ($product) {
            \App\Modules\Selloff\Review\Models\ProductComment::query()->firstOrCreate(
                ['product_id' => $product->id, 'comment' => 'Demo comment awaiting moderation'],
                ['is_approved' => false],
            );

            \App\Modules\Selloff\Escrow\Models\EscrowTransaction::query()->firstOrCreate(
                ['ref' => 'DEMOESCROW1'],
                [
                    'buyer_id' => $users['buyer']->id,
                    'seller_id' => $users['vendor']->id,
                    'product_id' => $product->id,
                    'amount' => $product->price,
                    'currency_code' => 'NGN',
                    'status' => 'pending',
                    'buyer_agreement_token' => 'demo-buyer-escrow-token',
                    'seller_agreement_token' => 'demo-seller-escrow-token',
                    'buyer_email' => $users['buyer']->email,
                    'seller_email' => $users['vendor']->email,
                ],
            );

            $shippedProduct = Product::query()->where('sku', 'DEMO-TAB-1')->first();
            if ($shippedProduct) {
                \App\Modules\Selloff\Escrow\Models\EscrowTransaction::query()->firstOrCreate(
                    ['ref' => 'DEMOESCROW2'],
                    [
                        'buyer_id' => $users['buyer']->id,
                        'seller_id' => $users['vendor']->id,
                        'product_id' => $shippedProduct->id,
                        'amount' => $shippedProduct->price,
                        'currency_code' => 'NGN',
                        'status' => 'processing',
                        'buyer_agreed' => true,
                        'seller_agreed' => true,
                        'payment_link_sent' => true,
                        'payment_received' => true,
                        'seller_shipped_item' => true,
                        'delivery_cost' => 2500,
                        'delivery_address' => '12 Admiralty Way, Lekki, Lagos',
                        'buyer_agreement_token' => 'demo-buyer-escrow-deliver-token',
                        'seller_agreement_token' => 'demo-seller-escrow-deliver-token',
                        'buyer_email' => $users['buyer']->email,
                        'seller_email' => $users['vendor']->email,
                        'metadata' => ['payment_link_url' => 'https://paystack.com/pay/demo-escrow'],
                    ],
                );
            }

            \App\Modules\Selloff\Affiliate\Models\AffiliateEarning::query()->firstOrCreate(
                [
                    'referrer_id' => $users['buyer']->id,
                    'product_id' => $product->id,
                    'seller_id' => $users['vendor']->id,
                ],
                [
                    'commission_rate' => 5,
                    'earned_amount' => 500,
                    'currency_code' => 'NGN',
                    'exchange_rate' => 1,
                ],
            );
        }

        $users['buyer']->update([
            'shop_opening_status' => 1,
            'shop_request_date' => now(),
            'vendor_documents' => [['name' => 'id.jpg', 'path' => 'uploads/demo/id.jpg']],
        ]);
    }

    private function seedAdminBasics(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'NGN'],
            ['name' => 'Nigerian Naira', 'symbol' => '₦', 'exchange_rate' => 1, 'status' => true],
        );

        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 0.0012, 'status' => true],
        );

        Language::query()->updateOrCreate(
            ['code' => 'en'],
            [
                'name' => 'English',
                'language_code' => 'en-US',
                'text_direction' => 'ltr',
                'language_order' => 1,
                'text_editor_lang' => 'en',
                'is_default' => true,
                'status' => true,
            ],
        );
    }

    private function seedDefaultCmsPages(): void
    {
        $english = Language::query()->where('code', 'en')->first();
        $langId = $english?->id;

        $defaults = [
            [
                'page_default_name' => 'terms_conditions',
                'slug' => 'terms-conditions',
                'title' => 'Terms & Conditions',
                'description' => 'Terms & Conditions Page',
                'keywords' => 'Terms, Conditions, Page',
                'content' => '<p>By opening a shop on Selloff you agree to our marketplace terms.</p>',
                'location' => 'information',
                'page_order' => 1,
            ],
            [
                'page_default_name' => 'contact',
                'slug' => 'contact',
                'title' => 'Contact',
                'description' => 'Contact Page',
                'keywords' => 'Contact, Page',
                'content' => '',
                'location' => 'top_menu',
                'page_order' => 1,
            ],
            [
                'page_default_name' => 'blog',
                'slug' => 'blog',
                'title' => 'Blog',
                'description' => 'Blog Page',
                'keywords' => 'Blog, Page',
                'content' => '',
                'location' => 'quick_links',
                'page_order' => 1,
            ],
            [
                'page_default_name' => 'shops',
                'slug' => 'shops',
                'title' => 'Shops',
                'description' => 'Shops Page',
                'keywords' => 'Shops, Page',
                'content' => '',
                'location' => 'quick_links',
                'page_order' => 1,
            ],
        ];

        foreach ($defaults as $page) {
            Page::query()->updateOrCreate(
                ['page_default_name' => $page['page_default_name']],
                [
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'description' => $page['description'],
                    'keywords' => $page['keywords'],
                    'content' => $page['content'],
                    'locale' => 'en',
                    'lang_id' => $langId,
                    'location' => $page['location'],
                    'page_order' => $page['page_order'],
                    'is_active' => true,
                    'title_active' => true,
                    'is_custom' => false,
                ],
            );
        }
    }

    private function seedLocations(): void
    {
        $country = Country::query()->firstOrCreate(
            ['code' => 'NG'],
            ['name' => 'Nigeria', 'continent_code' => 'AF', 'status' => true],
        );

        $state = State::query()->firstOrCreate(
            ['country_id' => $country->id, 'name' => 'Lagos'],
            ['code' => 'LA', 'status' => true],
        );

        City::query()->firstOrCreate(
            ['state_id' => $state->id, 'name' => 'Ikeja'],
            ['status' => true],
        );

        $this->demoCountryId = $country->id;
        $this->demoStateId = $state->id;
        $this->demoCityId = City::query()->where('state_id', $state->id)->value('id');
    }

    private function seedShipping(): void
    {
        if (! $this->demoCountryId || ! $this->demoStateId) {
            return;
        }

        $zone = ShippingZone::query()->firstOrCreate(
            ['name' => 'Nigeria Standard'],
            ['status' => true],
        );

        ShippingZoneLocation::query()->firstOrCreate(
            [
                'shipping_zone_id' => $zone->id,
                'country_id' => $this->demoCountryId,
                'state_id' => $this->demoStateId,
            ],
        );

        ShippingMethod::query()->firstOrCreate(
            [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standard Delivery',
            ],
            ['flat_rate' => 1500, 'status' => true],
        );
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedVendorShipping(array $users): void
    {
        if (! $this->demoCountryId) {
            return;
        }

        $vendor = $users['vendor'] ?? null;
        if (! $vendor) {
            return;
        }

        $zone = ShippingZone::query()->updateOrCreate(
            ['seller_id' => $vendor->id, 'name' => 'Whole Nigeria'],
            [
                'estimated_delivery' => '3-5 days',
                'status' => true,
            ],
        );

        ShippingZoneLocation::query()->firstOrCreate(
            [
                'shipping_zone_id' => $zone->id,
                'country_id' => $this->demoCountryId,
                'state_id' => null,
            ],
        );

        ShippingMethod::query()->firstOrCreate(
            [
                'shipping_zone_id' => $zone->id,
                'name' => 'Flat Rate',
            ],
            [
                'method_type' => 'flat_rate',
                'cost_calculation_type' => 'per_order',
                'shipping_flat_cost' => 1500,
                'flat_rate' => 1500,
                'status' => true,
            ],
        );

        foreach (['3 - 5 Business Days', '2 Business Days', '1 Business Day'] as $label) {
            DeliveryTimeOption::query()->firstOrCreate(
                ['seller_id' => $vendor->id, 'label' => $label],
                ['status' => true],
            );
        }
    }

    /**
     * @return array<string, User>
     */
    private function seedDemoUsers(): array
    {
        $users = [];

        foreach (DemoCatalogData::VENDORS as $key => $definition) {
            $passwordKey = match ($key) {
                'vendor' => config('app.demo_vendor_password', 'password'),
                'vendor2' => config('app.demo_vendor2_password', 'password'),
                default => 'password',
            };

            $email = match ($key) {
                'vendor' => config('app.demo_vendor_email', $definition['email']),
                'vendor2' => config('app.demo_vendor2_email', $definition['email']),
                default => $definition['email'],
            };

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $definition['first_name'],
                    'last_name' => $definition['last_name'],
                    'slug' => $definition['slug'],
                    'password' => Hash::make($passwordKey),
                    'avatar' => $definition['avatar'],
                    'is_enable_login' => true,
                    'is_disable' => false,
                    'email_verified_at' => now(),
                ],
            );

            $user->update([
                'avatar' => $definition['avatar'],
                'phone_number' => $definition['phone_number'] ?? '+2348012345678',
            ]);
            $user->syncRoles(['vendor']);

            VendorProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'shop_name' => $definition['shop_name'],
                    'slug' => $definition['shop_slug'],
                    'is_verified_seller' => true,
                    'commission_rate' => $definition['commission_rate'],
                ],
            );

            $users[$key] = $user;
        }

        $member = User::firstOrCreate(
            ['email' => config('app.demo_member_email', 'buyer@selloff.test')],
            [
                'first_name' => 'Demo',
                'last_name' => 'Buyer',
                'slug' => 'demo-buyer',
                'password' => Hash::make(config('app.demo_member_password', 'password')),
                'wallet_balance' => 500000,
                'is_enable_login' => true,
                'is_disable' => false,
                'email_verified_at' => now(),
            ],
        );
        $member->syncRoles(['member']);

        $users['buyer'] = $member;

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @return array{category: Category}
     */
    private function seedSubcategories(): void
    {
        foreach (DemoCatalogData::SUBCATEGORIES as $parentSlug => $children) {
            $parent = Category::query()->where('slug', $parentSlug)->first();
            if ($parent === null) {
                continue;
            }

            $order = 0;
            foreach ($children as $slug => $definition) {
                $order++;
                $child = Category::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'parent_id' => $parent->id,
                        'status' => true,
                        'is_featured' => false,
                        'show_on_main_menu' => true,
                        'show_products_on_index' => false,
                        'category_order' => $order,
                    ],
                );

                CategoryTranslation::query()->updateOrCreate(
                    ['category_id' => $child->id, 'locale' => 'en'],
                    ['name' => $definition['name'], 'description' => $definition['description']],
                );
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     * @return array{category: Category}
     */
    private function seedExpandedCatalog(array $users): array
    {
        $brands = [];
        $primaryCategory = null;
        $order = 0;

        foreach (DemoCatalogData::CATEGORIES as $slug => $definition) {
            $order++;
            $category = Category::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => null,
                    'status' => true,
                    'is_featured' => true,
                    'show_on_main_menu' => true,
                    'show_products_on_index' => true,
                    'category_order' => $order,
                ],
            );

            $category->update([
                'image_path' => $definition['image'],
                'homepage_order' => $definition['homepage_order'],
            ]);

            CategoryTranslation::query()->updateOrCreate(
                ['category_id' => $category->id, 'locale' => 'en'],
                ['name' => $definition['name'], 'description' => $definition['description']],
            );

            $primaryCategory ??= $category;
        }

        $this->seedSubcategories();

        foreach (DemoCatalogData::products() as $index => $item) {
            $vendor = $users[$item['vendor']];
            $category = Category::query()->where('slug', $item['category'])->firstOrFail();

            $brands[$item['brand']] ??= Brand::query()->firstOrCreate(
                ['name' => $item['brand']],
                ['show_on_slider' => $item['brand'] === 'Samsung'],
            );

            $this->upsertProduct($vendor, $category, $brands[$item['brand']], $item, $index);
        }

        $vendor = $users['vendor'];
        $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
        $brand = $brands['Samsung'] ?? Brand::query()->firstOrFail();

        $pending = Product::query()->firstOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => 'DEMO-PENDING-1'],
            [
                'category_id' => $phones->id,
                'brand_id' => $brand->id,
                'slug' => 'demo-pending-tablet',
                'type' => 'physical',
                'listing_type' => 'sell_on_site',
                'status' => 'pending',
                'visibility' => 'visible',
                'is_active' => true,
                'is_verified' => false,
                'is_draft' => false,
                'price' => 59999,
                'currency_code' => 'NGN',
                'stock' => 10,
            ],
        );

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $pending->id, 'locale' => 'en'],
            [
                'title' => 'Demo Pending Tablet',
                'description' => 'Awaiting admin moderation.',
                'short_description' => 'Pending product',
            ],
        );

        $this->syncProductImages($pending, [
            'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=800&h=800&fit=crop',
        ]);

        return ['category' => $primaryCategory ?? $phones];
    }

    private function seedProductShowcase(User $vendor): void
    {
        if ($this->demoStateId) {
            $vendor->update([
                'state_id' => $this->demoStateId,
                'city_id' => $this->demoCityId,
            ]);
        }

        Product::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereNull('state_id')
            ->update([
                'country_id' => $this->demoCountryId,
                'state_id' => $this->demoStateId,
                'city_id' => $this->demoCityId,
            ]);

        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        if (! $phone) {
            return;
        }

        $phone->update([
            'is_promoted' => true,
            'price_discounted' => 79999,
            'created_at' => now(),
        ]);

        $this->seedProductOptions($phone);

        $laptop = Product::query()->where('sku', 'DEMO-LAPTOP-2')->first();
        if ($laptop) {
            $laptop->update(['listing_type' => 'bidding']);
        }
    }

    private function seedProductOptions(Product $phone): void
    {
        $colorOption = ProductOption::query()->firstOrCreate(
            ['product_id' => $phone->id, 'name' => 'Color'],
            ['sort_order' => 0],
        );

        $storageOption = ProductOption::query()->firstOrCreate(
            ['product_id' => $phone->id, 'name' => 'Storage'],
            ['sort_order' => 1],
        );

        $black = ProductOptionValue::query()->firstOrCreate(
            ['product_option_id' => $colorOption->id, 'value' => 'Black'],
            ['sort_order' => 0],
        );
        $white = ProductOptionValue::query()->firstOrCreate(
            ['product_option_id' => $colorOption->id, 'value' => 'White'],
            ['sort_order' => 1],
        );
        $storage128 = ProductOptionValue::query()->firstOrCreate(
            ['product_option_id' => $storageOption->id, 'value' => '128GB'],
            ['sort_order' => 0],
        );
        $storage256 = ProductOptionValue::query()->firstOrCreate(
            ['product_option_id' => $storageOption->id, 'value' => '256GB'],
            ['sort_order' => 1],
        );

        $variants = [
            [
                'sku' => 'DEMO-PHONE-1-BLK-128',
                'price' => 89999,
                'price_discounted' => 79999,
                'stock' => 12,
                'is_default' => true,
                'values' => [$black->id, $storage128->id],
            ],
            [
                'sku' => 'DEMO-PHONE-1-BLK-256',
                'price' => 99999,
                'price_discounted' => 89999,
                'stock' => 8,
                'is_default' => false,
                'values' => [$black->id, $storage256->id],
            ],
            [
                'sku' => 'DEMO-PHONE-1-WHT-128',
                'price' => 89999,
                'price_discounted' => 79999,
                'stock' => 5,
                'is_default' => false,
                'values' => [$white->id, $storage128->id],
            ],
            [
                'sku' => 'DEMO-PHONE-1-WHT-256',
                'price' => 99999,
                'price_discounted' => 89999,
                'stock' => 3,
                'is_default' => false,
                'values' => [$white->id, $storage256->id],
            ],
        ];

        foreach ($variants as $variantData) {
            $valueIds = $variantData['values'];
            unset($variantData['values']);

            $variant = ProductVariant::query()->firstOrCreate(
                ['product_id' => $phone->id, 'sku' => $variantData['sku']],
                [
                    ...$variantData,
                    'variant_hash' => md5(implode('-', $valueIds)),
                ],
            );

            $variant->optionValues()->sync($valueIds);
        }
    }

    /**
     * @param  array{
     *   sku: string,
     *   title: string,
     *   price: int,
     *   images: list<string>,
     *   short_description?: string,
     *   listing_type?: string,
     *   price_discounted?: int,
     *   is_promoted?: bool,
     * }  $item
     */
    private function upsertProduct(User $vendor, Category $category, Brand $brand, array $item, int $index): Product
    {
        $product = Product::query()->updateOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => $item['sku']],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'slug' => Str::slug($item['title']),
                'type' => 'physical',
                'listing_type' => $item['listing_type'] ?? 'sell_on_site',
                'status' => 'published',
                'visibility' => 'visible',
                'is_active' => true,
                'is_verified' => true,
                'price' => $item['price'],
                'price_discounted' => $item['price_discounted'] ?? null,
                'is_promoted' => $item['is_promoted'] ?? false,
                'currency_code' => 'NGN',
                'stock' => max(3, 25 - ($index % 20)),
                'country_id' => $this->demoCountryId,
                'state_id' => $this->demoStateId,
                'city_id' => $this->demoCityId,
            ],
        );

        ProductTranslation::query()->updateOrCreate(
            ['product_id' => $product->id, 'locale' => 'en'],
            [
                'title' => $item['title'],
                'description' => 'Quality '.$item['title'].' from '.$vendor->vendorProfile?->shop_name.'. Ships nationwide from Lagos.',
                'short_description' => $item['short_description'] ?? $item['title'],
            ],
        );

        $this->syncProductImages($product, $item['images']);

        return $product;
    }

    /**
     * @param  list<string>  $images
     */
    private function syncProductImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach (array_values($images) as $sortOrder => $url) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $url,
                'disk' => 'external',
                'sort_order' => $sortOrder,
                'is_primary' => $sortOrder === 0,
            ]);
        }
    }

    private function seedPromotion(User $vendor, Category $category): void
    {
        Coupon::query()->firstOrCreate(
            ['coupon_code' => 'DEMO10'],
            [
                'seller_id' => $vendor->id,
                'discount_rate' => 10,
                'usage_type' => 'multiple',
                'is_public' => true,
                'minimum_order_amount' => 0,
                'currency_code' => 'NGN',
                'expires_at' => now()->addMonths(3),
                'category_ids' => [$category->id],
            ],
        );
    }

    /**
     * @param  array{vendor: User, vendor2: User, buyer: User}  $users
     */
    private function seedSampleCommerce(array $users): void
    {
        if (Order::query()->where('buyer_id', $users['buyer']->id)->exists()) {
            return;
        }

        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $dress = Product::query()->where('sku', 'DEMO-FASHION-1')->firstOrFail();

        $this->placeWalletOrder($users['buyer'], $phone);
        $this->placeWalletOrder($users['buyer'], $dress);

        $users['buyer']->refresh();

        app(PayoutService::class)->requestPayout($users['vendor'], 2500);
    }

    private function placeWalletOrder(User $buyer, Product $product): Order
    {
        $cartService = app(CartService::class);
        $checkoutService = app(CheckoutService::class);

        $cart = $cartService->resolveCart($buyer);
        $cart->items()->delete();
        $cart->update(['coupon_code' => null, 'shipping_cost' => 0, 'shipping_data' => null]);

        $cartService->addItem($cart, $product, 1);
        $checkout = $checkoutService->createFromCart($buyer->fresh(), 'wallet_balance');

        return $checkoutService->completeWalletPayment($buyer->fresh(), $checkout);
    }

    private function seedProductReviews(User $buyer): void
    {
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->first();
        $dress = Product::query()->where('sku', 'DEMO-FASHION-1')->first();

        if ($phone) {
            ProductReview::query()->firstOrCreate(
                ['product_id' => $phone->id, 'user_id' => $buyer->id],
                [
                    'rating' => 5,
                    'review' => 'Great demo phone — fast delivery and solid build quality.',
                    'is_approved' => true,
                ],
            );

            \App\Modules\Selloff\Review\Models\ProductComment::query()->firstOrCreate(
                ['product_id' => $phone->id, 'comment' => 'Is this still available in black?'],
                [
                    'user_id' => $buyer->id,
                    'is_approved' => true,
                ],
            );
        }

        if ($dress) {
            ProductReview::query()->firstOrCreate(
                ['product_id' => $dress->id, 'user_id' => $buyer->id],
                [
                    'rating' => 4,
                    'review' => 'Beautiful fabric and true to size.',
                    'is_approved' => true,
                ],
            );
        }

        $extraSkus = ['DEMO-LAPTOP-1', 'DEMO-HOME-1', 'DEMO-BEAUTY-1', 'DEMO-SPORT-3', 'DEMO-BOOK-1'];
        foreach ($extraSkus as $sku) {
            $product = Product::query()->where('sku', $sku)->first();
            if (! $product) {
                continue;
            }

            ProductReview::query()->firstOrCreate(
                ['product_id' => $product->id, 'user_id' => $buyer->id],
                [
                    'rating' => 5,
                    'review' => 'Excellent quality — would buy again.',
                    'is_approved' => true,
                ],
            );
        }
    }
}
