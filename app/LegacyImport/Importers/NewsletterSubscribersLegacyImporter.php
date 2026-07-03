<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class NewsletterSubscribersLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'subscribers';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('subscribers')) {
            return;
        }

        foreach ($reader->rows('subscribers') as $row) {
            $context->notePlanned('subscribers');

            $legacyId = (int) ($row['id'] ?? 0);
            $email = LegacyValueCoercer::stringMax($row['email'] ?? '', 255, '');

            if ($legacyId <= 0 || $email === '') {
                $context->noteSkipped('subscribers');

                continue;
            }

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();
            $payload = [
                'email' => $email,
                'token' => LegacyValueCoercer::stringMax($row['token'] ?? null, 255),
                'is_active' => true,
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                $existing = DB::table('newsletter_subscribers')->where('legacy_id', $legacyId)->first()
                    ?? DB::table('newsletter_subscribers')->where('email', $email)->first();

                if ($existing) {
                    DB::table('newsletter_subscribers')->where('id', $existing->id)->update($payload);
                    $newId = (int) $existing->id;
                } else {
                    $newId = (int) DB::table('newsletter_subscribers')->insertGetId($payload);
                }

                $this->maps->remember($context, 'subscribers', $legacyId, 'newsletter_subscribers', $newId);
            }

            $context->noteImported('subscribers');
        }
    }
}
