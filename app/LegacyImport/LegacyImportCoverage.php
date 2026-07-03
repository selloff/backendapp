<?php

namespace App\LegacyImport;

class LegacyImportCoverage
{
    /**
     * @param  list<string>  $coveredTables
     * @param  list<string>  $skipTables
     * @return list<string>
     */
    public function unhandledTables(MySqlDumpReader $reader, array $coveredTables, array $skipTables): array
    {
        $covered = array_map('strtolower', $coveredTables);
        $skip = array_map('strtolower', $skipTables);
        $unhandled = [];

        foreach ($reader->tableNames() as $table) {
            $normalized = strtolower($table);
            if (in_array($normalized, $covered, true) || in_array($normalized, $skip, true)) {
                continue;
            }

            $unhandled[] = $table;
        }

        sort($unhandled);

        return $unhandled;
    }
}
