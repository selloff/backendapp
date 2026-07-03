<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'reject_reason')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('reject_reason', 1000)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'reject_reason')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('reject_reason');
        });
    }
};
