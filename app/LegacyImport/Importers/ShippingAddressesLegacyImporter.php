<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class ShippingAddressesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'shipping_addresses';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('shipping_addresses')) {
            return;
        }

        foreach ($reader->rows('shipping_addresses') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $userId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $countryId = $this->resolveLocationId($context, 'location_countries', $row['country_id'] ?? null);
            $stateId = $this->resolveLocationId($context, 'location_states', $row['state_id'] ?? null);
            $cityId = $this->resolveLocationId($context, 'location_cities', $row['city_id'] ?? null);

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'title' => $row['title'] ?? null,
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $row['email'] ?? null,
                'phone_number' => $row['phone_number'] ?? null,
                'address' => $row['address'] ?? null,
                'address_2' => $row['address_2'] ?? null,
                'zip_code' => $row['zip_code'] ?? null,
                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,
                'is_default' => LegacyValueCoercer::bool($row['is_default'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('shipping_addresses')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'shipping_addresses', $legacyId, 'shipping_addresses', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    private function resolveLocationId(LegacyImportContext $context, string $legacyTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId($legacyTable, (int) $legacyId);
    }
}
