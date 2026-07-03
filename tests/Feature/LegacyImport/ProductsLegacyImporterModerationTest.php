<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsLegacyImporterModerationTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/product-moderation-import.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_published_legacy_product_with_verified_no_is_imported_as_admin_approved(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        $published = DB::table('products')->where('id', 95001)->first();
        $this->assertNotNull($published);
        $this->assertSame('published', $published->status);
        $this->assertTrue((bool) $published->is_verified);

        $pending = DB::table('products')->where('id', 95002)->first();
        $this->assertNotNull($pending);
        $this->assertSame('pending', $pending->status);
        $this->assertFalse((bool) $pending->is_verified);

        $rejected = DB::table('products')->where('id', 95003)->first();
        $this->assertNotNull($rejected);
        $this->assertSame('hidden', $rejected->status);
        $this->assertFalse((bool) $rejected->is_verified);
    }
}
