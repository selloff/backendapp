<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class ShippingZonesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'shipping_zones';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable())) {
            return;
        }

        $this->importZones($context, $reader);
        $this->importZoneLocations($context, $reader);
        $this->importZoneMethods($context, $reader);
        $this->importDeliveryTimes($context, $reader);
    }

    private function importZones(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('shipping_zones')) {
            return;
        }

        foreach ($reader->rows('shipping_zones') as $row) {
            $context->notePlanned('shipping_zones');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('shipping_zones');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'seller_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'name' => $this->resolveZoneName($row),
                'estimated_delivery' => $this->resolveEstimatedDelivery($row),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('shipping_zones')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'shipping_zones', $legacyId, 'shipping_zones', $legacyId);
            $context->noteImported('shipping_zones');
        }
    }

    private function importZoneLocations(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('shipping_zone_locations')) {
            return;
        }

        foreach ($reader->rows('shipping_zone_locations') as $row) {
            $context->notePlanned('shipping_zone_locations');

            $legacyId = (int) ($row['id'] ?? 0);
            $zoneId = $context->resolveId('shipping_zones', (int) ($row['zone_id'] ?? 0));
            if ($legacyId <= 0 || $zoneId === null) {
                $context->noteSkipped('shipping_zone_locations');

                continue;
            }

            $countryId = $this->resolveOptionalLocationId($context, 'location_countries', $row['country_id'] ?? null);
            $stateId = $this->resolveOptionalLocationId($context, 'location_states', $row['state_id'] ?? null);
            $cityId = $this->resolveOptionalLocationId($context, 'location_cities', $row['city_id'] ?? null);

            $payload = [
                'id' => $legacyId,
                'shipping_zone_id' => $zoneId,
                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('shipping_zone_locations')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'shipping_zone_locations', $legacyId, 'shipping_zone_locations', $legacyId);
            $context->noteImported('shipping_zone_locations');
        }
    }

    private function importZoneMethods(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('shipping_zone_methods')) {
            return;
        }

        foreach ($reader->rows('shipping_zone_methods') as $row) {
            $context->notePlanned('shipping_zone_methods');

            $legacyId = (int) ($row['id'] ?? 0);
            $zoneId = $context->resolveId('shipping_zones', (int) ($row['zone_id'] ?? 0));
            if ($legacyId <= 0 || $zoneId === null) {
                $context->noteSkipped('shipping_zone_methods');

                continue;
            }

            $methodType = in_array($row['method_type'] ?? '', ['flat_rate', 'local_pickup', 'free_shipping'], true)
                ? (string) $row['method_type']
                : 'flat_rate';

            $flatRateCosts = $this->resolveFlatRateCosts($row['flat_rate_costs'] ?? null);
            $costCalculationType = in_array($row['cost_calculation_type'] ?? '', ['total_weight', 'per_order', 'per_item'], true)
                ? (string) $row['cost_calculation_type']
                : ($methodType === 'flat_rate' ? 'total_weight' : null);

            $attributes = [
                'id' => $legacyId,
                'shipping_zone_id' => $zoneId,
                'name' => $this->resolveMethodName($row),
                'method_type' => $methodType,
                'free_shipping_min_amount' => LegacyValueCoercer::decimal($row['free_shipping_min_amount'] ?? null),
                'local_pickup_cost' => LegacyValueCoercer::decimal($row['local_pickup_cost'] ?? null),
                'cost_calculation_type' => $costCalculationType,
                'shipping_flat_cost' => LegacyValueCoercer::decimal($row['shipping_flat_cost'] ?? null),
                'flat_rate_costs' => $flatRateCosts !== [] ? json_encode($flatRateCosts) : null,
                'flat_rate' => LegacyValueCoercer::decimal(
                    $row['shipping_flat_cost']
                        ?? $row['local_pickup_cost']
                        ?? ($flatRateCosts[0]['cost'] ?? 0)
                ),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('shipping_methods')->updateOrInsert(['id' => $legacyId], $attributes);
            }

            $this->maps->remember($context, 'shipping_zone_methods', $legacyId, 'shipping_methods', $legacyId);
            $context->noteImported('shipping_zone_methods');
        }
    }

    private function importDeliveryTimes(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('shipping_delivery_times')) {
            return;
        }

        foreach ($reader->rows('shipping_delivery_times') as $row) {
            $context->notePlanned('shipping_delivery_times');

            $legacyId = (int) ($row['id'] ?? 0);
            $sellerId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $sellerId === null) {
                $context->noteSkipped('shipping_delivery_times');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'seller_id' => $sellerId,
                'label' => $this->resolveDeliveryTimeLabel($row),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('delivery_time_options')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'shipping_delivery_times', $legacyId, 'delivery_time_options', $legacyId);
            $context->noteImported('shipping_delivery_times');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveMethodName(array $row): string
    {
        if (! empty($row['name_array'])) {
            $decoded = @unserialize((string) $row['name_array'], ['allowed_classes' => false]);
            if (is_array($decoded) && isset($decoded[0]['name'])) {
                return (string) $decoded[0]['name'];
            }
        }

        if (! empty($row['name'])) {
            return (string) $row['name'];
        }

        if (! empty($row['method_type'])) {
            return ucwords(str_replace('_', ' ', (string) $row['method_type']));
        }

        return 'Method '.((int) ($row['id'] ?? 0));
    }

    /**
     * @return list<array{min_weight: float, max_weight: float|null, cost: float}>
     */
    private function resolveFlatRateCosts(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return [];
            }

            $rates = [];
            foreach ($decoded as $rate) {
                if (! is_array($rate) || ! isset($rate['cost']) || ! is_numeric($rate['cost'])) {
                    continue;
                }

                $rates[] = [
                    'min_weight' => isset($rate['min_weight']) && is_numeric($rate['min_weight']) ? (float) $rate['min_weight'] : 0.0,
                    'max_weight' => isset($rate['max_weight']) && $rate['max_weight'] !== '' && is_numeric($rate['max_weight'])
                        ? (float) $rate['max_weight']
                        : null,
                    'cost' => (float) $rate['cost'],
                ];
            }

            return $rates;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveEstimatedDelivery(array $row): ?string
    {
        if (! empty($row['estimated_delivery'])) {
            $decoded = @unserialize((string) $row['estimated_delivery'], ['allowed_classes' => false]);
            if (is_array($decoded) && isset($decoded[0]['name'])) {
                return (string) $decoded[0]['name'];
            }

            return (string) $row['estimated_delivery'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveDeliveryTimeLabel(array $row): string
    {
        if (! empty($row['option_array'])) {
            $decoded = @unserialize((string) $row['option_array'], ['allowed_classes' => false]);
            if (is_array($decoded) && isset($decoded[0]['option'])) {
                return (string) $decoded[0]['option'];
            }
        }

        return 'Delivery time '.((int) ($row['id'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveZoneName(array $row): string
    {
        if (! empty($row['name'])) {
            return (string) $row['name'];
        }

        if (! empty($row['name_array'])) {
            $decoded = @unserialize((string) $row['name_array'], ['allowed_classes' => false]);
            if (is_array($decoded) && isset($decoded[0]['name'])) {
                return (string) $decoded[0]['name'];
            }
        }

        return 'Zone '.((int) ($row['id'] ?? 0));
    }

    private function resolveOptionalLocationId(LegacyImportContext $context, string $legacyTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId($legacyTable, (int) $legacyId);
    }
}
