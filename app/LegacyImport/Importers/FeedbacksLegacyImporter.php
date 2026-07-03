<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class FeedbacksLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'feedbacks';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('feedbacks')) {
            return;
        }

        foreach ($reader->rows('feedbacks') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $vendorId = $context->resolveId('users', (int) ($row['vendor_id'] ?? 0));
            if ($legacyId <= 0 || $vendorId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $feedbackType = LegacyValueCoercer::stringMax($row['feedback_type'] ?? null, 20);
            if (! in_array($feedbackType, ['positive', 'neutral', 'negative'], true)) {
                $feedbackType = 'neutral';
            }

            $payload = [
                'id' => $legacyId,
                'vendor_id' => $vendorId,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'rating' => isset($row['rating']) && $row['rating'] !== '' ? (int) $row['rating'] : null,
                'feedback_type' => $feedbackType,
                'title' => $row['title'] ?? null,
                'feedback' => $row['feedback'] ?? null,
                'status' => 'unread',
                'moderation_status' => 'approved',
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('feedbacks')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'feedbacks', $legacyId, 'feedbacks', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
