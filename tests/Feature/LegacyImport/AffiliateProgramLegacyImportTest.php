<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\PlatformSettingsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use Tests\TestCase;

class AffiliateProgramLegacyImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_affiliate_program_settings_and_localized_content(): void
    {
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
        $this->assertTrue($program['status']);
        $this->assertSame('seller_based', $program['type']);
        $this->assertSame(10.0, $program['commission_rate']);
        $this->assertSame('Boost Your Earnings', $program['description']['title']);
        $this->assertSame('Join the affiliate program.', $program['description']['description']);
        $this->assertSame('Why Join', $program['content']['title']);
        $this->assertStringContainsString('Competitive commissions', $program['content']['content']);
        $this->assertCount(3, $program['how_it_works']);
        $this->assertSame('How do I join?', $program['faq'][0]['q']);

        @unlink($dumpPath);
    }
}
