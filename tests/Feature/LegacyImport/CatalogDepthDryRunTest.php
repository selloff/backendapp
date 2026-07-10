<?php

use App\LegacyImport\Importers\CatalogDepthLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('product tags dry run does not insert tags', function () {
    $dumpPath = storage_path('app/test-product-tags-dry-run.sql');
    file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `users` (`id` int NOT NULL, `email` varchar(255) NOT NULL, `password` varchar(255) NOT NULL, `role_id` int DEFAULT 1, PRIMARY KEY (`id`));
INSERT INTO `users` (`id`, `email`, `password`, `role_id`) VALUES (1, 'vendor@test.local', '$2y$10$test', 2);

CREATE TABLE `categories` (`id` int NOT NULL, `slug` varchar(255) NOT NULL, `status` tinyint(1) DEFAULT 1, PRIMARY KEY (`id`));
INSERT INTO `categories` (`id`, `slug`, `status`) VALUES (1, 'cat', 1);

CREATE TABLE `products` (`id` int NOT NULL, `user_id` int NOT NULL, `slug` varchar(255) NOT NULL, `status` tinyint(1) DEFAULT 1, PRIMARY KEY (`id`));
INSERT INTO `products` (`id`, `user_id`, `slug`, `status`) VALUES (501, 1, 'dry-run-product', 1);

CREATE TABLE `product_tags` (`id` int NOT NULL, `product_id` int NOT NULL, `lang_id` int DEFAULT 1, `tag` varchar(255) NOT NULL, PRIMARY KEY (`id`));
INSERT INTO `product_tags` (`id`, `product_id`, `lang_id`, `tag`) VALUES (1, 501, 1, 'dry-run-only-tag');
SQL);

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $dumpPath,
        '--skip-verify' => true,
    ])->assertSuccessful();

    $tagCountBefore = DB::table('tags')->count();
    $pivotCountBefore = DB::table('product_tag')->count();

    $context = new LegacyImportContext(dryRun: true, tableFilter: 'product_tags');
    app(CatalogDepthLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    expect(DB::table('tags')->count())->toBe($tagCountBefore);
    expect(DB::table('product_tag')->count())->toBe($pivotCountBefore);
    expect(DB::table('tags')->where('tag')->value('id'))->toBeNull();

    @unlink($dumpPath);
});
