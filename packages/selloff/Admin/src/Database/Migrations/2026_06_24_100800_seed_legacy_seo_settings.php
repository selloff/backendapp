<?php

use App\LegacyImport\Sync\LegacySeoSettingsSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacySeoSettingsSync::class)->sync();
    }

    public function down(): void
    {
        // Idempotent SEO settings seed; no destructive rollback.
    }
};
