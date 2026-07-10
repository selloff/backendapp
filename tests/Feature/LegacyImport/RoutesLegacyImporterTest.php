<?php

use App\LegacyImport\Importers\RoutesLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports legacy routes into route slugs', function () {
    $dumpPath = storage_path('app/test-routes-import.sql');
    file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `routes` (
  `id` int NOT NULL,
  `route_key` varchar(100) NOT NULL,
  `route` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `routes` (`id`, `route_key`, `route`)
VALUES
(4,'admin','admin-2025'),
(7,'cart','shopping-cart');
SQL);

    $context = new LegacyImportContext(dryRun: false, tableFilter: 'routes');

    app(RoutesLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    expect(DB::table('route_slugs')->where('route_key', 'admin')->value('slug'))->toBe('admin-2025');
    expect((int) DB::table('route_slugs')->where('route_key', 'admin')->value('legacy_id'))->toBe(4);
    expect(DB::table('route_slugs')->where('route_key', 'cart')->value('slug'))->toBe('shopping-cart');
    expect(DB::table('route_slugs')->count())->toBeGreaterThanOrEqual(70);

    @unlink($dumpPath);
});

test('dry run does not write route slugs', function () {
    $dumpPath = storage_path('app/test-routes-dry-run.sql');
    file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `routes` (
  `id` int NOT NULL,
  `route_key` varchar(100) NOT NULL,
  `route` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `routes` (`id`, `route_key`, `route`)
VALUES
(99,'dry-run-test','dry-run-slug');
SQL);

    $before = DB::table('route_slugs')->count();
    $context = new LegacyImportContext(dryRun: true, tableFilter: 'routes');

    app(RoutesLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    expect(DB::table('route_slugs')->count())->toBe($before);
    expect(DB::table('route_slugs')->where('route_key', 'dry-run-test')->value('slug'))->toBeNull();

    @unlink($dumpPath);
});
