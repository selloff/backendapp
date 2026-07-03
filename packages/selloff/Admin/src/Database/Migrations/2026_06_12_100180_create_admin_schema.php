<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // currencies, languages, and language_translations are created in Core
        // (2026_06_12_100005_create_currencies_languages_schema) so ETL lookup
        // imports run after selloff:migrate without waiting for the Admin module.
    }

    public function down(): void
    {
        // Owned by Core migration 2026_06_12_100005.
    }
};
