<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->string('group')->toString() ?: 'general';

        return [
            'group' => ['sometimes', 'string', Rule::in(['general', 'email', 'social_login', 'maintenance', 'product', 'product_listing', 'visual', 'homepage', 'slider', 'payment', 'font'])],
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ] + $this->rulesForGroup($group);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForGroup(string $group): array
    {
        return match ($group) {
            'email' => [
                'settings.mail_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
                'settings.mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.mail_reply_to' => ['sometimes', 'nullable', 'email', 'max:255'],
                'settings.mail_service' => ['sometimes', 'nullable', 'string', Rule::in(['smtp', 'mail', 'brevo', 'mailgun'])],
                'settings.mail_protocol' => ['sometimes', 'nullable', 'string', Rule::in(['smtp', 'mail'])],
                'settings.mail_encryption' => ['sometimes', 'nullable', 'string', Rule::in(['tls', 'ssl'])],
                'settings.smtp_host' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.smtp_port' => ['sometimes', 'nullable', 'string', 'max:10'],
                'settings.smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.smtp_password' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.brevo_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.mailgun_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.mailgun_region' => ['sometimes', 'nullable', 'string', Rule::in(['us', 'eu'])],
                'settings.mailgun_domain' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.mailgun_sender_email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'settings.email_verification' => ['sometimes', 'boolean'],
                'settings.email_option_new_product' => ['sometimes', 'boolean'],
                'settings.email_option_new_order' => ['sometimes', 'boolean'],
                'settings.email_option_order_shipped' => ['sometimes', 'boolean'],
                'settings.email_option_contact_messages' => ['sometimes', 'boolean'],
                'settings.email_option_shop_opening_request' => ['sometimes', 'boolean'],
                'settings.email_option_bidding_system' => ['sometimes', 'boolean'],
                'settings.email_option_support_system' => ['sometimes', 'boolean'],
                'settings.mail_options_account' => ['sometimes', 'nullable', 'email', 'max:255'],
            ],
            'social_login' => [
                'settings.facebook_app_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.facebook_app_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.google_client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.google_client_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.vk_app_id' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.vk_secure_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.social_login_enabled' => ['sometimes', 'boolean'],
            ],
            'maintenance' => [
                'settings.maintenance_mode' => ['sometimes', 'boolean'],
                'settings.maintenance_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ],
            'product' => [
                'settings.marketplace_enabled' => ['sometimes', 'boolean'],
                'settings.bidding_enabled' => ['sometimes', 'boolean'],
                'settings.product_reviews_enabled' => ['sometimes', 'boolean'],
                'settings.blog_comments_enabled' => ['sometimes', 'boolean'],
                'settings.image_file_format' => ['sometimes', 'nullable', 'string', Rule::in(['JPG', 'WEBP', 'PNG', 'original'])],
            ],
            'product_listing' => [
                'settings.marketplace_sku' => ['sometimes', 'boolean'],
                'settings.marketplace_variations' => ['sometimes', 'boolean'],
                'settings.marketplace_shipping' => ['sometimes', 'boolean'],
                'settings.marketplace_product_location' => ['sometimes', 'boolean'],
                'settings.classified_price' => ['sometimes', 'boolean'],
                'settings.classified_price_required' => ['sometimes', 'boolean'],
                'settings.classified_external_link' => ['sometimes', 'boolean'],
                'settings.classified_product_location' => ['sometimes', 'boolean'],
                'settings.physical_demo_url' => ['sometimes', 'boolean'],
                'settings.physical_video_preview' => ['sometimes', 'boolean'],
                'settings.physical_audio_preview' => ['sometimes', 'boolean'],
                'settings.digital_demo_url' => ['sometimes', 'boolean'],
                'settings.digital_video_preview' => ['sometimes', 'boolean'],
                'settings.digital_audio_preview' => ['sometimes', 'boolean'],
                'settings.digital_external_link' => ['sometimes', 'boolean'],
                'settings.digital_allowed_file_extensions' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.sort_by_featured_products' => ['sometimes', 'boolean'],
                'settings.pagination_per_page' => ['sometimes', 'integer', 'min:4', 'max:1000'],
                'settings.product_safety_tips' => ['sometimes', 'array', 'max:20'],
                'settings.product_safety_tips.*' => ['string', 'max:1000'],
            ],
            'visual' => [
                'settings.primary_color' => ['sometimes', 'nullable', 'string', 'max:20'],
                'settings.site_logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.site_logo_email_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.site_favicon_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.logo_width' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:300'],
                'settings.logo_height' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:300'],
                'settings.watermark_text' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.watermark_font_size' => ['sometimes', 'nullable', 'numeric', 'min:8', 'max:128'],
                'settings.watermark_product_enabled' => ['sometimes', 'boolean'],
                'settings.watermark_blog_enabled' => ['sometimes', 'boolean'],
                'settings.watermark_thumbnail_enabled' => ['sometimes', 'boolean'],
                'settings.watermark_horizontal_align' => ['sometimes', 'nullable', 'string', Rule::in(['left', 'center', 'right'])],
                'settings.watermark_vertical_align' => ['sometimes', 'nullable', 'string', Rule::in(['top', 'center', 'middle', 'bottom'])],
            ],
            'homepage' => [
                'settings.featured_categories' => ['sometimes', 'boolean'],
                'settings.index_latest_products' => ['sometimes', 'boolean'],
                'settings.index_latest_products_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:48'],
                'settings.index_promoted_products' => ['sometimes', 'boolean'],
                'settings.index_promoted_products_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:48'],
                'settings.index_trending_products_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:48'],
                'settings.index_products_per_row' => ['sometimes', 'nullable', 'integer', Rule::in([5])],
                'settings.product_grid_layout' => ['sometimes', 'nullable', 'string', Rule::in(['rows', 'masonry'])],
                'settings.index_recommended_products_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
                'settings.homepage_site_banner_mid_image' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.homepage_site_banner_mid_alt' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.promoted_products' => ['sometimes', 'boolean'],
                'settings.index_blog_slider' => ['sometimes', 'boolean'],
            ],
            'slider' => [
                'settings.slider_status' => ['sometimes', 'boolean'],
                'settings.slider_type' => ['sometimes', 'nullable', 'string', Rule::in(['full_width', 'boxed'])],
                'settings.slider_effect' => ['sometimes', 'nullable', 'string', Rule::in(['fade', 'slide'])],
            ],
            'payment' => [
                'settings.default_currency' => ['sometimes', 'nullable', 'string', 'max:10'],
                'settings.currency_converter' => ['sometimes', 'boolean'],
                'settings.auto_update_exchange_rates' => ['sometimes', 'boolean'],
                'settings.currency_converter_api' => ['sometimes', 'nullable', 'string', Rule::in(['fixer', 'currencyapi', 'openexchangerates'])],
                'settings.currency_converter_api_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.allow_all_currencies_for_classified' => ['sometimes', 'boolean'],
                'settings.commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'settings.vat_status' => ['sometimes', 'boolean'],
                'settings.cart_location_selection' => ['sometimes', 'boolean'],
                'settings.cash_on_delivery_debt_limit' => ['sometimes', 'numeric', 'min:0'],
            ],
            'font' => [
                'settings.site_font_family' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.dashboard_font_family' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.custom_fonts' => ['sometimes', 'nullable', 'array'],
                'settings.custom_fonts.*.id' => ['sometimes', 'integer'],
                'settings.custom_fonts.*.name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.custom_fonts.*.url' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'settings.custom_fonts.*.family' => ['sometimes', 'nullable', 'string', 'max:255'],
            ],
            default => [
                'settings.site_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.application_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.site_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.homepage_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.keywords' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'settings.support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'settings.site_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'settings.site_announcement' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'settings.about_footer' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'settings.copyright' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.contact_address' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.contact_email' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.contact_phone' => ['sometimes', 'nullable', 'string', 'max:100'],
                'settings.contact_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'settings.facebook_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.twitter_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.instagram_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.tiktok_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.whatsapp_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.youtube_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.linkedin_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.pinterest_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.telegram_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.discord_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.twitch_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.vk_url' => ['sometimes', 'nullable', 'string', 'max:500'],
                'settings.facebook_comment_status' => ['sometimes', 'boolean'],
                'settings.facebook_comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'settings.custom_header_codes' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'settings.custom_footer_codes' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'settings.gtm_enabled' => ['sometimes', 'boolean'],
                'settings.gtm_container_id' => ['sometimes', 'nullable', 'string', 'max:32'],
                'settings.cookies_warning' => ['sometimes', 'boolean'],
                'settings.cookies_warning_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'settings.bulk_upload_documentation' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'settings.turnstile_status' => ['sometimes', 'boolean'],
                'settings.turnstile_site_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.turnstile_secret_key' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.maintenance_mode_title' => ['sometimes', 'nullable', 'string', 'max:255'],
                'settings.maintenance_mode_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'settings.maintenance_mode_status' => ['sometimes', 'boolean'],
                'settings.multi_vendor_system' => ['sometimes', 'boolean'],
                'settings.ai_writer_status' => ['sometimes', 'boolean'],
                'settings.single_country_mode' => ['sometimes', 'boolean'],
                'settings.single_country_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            ],
        };
    }
}
