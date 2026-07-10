<?php

use App\LegacyImport\LegacyImportVerifier;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('verifier accepts merged custom field product rows', function () {
    $product = Product::query()->firstOrFail();

    $fieldId = (int) DB::table('custom_fields')->insertGetId([
        'field_type' => 'single_select',
        'field_order' => 1,
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('custom_field_product')->delete();
    DB::table('legacy_import_maps')->where('new_table', 'custom_field_product')->delete();

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

    expect($result->passed())->toBeTrue(implode('; ', $result->errors));
    expect($result->warnings)->toContain('custom_fields_product: 2 legacy rows merged into existing custom_field_product rows during import (duplicate natural keys)');
});
