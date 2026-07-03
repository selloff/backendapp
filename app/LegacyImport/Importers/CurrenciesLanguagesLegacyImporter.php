<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class CurrenciesLanguagesLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'currencies';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable())) {
            return;
        }

        $this->importCurrencies($context, $reader);
        $this->importLanguages($context, $reader);
    }

    private function importCurrencies(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('currencies')) {
            return;
        }

        foreach ($reader->rows('currencies') as $row) {
            $context->notePlanned('currencies');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('currencies');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'code' => $row['code'] ?? ('CUR'.$legacyId),
                'name' => $row['name'] ?? null,
                'symbol' => $row['symbol'] ?? null,
                'currency_format' => $row['currency_format'] ?? 'us',
                'symbol_direction' => $row['symbol_direction'] ?? 'left',
                'space_money_symbol' => LegacyValueCoercer::bool($row['space_money_symbol'] ?? 0),
                'exchange_rate' => LegacyValueCoercer::decimal($row['exchange_rate'] ?? 1, 6),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('currencies')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'currencies', $legacyId, 'currencies', $legacyId);
            $context->noteImported('currencies');
        }
    }

    private function importLanguages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('languages') || ! ($context->shouldImportTable('languages') || $context->shouldImportTable('currencies'))) {
            return;
        }

        foreach ($reader->rows('languages') as $row) {
            $context->notePlanned('languages');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('languages');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'name' => (string) ($row['name'] ?? ('Language '.$legacyId)),
                'code' => $row['short_form'] ?? $row['code'] ?? ('lang-'.$legacyId),
                'language_code' => $row['language_code'] ?? ($row['short_form'] ?? 'en-US'),
                'text_direction' => $row['text_direction'] ?? 'ltr',
                'language_order' => (int) ($row['language_order'] ?? $legacyId),
                'text_editor_lang' => $row['text_editor_lang'] ?? 'en',
                'flag_path' => $row['flag_path'] ?? null,
                'is_default' => LegacyValueCoercer::bool($row['language_default'] ?? $row['is_default'] ?? 0),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('languages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'languages', $legacyId, 'languages', $legacyId);
            $context->noteImported('languages');
        }
    }
}
