<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\PlatformSettingsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsLegacyImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_preference_fields_from_legacy_tables(): void
    {
        $dumpPath = storage_path('app/test-preferences-import.sql');
        $storage = addslashes(serialize([
            'storage' => 'aws_s3',
            'aws_key' => 'AKIA_TEST',
            'aws_secret' => 'secret',
            'aws_bucket' => 'selloff-bucket',
            'aws_region' => 'us-east-1',
        ]));
        $aiWriter = addslashes(serialize([
            'status' => '1',
            'api_key' => 'sk-test-key',
        ]));
        $pwaLogo = addslashes(serialize([
            'md' => 'uploads/pwa/logo-md.png',
            'sm' => 'uploads/pwa/logo-sm.png',
        ]));

        file_put_contents($dumpPath, <<<SQL
CREATE TABLE `general_settings` (
  `id` int NOT NULL,
  `physical_products_system` tinyint(1) DEFAULT '1',
  `digital_products_system` tinyint(1) DEFAULT '1',
  `marketplace_system` tinyint(1) DEFAULT '1',
  `classified_ads_system` tinyint(1) DEFAULT '0',
  `bidding_system` tinyint(1) DEFAULT '1',
  `selling_license_keys_system` tinyint(1) DEFAULT '0',
  `multi_vendor_system` tinyint(1) DEFAULT '1',
  `timezone` varchar(100) DEFAULT NULL,
  `multilingual_system` tinyint(1) DEFAULT '1',
  `rss_system` tinyint(1) DEFAULT '1',
  `vendor_verification_system` tinyint(1) DEFAULT '1',
  `show_vendor_contact_information` tinyint(1) DEFAULT '1',
  `show_vendor_contact_info_guests` tinyint(1) DEFAULT '0',
  `guest_checkout` tinyint(1) DEFAULT '0',
  `location_search_header` tinyint(1) DEFAULT '1',
  `pwa_status` tinyint(1) DEFAULT '0',
  `approve_before_publishing` tinyint(1) DEFAULT '1',
  `approve_after_editing` tinyint DEFAULT '2',
  `promoted_products` tinyint(1) DEFAULT '1',
  `vendor_bulk_product_upload` tinyint(1) DEFAULT '1',
  `show_sold_products` tinyint(1) DEFAULT '1',
  `product_link_structure` varchar(20) DEFAULT NULL,
  `reviews` tinyint(1) DEFAULT '1',
  `product_comments` tinyint(1) DEFAULT '1',
  `blog_comments` tinyint(1) DEFAULT '0',
  `comment_approval_system` tinyint(1) DEFAULT '1',
  `refund_system` tinyint(1) DEFAULT '1',
  `profile_number_of_sales` tinyint(1) DEFAULT '1',
  `vendors_change_shop_name` tinyint(1) DEFAULT '0',
  `show_customer_email_seller` tinyint(1) DEFAULT '1',
  `show_customer_phone_seller` tinyint(1) DEFAULT '0',
  `auto_approve_orders` tinyint(1) DEFAULT '1',
  `auto_approve_orders_days` smallint DEFAULT '15',
  `request_documents_vendors` tinyint(1) DEFAULT '1',
  `explanation_documents_vendors` varchar(500) DEFAULT NULL,
  `storage_settings` text,
  `ai_writer` text,
  `pwa_logo` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `general_settings` (`id`, `physical_products_system`, `digital_products_system`, `marketplace_system`, `classified_ads_system`, `bidding_system`, `selling_license_keys_system`, `multi_vendor_system`, `timezone`, `multilingual_system`, `rss_system`, `vendor_verification_system`, `show_vendor_contact_information`, `show_vendor_contact_info_guests`, `guest_checkout`, `location_search_header`, `pwa_status`, `approve_before_publishing`, `approve_after_editing`, `promoted_products`, `vendor_bulk_product_upload`, `show_sold_products`, `product_link_structure`, `reviews`, `product_comments`, `blog_comments`, `comment_approval_system`, `refund_system`, `profile_number_of_sales`, `vendors_change_shop_name`, `show_customer_email_seller`, `show_customer_phone_seller`, `auto_approve_orders`, `auto_approve_orders_days`, `request_documents_vendors`, `explanation_documents_vendors`, `storage_settings`, `ai_writer`, `pwa_logo`)
VALUES (1, 1, 1, 1, 0, 1, 0, 1, 'Africa/Lagos', 1, 1, 1, 1, 0, 0, 1, 0, 1, 2, 1, 1, 1, 'id-slug', 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 15, 1, 'Upload your ID', '{$storage}', '{$aiWriter}', '{$pwaLogo}');

CREATE TABLE `product_settings` (
  `id` int NOT NULL,
  `image_file_format` varchar(20) DEFAULT NULL,
  `is_product_image_required` tinyint(1) DEFAULT '1',
  `product_image_limit` smallint DEFAULT '20',
  `max_file_size_image` bigint DEFAULT '10485760',
  `max_file_size_video` bigint DEFAULT '31457280',
  `max_file_size_audio` bigint DEFAULT '10485760'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_settings` (`id`, `image_file_format`, `is_product_image_required`, `product_image_limit`, `max_file_size_image`, `max_file_size_video`, `max_file_size_audio`)
VALUES (1, 'webp', 1, 12, 10485760, 52428800, 20971520);

CREATE TABLE `payment_settings` (
  `id` int NOT NULL,
  `wallet_status` tinyint(1) DEFAULT '1',
  `wallet_deposit` tinyint(1) DEFAULT '1',
  `pay_with_wallet_balance` tinyint(1) DEFAULT '0',
  `wallet_min_deposit` decimal(13,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_settings` (`id`, `wallet_status`, `wallet_deposit`, `pay_with_wallet_balance`, `wallet_min_deposit`)
VALUES (1, 1, 0, 1, 5000.00);
SQL);

        $context = new LegacyImportContext(dryRun: false);

        app(PlatformSettingsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertTrue(PlatformSetting::query()->find('physical_products_enabled')?->value);
        $this->assertFalse(PlatformSetting::query()->find('classified_ads_enabled')?->value);
        $this->assertSame('Africa/Lagos', PlatformSetting::query()->find('timezone')?->value);
        $this->assertFalse(PlatformSetting::query()->find('guest_checkout_enabled')?->value);
        $this->assertSame(2, PlatformSetting::query()->find('approve_after_editing')?->value);
        $this->assertSame('id-slug', PlatformSetting::query()->find('product_link_structure')?->value);
        $this->assertFalse(PlatformSetting::query()->find('blog_comments_enabled')?->value);
        $this->assertSame('Upload your ID', PlatformSetting::query()->find('explanation_documents_vendors')?->value);
        $this->assertSame('aws_s3', PlatformSetting::query()->find('storage')?->value);
        $this->assertSame('AKIA_TEST', PlatformSetting::query()->find('aws_key')?->value);
        $this->assertTrue(PlatformSetting::query()->find('ai_writer_status')?->value);
        $this->assertSame('sk-test-key', PlatformSetting::query()->find('ai_writer_api_key')?->value);
        $this->assertSame('uploads/pwa/logo-md.png', PlatformSetting::query()->find('pwa_logo_md_url')?->value);
        $this->assertSame('WEBP', PlatformSetting::query()->find('image_file_format')?->value);
        $this->assertSame(12, PlatformSetting::query()->find('product_image_limit')?->value);
        $this->assertEquals(10, PlatformSetting::query()->find('max_file_size_image')?->value);
        $this->assertEquals(50, PlatformSetting::query()->find('max_file_size_video')?->value);
        $this->assertEquals(20, PlatformSetting::query()->find('max_file_size_audio')?->value);
        $this->assertTrue(PlatformSetting::query()->find('wallet_status')?->value);
        $this->assertFalse(PlatformSetting::query()->find('wallet_deposit')?->value);
        $this->assertTrue(PlatformSetting::query()->find('pay_with_wallet_balance')?->value);
        $this->assertEquals(5000, PlatformSetting::query()->find('wallet_min_deposit')?->value);

        @unlink($dumpPath);
    }

    public function test_imports_watermark_and_image_format_into_platform_settings(): void
    {
        $dumpPath = storage_path('app/test-platform-settings-import.sql');
        $watermark = serialize([
            'w_text' => 'Imported Watermark',
            'w_font_size' => '30',
            'w_product_images' => '1',
            'w_blog_images' => '0',
            'w_thumbnail_images' => '1',
            'w_vrt_alignment' => 'bottom',
            'w_hor_alignment' => 'right',
        ]);

        file_put_contents($dumpPath, <<<SQL
CREATE TABLE `general_settings` (
  `id` int NOT NULL,
  `site_title` varchar(255) DEFAULT NULL,
  `watermark_settings` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `general_settings` (`id`, `site_title`, `watermark_settings`)
VALUES
(1, 'Selloff Import', '{$watermark}');

CREATE TABLE `product_settings` (
  `id` int NOT NULL,
  `image_file_format` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_settings` (`id`, `image_file_format`)
VALUES
(1, 'WEBP');
SQL);

        $context = new LegacyImportContext(dryRun: false);

        app(PlatformSettingsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertSame('Selloff Import', PlatformSetting::query()->find('site_name')?->value);
        $this->assertSame('Imported Watermark', PlatformSetting::query()->find('watermark_text')?->value);
        $this->assertTrue(PlatformSetting::query()->find('watermark_product_enabled')?->value);
        $this->assertFalse(PlatformSetting::query()->find('watermark_blog_enabled')?->value);
        $this->assertTrue(PlatformSetting::query()->find('watermark_thumbnail_enabled')?->value);
        $this->assertSame('WEBP', PlatformSetting::query()->find('image_file_format')?->value);

        @unlink($dumpPath);
    }

    public function test_imports_admin_settings_page_fields_from_legacy_tables(): void
    {
        $dumpPath = storage_path('app/test-admin-settings-import.sql');
        $emailSettings = addslashes(serialize([
            'mail_service' => 'smtp',
            'mail_host' => 'smtp.mail.test',
            'mail_port' => '587',
            'mail_title' => 'Selloff Mail',
        ]));
        $emailOptions = addslashes(serialize([
            'new_order' => '1',
            'contact_messages' => '0',
        ]));
        $social = addslashes(serialize(['facebook_url' => 'https://facebook.com/selloff']));

        file_put_contents($dumpPath, <<<SQL
CREATE TABLE `general_settings` (
  `id` int NOT NULL,
  `site_lang` int DEFAULT '1',
  `application_name` varchar(255) DEFAULT NULL,
  `site_color` varchar(20) DEFAULT NULL,
  `facebook_app_id` varchar(255) DEFAULT NULL,
  `email_verification` tinyint(1) DEFAULT '1',
  `email_settings` text,
  `email_options` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `general_settings` (`id`, `site_lang`, `application_name`, `site_color`, `facebook_app_id`, `email_verification`, `email_settings`, `email_options`)
VALUES (1, 1, 'Selloff App', '#0075bb', 'fb-app-id', 1, '{$emailSettings}', '{$emailOptions}');

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `lang_id` int DEFAULT NULL,
  `site_title` varchar(255) DEFAULT NULL,
  `about_footer` text,
  `contact_email` varchar(255) DEFAULT NULL,
  `social_media_data` text,
  `site_font` smallint DEFAULT NULL,
  `dashboard_font` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`id`, `lang_id`, `site_title`, `about_footer`, `contact_email`, `social_media_data`, `site_font`, `dashboard_font`)
VALUES (1, 1, 'Selloff Nigeria', 'About footer text', 'support@selloff.ng', '{$social}', 19, 22);

CREATE TABLE `fonts` (
  `id` int NOT NULL,
  `font_name` varchar(255) DEFAULT NULL,
  `font_url` text,
  `font_family` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `fonts` (`id`, `font_name`, `font_url`, `font_family`) VALUES
(19, 'Open Sans', 'https://fonts.test/open-sans.css', 'Open Sans'),
(22, 'Roboto', 'https://fonts.test/roboto.css', 'Roboto');

CREATE TABLE `product_settings` (
  `id` int NOT NULL,
  `marketplace_sku` tinyint(1) DEFAULT '1',
  `pagination_per_page` int DEFAULT '60'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `product_settings` (`id`, `marketplace_sku`, `pagination_per_page`) VALUES (1, 1, 45);
SQL);

        $context = new LegacyImportContext(dryRun: false);
        app(PlatformSettingsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertSame('Selloff App', PlatformSetting::query()->find('application_name')?->value);
        $this->assertSame('Selloff Nigeria', PlatformSetting::query()->find('site_name')?->value);
        $this->assertSame('About footer text', PlatformSetting::query()->find('about_footer')?->value);
        $this->assertSame('support@selloff.ng', PlatformSetting::query()->find('contact_email')?->value);
        $this->assertSame('https://facebook.com/selloff', PlatformSetting::query()->find('facebook_url')?->value);
        $this->assertSame('#0075bb', PlatformSetting::query()->find('primary_color')?->value);
        $this->assertSame('fb-app-id', PlatformSetting::query()->find('facebook_app_id')?->value);
        $this->assertSame('smtp.mail.test', PlatformSetting::query()->find('smtp_host')?->value);
        $this->assertSame('Selloff Mail', PlatformSetting::query()->find('mail_from_name')?->value);
        $this->assertTrue(PlatformSetting::query()->find('email_option_new_order')?->value);
        $this->assertFalse(PlatformSetting::query()->find('email_option_contact_messages')?->value);
        $this->assertSame('Open Sans', PlatformSetting::query()->find('site_font_family')?->value);
        $this->assertSame('Roboto', PlatformSetting::query()->find('dashboard_font_family')?->value);
        $this->assertTrue(PlatformSetting::query()->find('marketplace_sku')?->value);
        $this->assertSame(45, PlatformSetting::query()->find('pagination_per_page')?->value);

        @unlink($dumpPath);
    }
}
