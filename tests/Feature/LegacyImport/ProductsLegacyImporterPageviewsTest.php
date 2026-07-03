<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsLegacyImporterPageviewsTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/product-pageviews-import.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_legacy_pageviews_are_imported_on_products(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        $popular = DB::table('products')->where('id', 96001)->first();
        $this->assertNotNull($popular);
        $this->assertSame(1847, (int) $popular->pageviews);

        $newListing = DB::table('products')->where('id', 96002)->first();
        $this->assertNotNull($newListing);
        $this->assertSame(0, (int) $newListing->pageviews);
    }

    public function test_backfill_command_restores_pageviews_from_dump(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        DB::table('products')->where('id', 96001)->update(['pageviews' => 0]);

        $this->artisan('selloff:backfill-product-pageviews', ['--source' => $this->fixture])->assertSuccessful();

        $this->assertSame(1847, (int) DB::table('products')->where('id', 96001)->value('pageviews'));
    }
}
