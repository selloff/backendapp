<?php

use App\LegacyImport\Importers\PlatformSettingsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports affiliate program settings and localized content', function () {
    $affiliateSettings = addslashes(serialize([
        'status' => '1',
        'type' => 'seller_based',
        'commission_rate' => 10,
        'discount_rate' => 0,
        'image' => 0,
        'storage' => 'local',
    ]));
    $description = addslashes(serialize([
        'title' => 'Boost Your Earnings',
        'description' => 'Join the affiliate program.',
    ]));
    $content = addslashes(serialize([
        'title' => 'Why Join',
        'content' => '<p>Competitive commissions.</p>',
    ]));
    $works = addslashes(serialize([
        ['title' => 'Sign up', 'description' => 'Register quickly.'],
        ['title' => 'Share', 'description' => 'Share your links.'],
        ['title' => 'Earn', 'description' => 'Earn commission.'],
    ]));
    $faq = addslashes(serialize([
        ['o' => '1', 'q' => 'How do I join?', 'a' => 'Click Join Now.'],
    ]));

    $dumpPath = storage_path('app/test-affiliate-import.sql');
    file_put_contents($dumpPath, <<<SQL
CREATE TABLE `general_settings` (
  `id` int NOT NULL,
  `site_lang` int DEFAULT '1',
  `affiliate_settings` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `general_settings` (`id`, `site_lang`, `affiliate_settings`)
VALUES (1, 1, '{$affiliateSettings}');

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `lang_id` int NOT NULL,
  `affiliate_description` text,
  `affiliate_content` longtext,
  `affiliate_faq` mediumtext,
  `affiliate_works` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`id`, `lang_id`, `affiliate_description`, `affiliate_content`, `affiliate_faq`, `affiliate_works`)
VALUES (1, 1, '{$description}', '{$content}', '{$faq}', '{$works}');
SQL);

    $context = new LegacyImportContext(dryRun: false, tableFilter: 'general_settings');
    app(PlatformSettingsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    $program = app(AffiliateProgramSettingsService::class)->adminProgram(1);
    expect($program['status'])->toBeTrue();
    expect($program['type'])->toBe('seller_based');
    expect($program['commission_rate'])->toBe(10.0);
    expect($program['description']['title'])->toBe('Boost Your Earnings');
    expect($program['description']['description'])->toBe('Join the affiliate program.');
    expect($program['content']['title'])->toBe('Why Join');
    $this->assertStringContainsString('Competitive commissions', $program['content']['content']);
    expect($program['how_it_works'])->toHaveCount(3);
    expect($program['faq'][0]['q'])->toBe('How do I join?');

    @unlink($dumpPath);
});
