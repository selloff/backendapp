<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            if (! Schema::hasColumn('tags', 'lang_id')) {
                $table->unsignedBigInteger('lang_id')->nullable()->after('tag');
                $table->index('lang_id');
            }
        });

        DB::table('tags')->whereNull('lang_id')->update(['lang_id' => 1]);

        Schema::table('tags', function (Blueprint $table) {
            if ($this->hasUniqueIndex('tags', 'tags_tag_unique')) {
                $table->dropUnique(['tag']);
            }

            if (! $this->hasUniqueIndex('tags', 'tags_tag_lang_id_unique')) {
                $table->unique(['tag', 'lang_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            if ($this->hasUniqueIndex('tags', 'tags_tag_lang_id_unique')) {
                $table->dropUnique(['tag', 'lang_id']);
            }

            if (! $this->hasUniqueIndex('tags', 'tags_tag_unique')) {
                $table->unique('tag');
            }

            if (Schema::hasColumn('tags', 'lang_id')) {
                $table->dropColumn('lang_id');
            }
        });
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
