<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Facades\Schema;

class LegacyImportSchemaGuard
{
    /**
     * @return list<string> Missing PostgreSQL table names.
     */
    public static function missingRequiredTables(): array
    {
        $missing = [];

        foreach (config('selloff.legacy_import.required_schema_tables', []) as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }
}
