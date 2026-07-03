<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class SupportLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return [
            'knowledge_base_categories',
            'knowledge_base',
            'support_tickets',
            'support_subtickets',
            'contacts',
        ];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importKnowledgeBaseCategories($context, $reader);
        $this->importKnowledgeBaseArticles($context, $reader);
        $this->importSupportTickets($context, $reader);
        $this->importSupportMessages($context, $reader);
        $this->importContacts($context, $reader);
    }

    private function importKnowledgeBaseCategories(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('knowledge_base_categories') || ! $reader->hasTable('knowledge_base_categories')) {
            return;
        }

        foreach ($reader->rows('knowledge_base_categories') as $row) {
            $context->notePlanned('knowledge_base_categories');

            $legacyId = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($legacyId <= 0 || $name === '') {
                $context->noteSkipped('knowledge_base_categories');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'name' => $name,
                'sort_order' => (int) ($row['category_order'] ?? 0),
                'is_active' => true,
                'legacy_id' => $legacyId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $context->dryRun) {
                DB::table('knowledge_base_categories')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'knowledge_base_categories', $legacyId, 'knowledge_base_categories', $legacyId);
            $context->noteImported('knowledge_base_categories');
        }
    }

    private function importKnowledgeBaseArticles(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('knowledge_base') || ! $reader->hasTable('knowledge_base')) {
            return;
        }

        foreach ($reader->rows('knowledge_base') as $row) {
            $context->notePlanned('knowledge_base');

            $legacyId = (int) ($row['id'] ?? 0);
            $title = trim((string) ($row['title'] ?? ''));
            if ($legacyId <= 0 || $title === '') {
                $context->noteSkipped('knowledge_base');

                continue;
            }

            $legacyCategoryId = (int) ($row['category_id'] ?? 0);
            $categoryId = $legacyCategoryId > 0
                ? $context->resolveId('knowledge_base_categories', $legacyCategoryId)
                : null;

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'knowledge_base_category_id' => $categoryId,
                'slug' => $row['slug'] ?? null,
                'title' => $title,
                'content' => $row['content'] ?? null,
                'is_active' => true,
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('knowledge_base_articles')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'knowledge_base', $legacyId, 'knowledge_base_articles', $legacyId);
            $context->noteImported('knowledge_base');
        }
    }

    private function importSupportTickets(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('support_tickets') || ! $reader->hasTable('support_tickets')) {
            return;
        }

        foreach ($reader->rows('support_tickets') as $row) {
            $context->notePlanned('support_tickets');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('support_tickets');

                continue;
            }

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = $legacyUserId > 0 ? $context->resolveId('users', $legacyUserId) : null;
            $subject = trim((string) ($row['subject'] ?? ''));
            if ($subject === '') {
                $subject = 'Support request';
            }

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();
            $updatedAt = LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? $createdAt;

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'subject' => $subject,
                'status' => $this->mapTicketStatus((int) ($row['status'] ?? 1)),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            if (! $context->dryRun) {
                DB::table('support_tickets')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'support_tickets', $legacyId, 'support_tickets', $legacyId);
            $context->noteImported('support_tickets');
        }
    }

    private function importSupportMessages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('support_subtickets') || ! $reader->hasTable('support_subtickets')) {
            return;
        }

        foreach ($reader->rows('support_subtickets') as $row) {
            $context->notePlanned('support_subtickets');

            $legacyId = (int) ($row['id'] ?? 0);
            $ticketId = $context->resolveId('support_tickets', (int) ($row['ticket_id'] ?? 0));
            if ($legacyId <= 0 || $ticketId === null) {
                $context->noteSkipped('support_subtickets');

                continue;
            }

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = $legacyUserId > 0 ? $context->resolveId('users', $legacyUserId) : null;
            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'support_ticket_id' => $ticketId,
                'user_id' => $userId,
                'message' => $row['message'] ?? null,
                'is_admin' => LegacyValueCoercer::bool($row['is_support_reply'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('support_messages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'support_subtickets', $legacyId, 'support_messages', $legacyId);
            $context->noteImported('support_subtickets');
        }
    }

    private function importContacts(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('contacts') || ! $reader->hasTable('contacts')) {
            return;
        }

        foreach ($reader->rows('contacts') as $row) {
            $context->notePlanned('contacts');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('contacts');

                continue;
            }

            $message = trim((string) ($row['message'] ?? ''));
            $subject = $this->contactSubject($message);
            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'subject' => $subject,
                'message' => $message !== '' ? $message : null,
                'status' => 'pending',
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('contact_messages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'contacts', $legacyId, 'contact_messages', $legacyId);
            $context->noteImported('contacts');
        }
    }

    private function mapTicketStatus(int $status): string
    {
        return match ($status) {
            3 => 'closed',
            2 => 'pending',
            default => 'open',
        };
    }

    private function contactSubject(string $message): string
    {
        if ($message === '') {
            return 'Contact form submission';
        }

        $line = strtok($message, "\r\n");
        if ($line === false || trim($line) === '') {
            return 'Contact form submission';
        }

        return mb_substr(trim($line), 0, 255);
    }
}
