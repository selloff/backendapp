<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Modules\Selloff\Catalog\Services\ListingPerformanceLegacyMetricsSync;

class VendorListingPerformanceLegacyImporter implements NonTransactionalLegacyImporter
{
    public function __construct(
        private readonly ListingPerformanceLegacyMetricsSync $sync,
    ) {}

    public function legacyTable(): string
    {
        return 'products';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('products')) {
            return;
        }

        $context->notePlanned($this->legacyTable());

        $stats = $this->sync->syncAllFromDatabase($context->dryRun);

        for ($i = 0; $i < $stats['products']; $i++) {
            $context->noteImported($this->legacyTable());
        }
    }
}
