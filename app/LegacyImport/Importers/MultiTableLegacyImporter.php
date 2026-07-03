<?php

namespace App\LegacyImport\Importers;

abstract class MultiTableLegacyImporter implements LegacyImporter
{
    /**
     * @return list<string>
     */
    abstract public function legacyTables(): array;

    public function legacyTable(): string
    {
        return $this->legacyTables()[0];
    }
}
