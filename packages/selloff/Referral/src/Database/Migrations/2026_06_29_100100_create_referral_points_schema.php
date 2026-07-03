<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('referral_profiles', 'referred_by_code')) {
                $table->string('referred_by_code', 40)->nullable()->after('referral_user_id');
            }
        });

        if (! Schema::hasTable('referral_point_transactions')) {
            Schema::create('referral_point_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 20);
                $table->unsignedInteger('points');
                $table->decimal('wallet_amount', 13, 2)->nullable();
                $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('description')->nullable();
                $table->timestampsTz();

                $table->unique(['type', 'referred_user_id'], 'referral_point_transactions_earn_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_point_transactions');

        Schema::table('referral_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('referral_profiles', 'referred_by_code')) {
                $table->dropColumn('referred_by_code');
            }
        });
    }
};
