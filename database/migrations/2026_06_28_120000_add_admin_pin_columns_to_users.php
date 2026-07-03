<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_pin_hash')->nullable()->after('password');
            $table->timestampTz('admin_pin_set_at')->nullable()->after('admin_pin_hash');
            $table->timestampTz('admin_pin_revoked_at')->nullable()->after('admin_pin_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_pin_hash', 'admin_pin_set_at', 'admin_pin_revoked_at']);
        });
    }
};
