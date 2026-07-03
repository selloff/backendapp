<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class RoutesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'routes';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable())) {
            return;
        }

        if (! $reader->hasTable('routes')) {
            return;
        }

        foreach ($reader->rows('routes') as $row) {
            $context->notePlanned('routes');

            $legacyId = (int) ($row['id'] ?? 0);
            $routeKey = LegacyValueCoercer::stringMax($row['route_key'] ?? '', 100, '') ?? '';
            $slug = LegacyValueCoercer::stringMax($row['route'] ?? '', 100, '') ?? '';

            if ($legacyId <= 0 || $routeKey === '' || $slug === '') {
                $context->noteSkipped('routes');

                continue;
            }

            $now = now();

            if ($context->dryRun) {
                $this->maps->remember($context, 'routes', $legacyId, 'route_slugs', $legacyId);
                $context->noteImported('routes');

                continue;
            }

            $existing = DB::table('route_slugs')->where('route_key', $routeKey)->first();

            if ($existing) {
                DB::table('route_slugs')->where('id', $existing->id)->update([
                    'slug' => $slug,
                    'legacy_id' => $legacyId,
                    'updated_at' => $now,
                ]);
                $newId = (int) $existing->id;
            } else {
                $newId = (int) DB::table('route_slugs')->insertGetId([
                    'route_key' => $routeKey,
                    'slug' => $slug,
                    'legacy_id' => $legacyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->maps->remember($context, 'routes', $legacyId, 'route_slugs', $newId);
            $context->noteImported('routes');
        }
    }
}
