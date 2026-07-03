<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductsLegacyImporterLongTitleTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/long-title-product.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_product_title_longer_than_255_chars_is_truncated(): void
    {
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

        $this->assertNotNull(DB::table('users')->where('id', 91001)->first(), 'vendor user missing');
        $this->assertNotNull(DB::table('products')->where('id', 9315)->first(), 'product missing');

        $storedTitle = DB::table('product_translations')->where('product_id', 9315)->value('title');
        $this->assertSame(255, mb_strlen((string) $storedTitle));
        $this->assertSame(str_repeat('A', 255), $storedTitle);
        $this->assertSame('Full description preserved', DB::table('product_translations')->where('product_id', 9315)->value('description'));
    }
}
