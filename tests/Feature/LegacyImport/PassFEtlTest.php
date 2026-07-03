<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PassFEtlTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_dry_run_reports_zero_unhandled_tables_for_subset_fixture(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry-run coverage: all dump tables are handled or explicitly skipped.');
    }

    public function test_live_import_populates_social_content_and_support_tables(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('followers')->where('user_id', 102)->where('follower_id', 103)->count());
        $this->assertSame(1, DB::table('comments')->where('legacy_id', 1301)->count());
        $this->assertSame(1, DB::table('conversations')->where('legacy_id', 1401)->count());
        $this->assertSame(1, DB::table('messages')->where('legacy_id', 1501)->count());
        $this->assertSame(1, DB::table('blog_categories')->where('legacy_id', 1601)->count());
        $this->assertSame(1, DB::table('blog_posts')->where('legacy_id', 1701)->count());
        $this->assertSame(1, DB::table('blog_post_category')->where('blog_post_id', 1701)->where('blog_category_id', 1601)->count());
        $this->assertSame(1, DB::table('pages')->where('legacy_id', 1801)->count());
        $this->assertSame(1, DB::table('knowledge_base_categories')->where('legacy_id', 1901)->count());
        $this->assertSame(1, DB::table('knowledge_base_articles')->where('legacy_id', 2001)->count());
        $this->assertSame(1, DB::table('support_tickets')->where('legacy_id', 2101)->count());
        $this->assertSame(1, DB::table('support_messages')->where('legacy_id', 2201)->count());
        $this->assertSame(1, DB::table('contact_messages')->where('legacy_id', 2301)->count());
    }

    public function test_verify_legacy_import_passes_pass_f_row_counts_and_orphans(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_coverage_helper_matches_orchestrator_for_subset_fixture(): void
    {
        $reader = new MySqlDumpReader($this->fixture);
        $coverage = app(LegacyImportCoverage::class);
        $orchestrator = app(LegacyImportOrchestrator::class);

        $unhandled = $coverage->unhandledTables(
            $reader,
            $orchestrator->coveredLegacyTables(),
            LegacyImportConfig::coverageExcludedTables(),
        );

        $this->assertSame([], $unhandled);
    }
}
