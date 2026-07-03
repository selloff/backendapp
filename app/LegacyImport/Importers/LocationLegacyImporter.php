<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class LocationLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'location_countries';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable())) {
            return;
        }

        $this->importCountries($context, $reader);
        $this->importStates($context, $reader);
        $this->importCities($context, $reader);
    }

    private function importCountries(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('location_countries')) {
            return;
        }

        foreach ($reader->rows('location_countries') as $row) {
            $context->notePlanned('location_countries');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('location_countries');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'name' => (string) ($row['name'] ?? ('Country '.$legacyId)),
                'continent_code' => $row['continent_code'] ?? null,
                'code' => $row['code'] ?? null,
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('countries')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'location_countries', $legacyId, 'countries', $legacyId);
            $context->noteImported('location_countries');
        }
    }

    private function importStates(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('location_states')) {
            return;
        }

        foreach ($reader->rows('location_states') as $row) {
            $context->notePlanned('location_states');

            $legacyId = (int) ($row['id'] ?? 0);
            $countryId = $context->resolveId('location_countries', (int) ($row['country_id'] ?? 0));
            if ($legacyId <= 0 || $countryId === null) {
                $context->noteSkipped('location_states');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'country_id' => $countryId,
                'name' => (string) ($row['name'] ?? ('State '.$legacyId)),
                'code' => $row['code'] ?? null,
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('states')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'location_states', $legacyId, 'states', $legacyId);
            $context->noteImported('location_states');
        }
    }

    private function importCities(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('location_cities')) {
            return;
        }

        foreach ($reader->rows('location_cities') as $row) {
            $context->notePlanned('location_cities');

            $legacyId = (int) ($row['id'] ?? 0);
            $stateId = $context->resolveId('location_states', (int) ($row['state_id'] ?? 0));
            if ($legacyId <= 0 || $stateId === null) {
                $context->noteSkipped('location_cities');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'state_id' => $stateId,
                'name' => (string) ($row['name'] ?? ('City '.$legacyId)),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('cities')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'location_cities', $legacyId, 'cities', $legacyId);
            $context->noteImported('location_cities');
        }
    }
}
