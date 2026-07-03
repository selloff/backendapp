<?php

use App\LegacyImport\Sync\LegacyProductCommentsSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyProductCommentsSync::class)->sync();
    }

    public function down(): void
    {
        // Re-sync migration; rollback is a no-op.
    }
};
