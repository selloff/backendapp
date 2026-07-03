<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;

class WishlistLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'wishlist';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('wishlist')) {
            return;
        }

        $index = 0;

        foreach ($reader->rows('wishlist') as $row) {
            $context->notePlanned($this->legacyTable());

            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($userId === null || $productId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $legacyId = isset($row['id']) && (int) $row['id'] > 0 ? (int) $row['id'] : ++$index;

            $payload = [
                'user_id' => $userId,
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $context->dryRun) {
                DB::table('wishlists')->updateOrInsert(
                    ['user_id' => $userId, 'product_id' => $productId],
                    $payload,
                );
            }

            $newId = $context->dryRun
                ? $legacyId
                : (int) DB::table('wishlists')->where('user_id', $userId)->where('product_id', $productId)->value('id');

            $this->maps->remember($context, 'wishlist', $legacyId, 'wishlists', $newId);
            $context->noteImported($this->legacyTable());
        }
    }
}
