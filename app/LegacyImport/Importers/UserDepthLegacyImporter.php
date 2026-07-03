<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class UserDepthLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'user_login_activities';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('user_login_activities')) {
            return;
        }

        foreach ($reader->rows('user_login_activities') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $userId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $loginAt = LegacyValueCoercer::date($row['login_at'] ?? $row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'ip_address' => $row['ip_address'] ?? null,
                'user_agent' => $row['user_agent'] ?? null,
                'login_at' => $loginAt,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? $loginAt) ?? $loginAt,
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? $loginAt) ?? $loginAt,
            ];

            if (! $context->dryRun) {
                DB::table('login_activities')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'user_login_activities', $legacyId, 'login_activities', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
