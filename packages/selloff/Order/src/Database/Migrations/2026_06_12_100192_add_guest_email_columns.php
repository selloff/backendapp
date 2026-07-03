<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_email')->nullable()->after('buyer_id');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->string('guest_email')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn('guest_email');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('guest_email');
        });
    }
};
