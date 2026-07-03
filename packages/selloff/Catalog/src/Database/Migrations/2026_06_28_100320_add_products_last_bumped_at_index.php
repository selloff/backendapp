<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'last_bumped_at')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->index('last_bumped_at', 'products_last_bumped_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_last_bumped_at_index');
        });
    }
};
