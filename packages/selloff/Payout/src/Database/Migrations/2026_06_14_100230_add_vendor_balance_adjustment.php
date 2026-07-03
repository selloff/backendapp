<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'vendor_balance_adjustment')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->decimal('vendor_balance_adjustment', 14, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'vendor_balance_adjustment')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('vendor_balance_adjustment');
            });
        }
    }
};
