<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abuse_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('abuse_reports', 'item_id')) {
                $table->unsignedBigInteger('item_id')->nullable()->after('user_id');
                $table->index('item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('abuse_reports', function (Blueprint $table) {
            if (Schema::hasColumn('abuse_reports', 'item_id')) {
                $table->dropIndex(['item_id']);
                $table->dropColumn('item_id');
            }
        });
    }
};
