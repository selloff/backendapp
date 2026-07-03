<?php

use App\LegacyImport\Sync\LegacyBlogCommentsSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyBlogCommentsSync::class)->sync();
    }

    public function down(): void
    {
        // Legacy comments are re-synced idempotently; no destructive rollback.
    }
};
