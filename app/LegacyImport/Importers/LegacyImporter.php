<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;

interface LegacyImporter
{
    public function legacyTable(): string;

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void;
}
