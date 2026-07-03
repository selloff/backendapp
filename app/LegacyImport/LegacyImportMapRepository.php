<?php

namespace App\LegacyImport;

use Illuminate\Support\Facades\DB;

class LegacyImportMapRepository
{
    public function remember(LegacyImportContext $context, string $legacyTable, int $legacyId, string $newTable, int $newId): void
    {
        $context->rememberMap($legacyTable, $legacyId, $newTable, $newId);

        if ($context->dryRun) {
            return;
        }

        DB::table('legacy_import_maps')->updateOrInsert(
            [
                'legacy_table' => $legacyTable,
                'legacy_id' => $legacyId,
            ],
            [
                'new_table' => $newTable,
                'new_id' => $newId,
                'imported_at' => now(),
            ],
        );
    }

    public function hydrateContext(LegacyImportContext $context): void
    {
        if ($context->dryRun || ! \Illuminate\Support\Facades\Schema::hasTable('legacy_import_maps')) {
            return;
        }

        $rows = DB::table('legacy_import_maps')
            ->orderBy('legacy_table')
            ->orderBy('legacy_id')
            ->get(['legacy_table', 'legacy_id', 'new_table', 'new_id']);

        foreach ($rows as $row) {
            $context->rememberMap(
                (string) $row->legacy_table,
                (int) $row->legacy_id,
                (string) $row->new_table,
                (int) $row->new_id,
            );
        }
    }
}
