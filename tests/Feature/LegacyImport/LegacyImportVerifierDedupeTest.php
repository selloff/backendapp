<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\LegacyImportVerifier;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyImportVerifierDedupeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_verifier_accepts_merged_custom_field_product_rows(): void
    {
        $product = Product::query()->firstOrFail();

        $fieldId = (int) DB::table('custom_fields')->insertGetId([
            'field_type' => 'single_select',
            'field_order' => 1,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rowId = (int) DB::table('custom_field_product')->insertGetId([
            'custom_field_id' => $fieldId,
            'product_id' => $product->id,
            'field_value' => 'latest',
        ]);

        foreach ([101, 102, 103] as $legacyId) {
            DB::table('legacy_import_maps')->insert([
                'legacy_table' => 'custom_fields_product',
                'legacy_id' => $legacyId,
                'new_table' => 'custom_field_product',
                'new_id' => $rowId,
                'imported_at' => now(),
            ]);
        }

        $result = app(LegacyImportVerifier::class)->verify();

        $this->assertTrue($result->passed(), implode('; ', $result->errors));
        $this->assertContains(
            'custom_fields_product: 2 legacy rows merged into existing custom_field_product rows during import (duplicate natural keys)',
            $result->warnings,
        );
    }
}
