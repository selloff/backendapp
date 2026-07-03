<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class AdminCatalogParityHousekeepingTest extends TestCase
{
    /** @var array<string, string> */
    private const CATALOG_DONE_PATHS = [
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

    public function test_admin_catalog_rows_are_done_with_full_ui_parity_in_matrix(): void
    {
        $byPath = [];
        foreach ($this->matrixRows() as $row) {
            $byPath[$row['legacy_path']] = $row;
        }

        foreach (self::CATALOG_DONE_PATHS as $legacyPath => $spaPath) {
            $row = $byPath[$legacyPath] ?? null;
            $this->assertNotNull($row, "Missing matrix row: {$legacyPath}");
            $this->assertSame('done', $row['status'], $legacyPath);
            $this->assertSame($spaPath, $row['spa_path'], $legacyPath);
            $this->assertSame('full', $row['ui_parity'], $legacyPath);
        }
    }

    public function test_admin_catalog_done_paths_match_registry(): void
    {
        $registry = $this->loadRegistry();
        $done = $registry['done'] ?? [];

        foreach (self::CATALOG_DONE_PATHS as $legacyPath => $spaPath) {
            $this->assertArrayHasKey($legacyPath, $done, $legacyPath);
            $this->assertSame($spaPath, $done[$legacyPath]['spa_path'] ?? null, $legacyPath);
        }
    }

    public function test_admin_product_filter_partial_has_registry_waive_note(): void
    {
        $registry = $this->loadRegistry();

        $this->assertNotEmpty($registry['waived']['admin/product/_filter_products.php'] ?? null);
    }

    public function test_admin_custom_field_bulk_upload_is_done_in_registry(): void
    {
        $registry = $this->loadRegistry();
        $done = $registry['done'] ?? [];

        $this->assertSame('/admin/custom-fields/bulk', $done['admin/category/bulk_custom_field_upload.php']['spa_path'] ?? null);
        $this->assertArrayNotHasKey('admin/category/bulk_custom_field_upload.php', $registry['waived'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRegistry(): array
    {
        return json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
    }

    /**
     * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
     */
    private function matrixRows(): array
    {
        $path = base_path('../docs/spa-parity-matrix.csv');
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
}
