<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'is_edited')) {
                $table->boolean('is_edited')->default(false);
            }
            if (! Schema::hasColumn('products', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false);
            }
            if (! Schema::hasColumn('products', 'is_draft')) {
                $table->boolean('is_draft')->default(false);
            }
            if (! Schema::hasColumn('products', 'promote_plan')) {
                $table->string('promote_plan', 100)->nullable();
            }
            if (! Schema::hasColumn('products', 'promoted_at')) {
                $table->timestampTz('promoted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach (['is_edited', 'is_deleted', 'is_draft', 'promote_plan', 'promoted_at'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
