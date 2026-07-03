<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('delivery_time_options')) {
            return;
        }

        if (! Schema::hasColumn('products', 'delivery_time_option_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign('delivery_time_option_id')
                ->references('id')
                ->on('delivery_time_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'delivery_time_option_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['delivery_time_option_id']);
        });
    }
};
