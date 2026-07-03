<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'lang_id')) {
                $table->unsignedTinyInteger('lang_id')->default(1)->after('locale');
            }
            if (! Schema::hasColumn('pages', 'page_order')) {
                $table->unsignedInteger('page_order')->default(1)->after('is_custom');
            }
            if (! Schema::hasColumn('pages', 'description')) {
                $table->string('description', 500)->nullable()->after('title');
            }
            if (! Schema::hasColumn('pages', 'keywords')) {
                $table->string('keywords', 500)->nullable()->after('description');
            }
            if (! Schema::hasColumn('pages', 'title_active')) {
                $table->boolean('title_active')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('pages', 'page_default_name')) {
                $table->string('page_default_name', 255)->nullable()->after('is_custom');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table): void {
            foreach (['lang_id', 'page_order', 'description', 'keywords', 'title_active', 'page_default_name'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
