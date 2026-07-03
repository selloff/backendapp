<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook_id')->nullable()->after('avatar');
            $table->string('google_id')->nullable()->after('facebook_id');
            $table->string('vk_id')->nullable()->after('google_id');
            $table->string('storage_avatar')->default('local')->after('vk_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['facebook_id', 'google_id', 'vk_id', 'storage_avatar']);
        });
    }
};
