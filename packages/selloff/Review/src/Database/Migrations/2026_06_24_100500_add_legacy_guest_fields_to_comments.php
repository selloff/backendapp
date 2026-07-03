<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (! Schema::hasColumn('comments', 'name')) {
                $table->string('name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('comments', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (! Schema::hasColumn('comments', 'ip_address')) {
                $table->string('ip_address', 50)->nullable()->after('comment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            foreach (['name', 'email', 'ip_address'] as $column) {
                if (Schema::hasColumn('comments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
