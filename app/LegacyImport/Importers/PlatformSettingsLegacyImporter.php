<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Support\BrandColor;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use App\Modules\Selloff\Notification\Services\NewsletterSettingsService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\DB;

class PlatformSettingsLegacyImporter implements LegacyImporter
{
    /** @var list<string> */
    private const GENERAL_SETTING_BOOLEAN_KEYS = [
        'featured_categories',
        'index_promoted_products',
        'index_latest_products',
        'promoted_products',
    ];

    /** @var list<string> */
    private const GENERAL_SETTING_KEYS = [
        'site_title',
        'homepage_title',
        'site_description',
        'keywords',
        'contact_email',
        'contact_phone',
        'contact_address',
        'copyright',
        'single_country_mode',
        'single_country_id',
        'featured_categories',
        'index_promoted_products',
        'index_latest_products',
        'index_latest_products_count',
        'index_products_per_row',
        'promoted_products',
        'fea_categories_design',
    ];

    /** @var array<string, string> */
    private const PREFERENCE_BOOL_MAPPINGS = [
        'physical_products_system' => 'physical_products_enabled',
        'digital_products_system' => 'digital_products_enabled',
        'marketplace_system' => 'marketplace_enabled',
        'classified_ads_system' => 'classified_ads_enabled',
        'bidding_system' => 'bidding_enabled',
        'selling_license_keys_system' => 'license_keys_enabled',
        'multi_vendor_system' => 'multi_vendor_system',
        'multilingual_system' => 'multilingual_system',
        'rss_system' => 'rss_enabled',
        'vendor_verification_system' => 'vendor_verification_system',
        'show_vendor_contact_information' => 'show_vendor_contact_information',
        'show_vendor_contact_info_guests' => 'show_vendor_contact_info_guests',
        'guest_checkout' => 'guest_checkout_enabled',
        'location_search_header' => 'location_search_header',
        'pwa_status' => 'pwa_enabled',
        'approve_before_publishing' => 'approve_before_publishing',
        'vendor_bulk_product_upload' => 'vendor_bulk_product_upload',
        'show_sold_products' => 'show_sold_products',
        'reviews' => 'product_reviews_enabled',
        'product_comments' => 'product_comments_enabled',
        'blog_comments' => 'blog_comments_enabled',
        'comment_approval_system' => 'comment_approval_system',
        'refund_system' => 'refund_system',
        'profile_number_of_sales' => 'profile_number_of_sales',
        'vendors_change_shop_name' => 'vendors_change_shop_name',
        'show_customer_email_seller' => 'show_customer_email_seller',
        'show_customer_phone_seller' => 'show_customer_phone_seller',
        'auto_approve_orders' => 'auto_approve_orders',
        'request_documents_vendors' => 'request_documents_vendors',
    ];

    /** @var list<string> */
    private const STORAGE_SETTING_KEYS = [
        'storage',
        'aws_key',
        'aws_secret',
        'aws_bucket',
        'aws_region',
        'r2_key',
        'r2_secret',
        'r2_bucket',
        'r2_endpoint_url',
        'r2_public_url',
        'b2_key',
        'b2_secret',
        'b2_bucket',
        'b2_endpoint_url',
        'b2_public_url',
    ];

    public function legacyTable(): string
    {
        return 'general_settings';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('general_settings')) {
            return;
        }

        $rows = $reader->rows('general_settings');
        $row = $rows[0] ?? null;
        if ($row === null) {
            return;
        }

        $context->notePlanned($this->legacyTable());

        foreach (self::GENERAL_SETTING_KEYS as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $settingKey = $key === 'site_title' ? 'site_name' : $key;

            if (in_array($key, self::GENERAL_SETTING_BOOLEAN_KEYS, true)) {
                $value = LegacyValueCoercer::bool($value);
            }

            $this->upsertPlatformSetting($context, $settingKey, $value, 'general');
        }

