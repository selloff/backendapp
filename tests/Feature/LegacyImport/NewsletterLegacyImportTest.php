<?php

use App\LegacyImport\Importers\NewsletterSubscribersLegacyImporter;
use App\LegacyImport\Importers\PlatformSettingsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Modules\Selloff\Notification\Services\NewsletterSettingsService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports newsletter settings and subscribers', function () {
    $newsletterSettings = addslashes(serialize([
        'status' => '1',
        'is_popup_active' => '1',
        'image' => '',
        'storage' => 'local',
    ]));

    $dumpPath = storage_path('app/test-newsletter-import.sql');
    file_put_contents($dumpPath, <<<SQL
CREATE TABLE `general_settings` (
  `id` int NOT NULL,
  `site_lang` int DEFAULT '1',
  `newsletter_settings` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `general_settings` (`id`, `site_lang`, `newsletter_settings`)
VALUES (1, 1, '{$newsletterSettings}');

CREATE TABLE `subscribers` (
  `id` int NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `subscribers` (`id`, `email`, `token`, `created_at`)
VALUES (9001, 'legacy-subscriber@selloff.test', 'legacy-token', '2024-01-01 10:00:00');
SQL);

    $context = new LegacyImportContext(dryRun: false, tableFilter: 'general_settings');
    app(PlatformSettingsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    $settings = app(NewsletterSettingsService::class)->settings();
    expect($settings['newsletter_status'])->toBeTrue();
    expect($settings['newsletter_popup_active'])->toBeTrue();

    app(NewsletterSubscribersLegacyImporter::class)->import(
        new LegacyImportContext(dryRun: false, tableFilter: 'subscribers'),
        new MySqlDumpReader($dumpPath),
    );

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'legacy-subscriber@selloff.test',
        'legacy_id' => 9001,
    ]);

    @unlink($dumpPath);
});
