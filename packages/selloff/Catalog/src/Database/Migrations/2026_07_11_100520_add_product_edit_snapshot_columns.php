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
            if (! Schema::hasColumn('products', 'approved_snapshot')) {
                $table->jsonb('approved_snapshot')->nullable();
            }
            if (! Schema::hasColumn('products', 'pending_changes')) {
                $table->jsonb('pending_changes')->nullable();
            }
            if (! Schema::hasColumn('products', 'pending_submitted_at')) {
                $table->timestampTz('pending_submitted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach (['approved_snapshot', 'pending_changes', 'pending_submitted_at'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