        $this->importPreferenceSettings($context, $row);
        $this->importStorageSettings($context, $row);
        $this->importAiWriterSettings($context, $row);
        $this->importPwaLogoSettings($context, $row);
        $this->importWatermarkSettings($context, $row);
        $this->importGeneralSettingsExtras($context, $row);
        $this->importEmailSettings($context, $row);
        $this->importSocialLoginSettings($context, $row);
        $this->importVisualBranding($context, $row);
        $this->importLocalizedSettings($context, $reader, $row);
        $this->importProductMediaSettings($context, $reader);
        $this->importProductListingSettings($context, $reader);
        $this->importFontSettings($context, $reader);
        $this->importWalletSettings($context, $reader);
        $this->importAffiliateProgram($context, $reader, $row);
        $this->importNewsletterSettings($context, $row);

        if (! $context->dryRun) {
            app(PlatformSettingsService::class)->flushCache();
        }

        $context->noteImported($this->legacyTable());
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importPreferenceSettings(LegacyImportContext $context, array $row): void
    {
        foreach (self::PREFERENCE_BOOL_MAPPINGS as $legacyKey => $platformKey) {
            if (! array_key_exists($legacyKey, $row) || $row[$legacyKey] === null) {
                continue;
            }

            $this->upsertPlatformSetting(
                $context,
                $platformKey,
                LegacyValueCoercer::bool($row[$legacyKey]),
                'preferences',
            );
        }

        if (array_key_exists('timezone', $row) && $row['timezone'] !== null && $row['timezone'] !== '') {
            $this->upsertPlatformSetting($context, 'timezone', (string) $row['timezone'], 'preferences');
        }

        if (array_key_exists('approve_after_editing', $row) && $row['approve_after_editing'] !== null && $row['approve_after_editing'] !== '') {
            $this->upsertPlatformSetting($context, 'approve_after_editing', (int) $row['approve_after_editing'], 'preferences');
        }

        if (array_key_exists('promoted_products', $row) && $row['promoted_products'] !== null) {
            $this->upsertPlatformSetting(
                $context,
                'promoted_products',
                LegacyValueCoercer::bool($row['promoted_products']),
                'preferences',
            );
        }

        if (array_key_exists('product_link_structure', $row) && $row['product_link_structure'] !== null && $row['product_link_structure'] !== '') {
            $this->upsertPlatformSetting($context, 'product_link_structure', (string) $row['product_link_structure'], 'preferences');
        }

        if (array_key_exists('auto_approve_orders_days', $row) && $row['auto_approve_orders_days'] !== null && $row['auto_approve_orders_days'] !== '') {
            $this->upsertPlatformSetting($context, 'auto_approve_orders_days', (int) $row['auto_approve_orders_days'], 'preferences');
        }

        if (array_key_exists('explanation_documents_vendors', $row) && $row['explanation_documents_vendors'] !== null && $row['explanation_documents_vendors'] !== '') {
            $this->upsertPlatformSetting($context, 'explanation_documents_vendors', (string) $row['explanation_documents_vendors'], 'preferences');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importStorageSettings(LegacyImportContext $context, array $row): void
    {
        if (empty($row['storage_settings'])) {
            return;
        }

        $bag = LegacyValueCoercer::phpSerializedArray($row['storage_settings']);
        if ($bag === null) {
            return;
        }

        foreach (self::STORAGE_SETTING_KEYS as $key) {
            if (! array_key_exists($key, $bag)) {
                continue;
            }

            $value = $bag[$key];
            if ($value === null || $value === '') {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, (string) $value, 'preferences');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importAiWriterSettings(LegacyImportContext $context, array $row): void
    {
        if (empty($row['ai_writer'])) {
            return;
        }

        $bag = LegacyValueCoercer::phpSerializedArray($row['ai_writer']);
        if ($bag === null) {
            return;
        }

        if (array_key_exists('status', $bag) && $bag['status'] !== null && $bag['status'] !== '') {
            $this->upsertPlatformSetting($context, 'ai_writer_status', LegacyValueCoercer::bool($bag['status']), 'preferences');
        }

        if (array_key_exists('api_key', $bag) && $bag['api_key'] !== null && $bag['api_key'] !== '') {
            $this->upsertPlatformSetting($context, 'ai_writer_api_key', (string) $bag['api_key'], 'preferences');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importPwaLogoSettings(LegacyImportContext $context, array $row): void
    {
        if (empty($row['pwa_logo'])) {
            return;
        }

        $bag = LegacyValueCoercer::phpSerializedArray($row['pwa_logo']);
        if ($bag === null) {
            return;
        }

        if (! empty($bag['md'])) {
            $this->upsertPlatformSetting($context, 'pwa_logo_md_url', (string) $bag['md'], 'preferences');
        }

        $this->upsertPlatformSetting($context, 'pwa_logo_paths', $bag, 'preferences');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importWatermarkSettings(LegacyImportContext $context, array $row): void
    {
        if (empty($row['watermark_settings'])) {
            return;
        }

        $bag = LegacyValueCoercer::phpSerializedArray($row['watermark_settings']);
        if ($bag === null) {
            return;
        }

        $settings = [
            'watermark_text' => (string) ($bag['w_text'] ?? ''),
            'watermark_font_size' => (float) ($bag['w_font_size'] ?? 28),
            'watermark_product_enabled' => LegacyValueCoercer::bool($bag['w_product_images'] ?? 0),
            'watermark_blog_enabled' => LegacyValueCoercer::bool($bag['w_blog_images'] ?? 0),
            'watermark_thumbnail_enabled' => LegacyValueCoercer::bool($bag['w_thumbnail_images'] ?? 0),
            'watermark_horizontal_align' => (string) ($bag['w_hor_alignment'] ?? 'right'),
            'watermark_vertical_align' => (string) ($bag['w_vrt_alignment'] ?? 'bottom'),
        ];

        foreach ($settings as $key => $value) {
            $this->upsertPlatformSetting($context, $key, $value, 'visual');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importGeneralSettingsExtras(LegacyImportContext $context, array $row): void
    {
        $booleanKeys = [
            'facebook_comment_status',
            'turnstile_status',
            'maintenance_mode_status',
            'email_verification',
        ];

        foreach ($booleanKeys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, LegacyValueCoercer::bool($row[$key]), 'general');
        }

        foreach ([
            'application_name',
            'facebook_comment',
            'custom_header_codes',
            'custom_footer_codes',
            'turnstile_site_key',
            'turnstile_secret_key',
            'maintenance_mode_title',
            'maintenance_mode_description',
            'mail_options_account',
        ] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, (string) $row[$key], 'general');
        }

        if (array_key_exists('google_analytics', $row) && $row['google_analytics'] !== null && $row['google_analytics'] !== '') {
            $this->upsertPlatformSetting($context, 'google_analytics', (string) $row['google_analytics'], 'seo');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importEmailSettings(LegacyImportContext $context, array $row): void
    {
        if (! empty($row['email_settings'])) {
            $bag = LegacyValueCoercer::phpSerializedArray($row['email_settings']);
            if ($bag !== null) {
                $map = [
                    'mail_service' => 'mail_service',
                    'mail_protocol' => 'mail_protocol',
                    'mail_encryption' => 'mail_encryption',
                    'mail_host' => 'smtp_host',
                    'mail_port' => 'smtp_port',
                    'mail_username' => 'smtp_username',
                    'mail_password' => 'smtp_password',
                    'mail_title' => 'mail_from_name',
                    'mail_reply_to' => 'mail_reply_to',
                    'brevo_api_key' => 'brevo_api_key',
                    'mailgun_api_key' => 'mailgun_api_key',
                    'mailgun_region' => 'mailgun_region',
                    'mailgun_domain' => 'mailgun_domain',
                    'mailgun_sender_email' => 'mailgun_sender_email',
                ];

                foreach ($map as $legacyKey => $platformKey) {
                    if (! array_key_exists($legacyKey, $bag) || $bag[$legacyKey] === null || $bag[$legacyKey] === '') {
                        continue;
                    }

                    $this->upsertPlatformSetting($context, $platformKey, (string) $bag[$legacyKey], 'email');
                }
            }
        }

        if (! empty($row['email_options'])) {
            $options = LegacyValueCoercer::phpSerializedArray($row['email_options']);
            if ($options !== null) {
                $optionMap = [
                    'new_product' => 'email_option_new_product',
                    'new_order' => 'email_option_new_order',
                    'order_shipped' => 'email_option_order_shipped',
                    'contact_messages' => 'email_option_contact_messages',
                    'shop_opening_request' => 'email_option_shop_opening_request',
                    'bidding_system' => 'email_option_bidding_system',
                    'support_system' => 'email_option_support_system',
                ];

                foreach ($optionMap as $legacyKey => $platformKey) {
                    if (! array_key_exists($legacyKey, $options) || $options[$legacyKey] === null) {
                        continue;
                    }

                    $this->upsertPlatformSetting($context, $platformKey, LegacyValueCoercer::bool($options[$legacyKey]), 'email');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importSocialLoginSettings(LegacyImportContext $context, array $row): void
    {
        foreach ([
            'facebook_app_id',
            'facebook_app_secret',
            'google_client_id',
            'google_client_secret',
            'vk_app_id',
            'vk_secure_key',
        ] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, (string) $row[$key], 'social_login');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importVisualBranding(LegacyImportContext $context, array $row): void
    {
        if (array_key_exists('site_color', $row) && $row['site_color'] !== null && $row['site_color'] !== '') {
            $this->upsertPlatformSetting($context, 'primary_color', BrandColor::normalize((string) $row['site_color']), 'visual');
        }

        foreach ([
            'logo' => 'site_logo_url',
            'logo_email' => 'site_logo_email_url',
            'favicon' => 'site_favicon_url',
        ] as $legacyKey => $platformKey) {
            if (! array_key_exists($legacyKey, $row) || $row[$legacyKey] === null || $row[$legacyKey] === '') {
                continue;
            }

            $this->upsertPlatformSetting($context, $platformKey, (string) $row[$legacyKey], 'visual');
        }

        if (! empty($row['logo_size']) && is_string($row['logo_size']) && str_contains($row['logo_size'], 'x')) {
            [$width, $height] = array_pad(explode('x', $row['logo_size'], 2), 2, null);
            if ($width !== null && $width !== '') {
                $this->upsertPlatformSetting($context, 'logo_width', (int) $width, 'visual');
            }
            if ($height !== null && $height !== '') {
                $this->upsertPlatformSetting($context, 'logo_height', (int) $height, 'visual');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $generalRow
     */
    private function importLocalizedSettings(LegacyImportContext $context, MySqlDumpReader $reader, array $generalRow): void
    {
        if (! $reader->hasTable('settings')) {
            return;
        }

        $siteLang = (int) ($generalRow['site_lang'] ?? 1);
        $settingsRow = null;

        foreach ($reader->rows('settings') as $row) {
            if ((int) ($row['lang_id'] ?? 0) === $siteLang) {
                $settingsRow = $row;

                break;
            }
        }

        $settingsRow ??= $reader->rows('settings')[0] ?? null;
        if ($settingsRow === null) {
            return;
        }

        foreach ([
            'site_title',
            'homepage_title',
            'site_description',
            'keywords',
            'about_footer',
            'contact_text',
            'contact_address',
            'contact_email',
            'contact_phone',
            'copyright',
            'cookies_warning_text',
            'bulk_upload_documentation',
        ] as $key) {
            if (! array_key_exists($key, $settingsRow) || $settingsRow[$key] === null || $settingsRow[$key] === '') {
                continue;
            }

            $platformKey = $key === 'site_title' ? 'site_name' : $key;
            $this->upsertPlatformSetting($context, $platformKey, (string) $settingsRow[$key], 'general');
        }

        if (array_key_exists('cookies_warning', $settingsRow) && $settingsRow['cookies_warning'] !== null) {
            $this->upsertPlatformSetting($context, 'cookies_warning', LegacyValueCoercer::bool($settingsRow['cookies_warning']), 'general');
        }

        if (! empty($settingsRow['social_media_data'])) {
            $social = LegacyValueCoercer::phpSerializedArray($settingsRow['social_media_data']);
            if ($social !== null) {
                foreach ($social as $key => $value) {
                    if (! is_string($key) || ! str_ends_with($key, '_url') || $value === null || $value === '') {
                        continue;
                    }

                    $this->upsertPlatformSetting($context, $key, (string) $value, 'general');
                }
            }
        }
    }

    private function importProductListingSettings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('product_settings')) {
            return;
        }

        $row = $reader->rows('product_settings')[0] ?? null;
        if ($row === null) {
            return;
        }

        $booleanKeys = [
            'marketplace_sku',
            'marketplace_variations',
            'marketplace_shipping',
            'marketplace_product_location',
            'classified_price',
            'classified_price_required',
            'classified_external_link',
            'classified_product_location',
            'physical_demo_url',
            'physical_video_preview',
            'physical_audio_preview',
            'digital_demo_url',
            'digital_video_preview',
            'digital_audio_preview',
            'digital_external_link',
            'sort_by_featured_products',
        ];

        foreach ($booleanKeys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, LegacyValueCoercer::bool($row[$key]), 'product_listing');
        }

        if (array_key_exists('digital_allowed_file_extensions', $row) && $row['digital_allowed_file_extensions'] !== null && $row['digital_allowed_file_extensions'] !== '') {
            $this->upsertPlatformSetting($context, 'digital_allowed_file_extensions', (string) $row['digital_allowed_file_extensions'], 'product_listing');
        }

        if (array_key_exists('pagination_per_page', $row) && $row['pagination_per_page'] !== null && $row['pagination_per_page'] !== '') {
            $this->upsertPlatformSetting($context, 'pagination_per_page', (int) $row['pagination_per_page'], 'product_listing');
        }

        foreach (['sitemap_frequency', 'sitemap_last_modification', 'sitemap_priority'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            $this->upsertPlatformSetting(
                $context,
                $key,
                $this->normalizeLegacySitemapPref((string) $row[$key]),
                'product',
            );
        }
    }

    private function normalizeLegacySitemapPref(string $value): string
    {
        return $value === 'auto' ? 'auto' : 'none';
    }

    private function importFontSettings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $fontMap = [];

        if ($reader->hasTable('fonts')) {
            $customFonts = [];

            foreach ($reader->rows('fonts') as $fontRow) {
                $id = (int) ($fontRow['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $family = (string) ($fontRow['font_family'] ?? $fontRow['font_name'] ?? '');
                $customFonts[] = [
                    'id' => $id,
                    'name' => (string) ($fontRow['font_name'] ?? $family),
                    'url' => (string) ($fontRow['font_url'] ?? ''),
                    'family' => $family,
                ];
                $fontMap[$id] = $family;
            }

            if ($customFonts !== []) {
                $this->upsertPlatformSetting($context, 'custom_fonts', $customFonts, 'font');
            }
        }

        if (! $reader->hasTable('settings')) {
            return;
        }

        $settingsRow = $reader->rows('settings')[0] ?? null;
        if ($settingsRow === null) {
            return;
        }

        $siteFontId = (int) ($settingsRow['site_font'] ?? 0);
        $dashboardFontId = (int) ($settingsRow['dashboard_font'] ?? 0);

        if ($siteFontId > 0 && isset($fontMap[$siteFontId])) {
            $this->upsertPlatformSetting($context, 'site_font_family', $fontMap[$siteFontId], 'font');
        }

        if ($dashboardFontId > 0 && isset($fontMap[$dashboardFontId])) {
            $this->upsertPlatformSetting($context, 'dashboard_font_family', $fontMap[$dashboardFontId], 'font');
        }
    }

    private function importProductMediaSettings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('product_settings')) {
            return;
        }

        $row = $reader->rows('product_settings')[0] ?? null;
        if ($row === null) {
            return;
        }

        if (array_key_exists('image_file_format', $row) && $row['image_file_format'] !== null && $row['image_file_format'] !== '') {
            $format = (string) $row['image_file_format'];
            if (strtolower($format) !== 'original') {
                $format = strtoupper($format);
            }

            $this->upsertPlatformSetting($context, 'image_file_format', $format, 'preferences');
        }

        foreach (['brand_status', 'is_brand_optional'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, LegacyValueCoercer::bool($row[$key]), 'product');
        }

        if (array_key_exists('brand_where_to_display', $row) && $row['brand_where_to_display'] !== null && $row['brand_where_to_display'] !== '') {
            $this->upsertPlatformSetting($context, 'brand_where_to_display', (int) $row['brand_where_to_display'], 'product');
        }

        if (array_key_exists('is_product_image_required', $row) && $row['is_product_image_required'] !== null) {
            $this->upsertPlatformSetting(
                $context,
                'is_product_image_required',
                LegacyValueCoercer::bool($row['is_product_image_required']),
                'preferences',
            );
        }

        if (array_key_exists('product_image_limit', $row) && $row['product_image_limit'] !== null && $row['product_image_limit'] !== '') {
            $this->upsertPlatformSetting($context, 'product_image_limit', (int) $row['product_image_limit'], 'preferences');
        }

        foreach (['max_file_size_image', 'max_file_size_video', 'max_file_size_audio'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            $megabytes = round(((float) $row[$key]) / 1048576, 2);
            $this->upsertPlatformSetting($context, $key, $megabytes, 'preferences');
        }
    }

    private function importWalletSettings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('payment_settings')) {
            return;
        }

        $row = $reader->rows('payment_settings')[0] ?? null;
        if ($row === null) {
            return;
        }

        foreach (['wallet_status', 'wallet_deposit', 'pay_with_wallet_balance'] as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }

            $this->upsertPlatformSetting($context, $key, LegacyValueCoercer::bool($row[$key]), 'preferences');
        }

        if (array_key_exists('wallet_min_deposit', $row) && $row['wallet_min_deposit'] !== null && $row['wallet_min_deposit'] !== '') {
            $this->upsertPlatformSetting($context, 'wallet_min_deposit', (float) $row['wallet_min_deposit'], 'preferences');
        }
    }

    /**
     * @param  array<string, mixed>  $generalRow
     */
    private function importAffiliateProgram(LegacyImportContext $context, MySqlDumpReader $reader, array $generalRow): void
    {
        if ($context->dryRun) {
            return;
        }

        $affiliateSettings = LegacyValueCoercer::phpSerializedArray($generalRow['affiliate_settings'] ?? null) ?? [];
        if ($affiliateSettings === []) {
            return;
        }

        $affiliateSettings['status'] = LegacyValueCoercer::bool($affiliateSettings['status'] ?? false);

        $localizedRows = [];
        if ($reader->hasTable('settings')) {
            foreach ($reader->rows('settings') as $settingsRow) {
                $langId = (int) ($settingsRow['lang_id'] ?? 0);
                if ($langId <= 0) {
                    continue;
                }

                $localizedRows[$langId] = [
                    'description' => LegacyValueCoercer::phpSerializedArray($settingsRow['affiliate_description'] ?? null) ?? [],
                    'content' => LegacyValueCoercer::phpSerializedArray($settingsRow['affiliate_content'] ?? null) ?? [],
                    'how_it_works' => LegacyValueCoercer::phpSerializedArray($settingsRow['affiliate_works'] ?? null) ?? [],
                    'faq' => LegacyValueCoercer::phpSerializedArray($settingsRow['affiliate_faq'] ?? null) ?? [],
                ];
            }
        }

        app(AffiliateProgramSettingsService::class)->importFromLegacy($affiliateSettings, $localizedRows);
        app(PlatformSettingsService::class)->flushCache();
    }

    /**
     * @param  array<string, mixed>  $generalRow
     */
    private function importNewsletterSettings(LegacyImportContext $context, array $generalRow): void
    {
        if ($context->dryRun) {
            return;
        }

        $newsletterSettings = LegacyValueCoercer::phpSerializedArray($generalRow['newsletter_settings'] ?? null) ?? [];
        if ($newsletterSettings === []) {
            return;
        }

        app(NewsletterSettingsService::class)->importFromLegacy($newsletterSettings);
    }

    private function upsertPlatformSetting(LegacyImportContext $context, string $key, mixed $value, string $group): void
    {
        if ($context->dryRun) {
            return;
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'group' => $group,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}

