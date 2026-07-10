<?php

namespace Tests\Feature\Coverage;

const CATALOG_DONE_PATHS = [
    'admin/brand/brands.php' => '/admin/brands',
    'admin/brand/add.php' => '/admin/brands/new',
    'admin/brand/edit.php' => '/admin/brands/:id/edit',
    'admin/category/categories.php' => '/admin/categories',
    'admin/category/add_category.php' => '/admin/categories',
    'admin/category/edit_category.php' => '/admin/categories',
    'admin/category/buld_category_upload.php' => '/admin/categories/import',
    'admin/category/bulk_custom_field_upload.php' => '/admin/custom-fields/bulk',
    'admin/category/custom_fields.php' => '/admin/custom-fields',
    'admin/category/add_custom_field.php' => '/admin/custom-fields',
    'admin/category/edit_custom_field.php' => '/admin/custom-fields',
    'admin/category/custom_field_options.php' => '/admin/custom-fields/:id/options',
    'admin/category/tags.php' => '/admin/tags',
    'admin/product/products.php' => '/admin/products',
    'admin/product/product_details.php' => '/admin/products/:id',
    'admin/product/featured_products_pricing.php' => '/admin/featured-pricing',
];

test('admin catalog rows are done with full ui parity in matrix', function () {
    $byPath = [];
    foreach (matrixRows_in_AdminCatalogParityHousekeeping() as $row) {
        $byPath[$row['legacy_path']] = $row;
    }

    foreach (CATALOG_DONE_PATHS as $legacyPath => $spaPath) {
        $row = $byPath[$legacyPath] ?? null;
        expect($row)->not->toBeNull("Missing matrix row: {$legacyPath}");
        expect($row['status'])->toBe('done');
        expect($row['spa_path'])->toBe($spaPath);
        expect($row['ui_parity'])->toBe('full');
    }
});

test('admin catalog done paths match registry', function () {
    $registry = loadRegistry_in_AdminCatalogParityHousekeeping();
    $done = $registry['done'] ?? [];

    foreach (CATALOG_DONE_PATHS as $legacyPath => $spaPath) {
        expect($done)->toHaveKey($legacyPath);
        expect($done[$legacyPath]['spa_path'] ?? null)->toBe($spaPath);
    }
});

test('admin product filter partial has registry waive note', function () {
    $registry = loadRegistry_in_AdminCatalogParityHousekeeping();

    expect($registry['waived']['admin/product/_filter_products.php'] ?? null)->not->toBeEmpty();
});

test('admin custom field bulk upload is done in registry', function () {
    $registry = loadRegistry_in_AdminCatalogParityHousekeeping();
    $done = $registry['done'] ?? [];

    expect($done['admin/category/bulk_custom_field_upload.php']['spa_path'] ?? null)->toBe('/admin/custom-fields/bulk');
    $this->assertArrayNotHasKey('admin/category/bulk_custom_field_upload.php', $registry['waived'] ?? []);
});

/**
 * @return array<string, mixed>
 */
function loadRegistry_in_AdminCatalogParityHousekeeping(): array
{
    return json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
}

/**
 * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
 */
function matrixRows_in_AdminCatalogParityHousekeeping(): array
{
    $path = monorepo_path('docs/spa-parity-matrix.csv');
    $rows = [];
    $lines = array_slice(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))), 1);

    foreach ($lines as $line) {
        if (! preg_match('/^"([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)"$/', $line, $matches)) {
            continue;
        }

        $rows[] = [
            'legacy_path' => $matches[2],
            'spa_path' => $matches[3],
            'status' => $matches[4],
            'ui_parity' => $matches[5],
        ];
    }

    return $rows;
}
