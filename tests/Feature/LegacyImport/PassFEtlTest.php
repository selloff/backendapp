<?php

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('dry run reports zero unhandled tables for subset fixture', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry-run coverage: all dump tables are handled or explicitly skipped.');
});

test('live import populates social content and support tables', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(DB::table('followers')->where('user_id', 102)->where('follower_id', 103)->count())->toBe(1);
    expect(DB::table('comments')->where('legacy_id', 1301)->count())->toBe(1);
    expect(DB::table('conversations')->where('legacy_id', 1401)->count())->toBe(1);
    expect(DB::table('messages')->where('legacy_id', 1501)->count())->toBe(1);
    expect(DB::table('blog_categories')->where('legacy_id', 1601)->count())->toBe(1);
    expect(DB::table('blog_posts')->where('legacy_id', 1701)->count())->toBe(1);
    expect(DB::table('blog_post_category')->where('blog_post_id', 1701)->where('blog_category_id', 1601)->count())->toBe(1);
    expect(DB::table('pages')->where('legacy_id', 1801)->count())->toBe(1);
    expect(DB::table('knowledge_base_categories')->where('legacy_id', 1901)->count())->toBe(1);
    expect(DB::table('knowledge_base_articles')->where('legacy_id', 2001)->count())->toBe(1);
    expect(DB::table('support_tickets')->where('legacy_id', 2101)->count())->toBe(1);
    expect(DB::table('support_messages')->where('legacy_id', 2201)->count())->toBe(1);
    expect(DB::table('contact_messages')->where('legacy_id', 2301)->count())->toBe(1);
});

test('verify legacy import passes pass f row counts and orphans', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('coverage helper matches orchestrator for subset fixture', function () {
    $reader = new MySqlDumpReader($this->fixture);
    $coverage = app(LegacyImportCoverage::class);
    $orchestrator = app(LegacyImportOrchestrator::class);

    $unhandled = $coverage->unhandledTables(
        $reader,
        $orchestrator->coveredLegacyTables(),
        LegacyImportConfig::coverageExcludedTables(),
    );

    expect($unhandled)->toBe([]);
});