<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDepthLegacyImporterLongTagTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/long-tag.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_tag_longer_than_255_chars_is_truncated(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        $storedTag = DB::table('tags')->where('id', 4938)->value('tag');
        $this->assertSame(255, mb_strlen((string) $storedTag));
        $this->assertSame(1, DB::table('product_tag')->where('product_id', 9315)->where('tag_id', 4938)->count());
    }
}
