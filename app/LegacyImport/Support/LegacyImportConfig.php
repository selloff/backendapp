<?php

namespace App\LegacyImport\Support;

class LegacyImportConfig
{
    /**
     * Legacy dump tables excluded from coverage reporting only.
     *
     * These tables are intentionally not imported and must never gate
     * LegacyImportOrchestrator importers — use exclude_importers for that.
     *
     * @return list<string>
     */
    public static function coverageExcludedTables(): array
    {
        $tables = config('selloff.legacy_import.coverage_excluded_tables');

        if (is_array($tables)) {
            return $tables;
        }

        // Back-compat: older configs used skip_tables for the same purpose.
        $legacy = config('selloff.legacy_import.skip_tables');

        return is_array($legacy) ? $legacy : ['ci_sessions'];
    }

    /**
     * Importer class names to skip during orchestrated import (opt-in safety valve).
     *
     * @return list<class-string>
     */
    public static function excludedImporters(): array
    {
        $importers = config('selloff.legacy_import.exclude_importers');

        return is_array($importers) ? $importers : [];
    }
}
