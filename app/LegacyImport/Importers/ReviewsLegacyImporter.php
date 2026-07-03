<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class ReviewsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'reviews';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('reviews')) {
            return;
        }

        foreach ($reader->rows('reviews') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'order_id' => $context->resolveId('orders', (int) ($row['order_id'] ?? 0)),
                'rating' => (int) ($row['rating'] ?? 0),
                'review' => $row['review'] ?? null,
                'is_approved' => LegacyValueCoercer::bool($row['is_approved'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_reviews')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'reviews', $legacyId, 'product_reviews', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
