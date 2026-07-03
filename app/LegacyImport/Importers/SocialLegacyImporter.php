<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyForeignKeyResolver;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class SocialLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['followers', 'comments'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importFollowers($context, $reader);
        $this->importComments($context, $reader);
    }

    private function importFollowers(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('followers') || ! $reader->hasTable('followers')) {
            return;
        }

        foreach ($reader->rows('followers') as $row) {
            $context->notePlanned('followers');

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['following_id'] ?? 0));
            $followerId = $context->resolveId('users', (int) ($row['follower_id'] ?? 0));

            if ($legacyId <= 0 || $userId === null || $followerId === null || $userId === $followerId) {
                $context->noteSkipped('followers');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'follower_id' => $followerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $context->dryRun) {
                DB::table('followers')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'followers', $legacyId, 'followers', $legacyId);
            $context->noteImported('followers');
        }
    }

    private function importComments(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('comments') || ! $reader->hasTable('comments')) {
            return;
        }

        $rows = iterator_to_array($reader->rows('comments'));
        usort($rows, fn (array $a, array $b): int => (int) ($a['parent_id'] ?? 0) <=> (int) ($b['parent_id'] ?? 0));

        foreach ($rows as $row) {
            $context->notePlanned('comments');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = LegacyForeignKeyResolver::productId($context, (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('comments');

                continue;
            }

            $legacyParentId = (int) ($row['parent_id'] ?? 0);
            $parentId = $legacyParentId > 0
                ? LegacyForeignKeyResolver::commentId($context, $legacyParentId)
                : null;

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = LegacyForeignKeyResolver::userId($context, $legacyUserId);

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'comment' => $row['comment'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'is_approved' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('comments')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'comments', $legacyId, 'comments', $legacyId);
            $context->noteImported('comments');
        }
    }
}
