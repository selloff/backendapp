<?php

namespace App\LegacyImport\Importers;

/**
 * Importers that manage their own writes across many rows (metrics rebuild, etc.)
 * must not run inside a single DB::transaction — it holds locks too long and deadlocks.
 */
interface NonTransactionalLegacyImporter extends LegacyImporter
{
}
