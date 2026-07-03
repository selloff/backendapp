<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('slug')->nullable()->unique()->after('last_name');
            $table->string('avatar')->nullable()->after('email');
            $table->boolean('is_enable_login')->default(true)->after('password');
            $table->boolean('is_disable')->default(false)->after('is_enable_login');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'slug',
                'avatar',
                'is_enable_login',
                'is_disable',
            ]);
        });
    }
};
