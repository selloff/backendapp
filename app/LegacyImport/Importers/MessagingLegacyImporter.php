<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class MessagingLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['chat', 'chat_messages'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importConversations($context, $reader);
        $this->importMessages($context, $reader);
    }

    private function importConversations(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('chat') || ! $reader->hasTable('chat')) {
            return;
        }

        foreach ($reader->rows('chat') as $row) {
            $context->notePlanned('chat');

            $legacyId = (int) ($row['id'] ?? 0);
            $senderId = $context->resolveId('users', (int) ($row['sender_id'] ?? 0));
            $receiverId = $context->resolveId('users', (int) ($row['receiver_id'] ?? 0));

            if ($legacyId <= 0 || $senderId === null || $receiverId === null) {
                $context->noteSkipped('chat');

                continue;
            }

            $legacyProductId = (int) ($row['product_id'] ?? 0);
            $productId = $legacyProductId > 0
                ? $context->resolveId('products', $legacyProductId)
                : null;

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();
            $updatedAt = LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? $createdAt;

            $payload = [
                'id' => $legacyId,
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'subject' => $row['subject'] ?? null,
                'product_id' => $productId,
                'last_message_at' => $updatedAt,
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            if (! $context->dryRun) {
                DB::table('conversations')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'chat', $legacyId, 'conversations', $legacyId);
            $context->noteImported('chat');
        }
    }

    private function importMessages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('chat_messages') || ! $reader->hasTable('chat_messages')) {
            return;
        }

        foreach ($reader->rows('chat_messages') as $row) {
            $context->notePlanned('chat_messages');

            $legacyId = (int) ($row['id'] ?? 0);
            $conversationId = $context->resolveId('chat', (int) ($row['chat_id'] ?? 0));
            $senderId = $context->resolveId('users', (int) ($row['sender_id'] ?? 0));
            $receiverId = $context->resolveId('users', (int) ($row['receiver_id'] ?? 0));

            if ($legacyId <= 0 || $conversationId === null || $senderId === null || $receiverId === null) {
                $context->noteSkipped('chat_messages');

                continue;
            }

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'conversation_id' => $conversationId,
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => $row['message'] ?? null,
                'is_read' => LegacyValueCoercer::bool($row['is_read'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('messages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'chat_messages', $legacyId, 'messages', $legacyId);
            $context->noteImported('chat_messages');
        }
    }
}
