<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\RoutesLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutesLegacyImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_legacy_routes_into_route_slugs(): void
    {
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

        $this->assertSame('admin-2025', DB::table('route_slugs')->where('route_key', 'admin')->value('slug'));
        $this->assertSame(4, (int) DB::table('route_slugs')->where('route_key', 'admin')->value('legacy_id'));
        $this->assertSame('shopping-cart', DB::table('route_slugs')->where('route_key', 'cart')->value('slug'));
        $this->assertGreaterThanOrEqual(70, DB::table('route_slugs')->count());

        @unlink($dumpPath);
    }

    public function test_dry_run_does_not_write_route_slugs(): void
    {
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

        $this->assertSame($before, DB::table('route_slugs')->count());
        $this->assertNull(DB::table('route_slugs')->where('route_key', 'dry-run-test')->value('slug'));

        @unlink($dumpPath);
    }
}
