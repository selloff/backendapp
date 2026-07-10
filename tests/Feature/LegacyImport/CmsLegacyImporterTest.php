<?php

use App\LegacyImport\Importers\CmsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports slider ad spaces and mobile banners', function () {
    $dumpPath = storage_path('app/test-cms-import.sql');
    file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `slider` (
  `id` int NOT NULL,
  `lang_id` tinyint DEFAULT '1',
  `title` varchar(255) DEFAULT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `link` text,
  `item_order` smallint DEFAULT '1',
  `button_text` varchar(255) DEFAULT NULL,
  `animation_title` varchar(50) DEFAULT 'none',
  `animation_description` varchar(50) DEFAULT 'none',
  `animation_button` varchar(50) DEFAULT 'none',
  `image` varchar(255) DEFAULT NULL,
  `image_mobile` varchar(255) DEFAULT NULL,
  `text_color` varchar(30) DEFAULT '#ffffff',
  `button_color` varchar(30) DEFAULT '#222222',
  `button_text_color` varchar(30) DEFAULT '#ffffff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `slider` (`id`, `lang_id`, `title`, `description`, `link`, `item_order`, `button_text`, `animation_title`, `animation_description`, `animation_button`, `image`, `image_mobile`, `text_color`, `button_color`, `button_text_color`)
VALUES
(1,1,'Hero slide','Slide body','https://selloff.ng/shop',1,'Shop now','fadeInUp','fadeInUp','fadeInUp','uploads/slider/hero.webp','uploads/slider/hero-mobile.webp','#ffffff','#222222','#ffffff');

CREATE TABLE `ad_spaces` (
  `id` int NOT NULL,
  `lang_id` int DEFAULT '1',
  `ad_space` text,
  `ad_code_desktop` text,
  `desktop_width` int DEFAULT NULL,
  `desktop_height` int DEFAULT NULL,
  `ad_code_mobile` text,
  `mobile_width` int DEFAULT NULL,
  `mobile_height` int DEFAULT NULL,
  `storage` varchar(30) DEFAULT 'local'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `ad_spaces` (`id`, `lang_id`, `ad_space`, `ad_code_desktop`, `desktop_width`, `desktop_height`, `ad_code_mobile`, `mobile_width`, `mobile_height`, `storage`)
VALUES
(1,1,'index_1','<div>Desktop ad</div>',728,90,'<div>Mobile ad</div>',300,250,'local');

CREATE TABLE `homepage_banners` (
  `id` int NOT NULL,
  `banner_url` varchar(1000) DEFAULT NULL,
  `banner_image_path` varchar(255) DEFAULT NULL,
  `banner_order` int NOT NULL DEFAULT '1',
  `banner_width` double DEFAULT NULL,
  `banner_location` varchar(100) DEFAULT 'featured_products',
  `lang_id` int DEFAULT '1',
  `storage` varchar(30) DEFAULT 'local'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `homepage_banners` (`id`, `banner_url`, `banner_image_path`, `banner_order`, `banner_width`, `banner_location`, `lang_id`, `storage`)
VALUES
(2,'https://selloff.ng/promo','uploads/banners/promo.webp',2,50,'featured_products',1,'local');

CREATE TABLE `mobile_banner_ads` (
  `id` int unsigned NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `status` int DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `show_in_home` int DEFAULT NULL,
  `show_in_categories` int DEFAULT NULL,
  `show_in_other` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `mobile_banner_ads` (`id`, `image`, `url`, `status`, `show_in_home`, `show_in_categories`, `show_in_other`, `created_at`)
VALUES
(3,'uploads/mobile/ad.webp','https://selloff.ng/mobile',1,1,0,0,'2025-01-01 00:00:00');
SQL);

    app(CmsLegacyImporter::class)->import(
        new LegacyImportContext(dryRun: false),
        new MySqlDumpReader($dumpPath),
    );

    $this->assertDatabaseHas('sliders', [
        'id' => 1,
        'title' => 'Hero slide',
        'image_path' => 'uploads/slider/hero.webp',
        'image_mobile_path' => 'uploads/slider/hero-mobile.webp',
        'legacy_id' => 1,
    ]);

    $this->assertDatabaseHas('ad_spaces', [
        'id' => 1,
        'ad_space_key' => 'index_1',
        'desktop_width' => 728,
        'legacy_id' => 1,
    ]);

    $this->assertDatabaseHas('homepage_banners', [
        'id' => 2,
        'link' => 'https://selloff.ng/promo',
        'banner_location' => 'featured_products',
        'legacy_id' => 2,
    ]);

    $this->assertDatabaseHas('homepage_banners', [
        'id' => 1_000_030,
        'image_path' => 'uploads/mobile/ad.webp',
        'banner_location' => 'mobile_home',
        'legacy_id' => 3,
    ]);

    @unlink($dumpPath);
});
