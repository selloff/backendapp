<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
                    $table->string('username')->nullable()->unique();
                    $table->decimal('wallet_balance', 13, 2)->default(0);
                    $table->boolean('is_banned')->default(false);
                    $table->string('phone_number')->nullable();
                    $table->text('about_me')->nullable();
                    $table->timestampTz('last_seen_at')->nullable();
                });
        
                Schema::create('vendor_profiles', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                    $table->string('shop_name')->nullable();
                    $table->string('slug')->nullable()->unique();
                    $table->string('cover_path')->nullable();
                    $table->boolean('is_verified_seller')->default(false);
                    $table->decimal('commission_rate', 13, 2)->default(0);
                    $table->boolean('vacation_mode')->default(false);
                    $table->text('vacation_message')->nullable();
                    $table->jsonb('payout_info')->nullable();
                    $table->jsonb('vat_rates_data')->nullable();
                    $table->jsonb('social_media_data')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('referral_profiles', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                    $table->string('referral_code')->unique();
                    $table->foreignId('referral_user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->unsignedInteger('referral_points')->default(0);
                    $table->unsignedInteger('referral_point_balance')->default(0);
                    $table->decimal('affiliate_commission_rate', 13, 2)->default(0);
                    $table->decimal('affiliate_discount_rate', 13, 2)->default(0);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('shipping_addresses', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('title')->nullable();
                    $table->string('first_name')->nullable();
                    $table->string('last_name')->nullable();
                    $table->string('email')->nullable();
                    $table->string('phone_number')->nullable();
                    $table->string('address')->nullable();
                    $table->string('address_2')->nullable();
                    $table->string('zip_code')->nullable();
                    $table->unsignedBigInteger('country_id')->nullable()->index();
                    $table->unsignedBigInteger('state_id')->nullable()->index();
                    $table->unsignedBigInteger('city_id')->nullable()->index();
                    $table->boolean('is_default')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('followers', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
                    $table->timestampsTz();
                    $table->unique(['user_id', 'follower_id']);
                });
        
                Schema::create('login_activities', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('ip_address', 45)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->timestampTz('login_at');
                    $table->timestampsTz();
                    $table->index(['user_id', 'login_at']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');

        Schema::dropIfExists('followers');

        Schema::dropIfExists('shipping_addresses');

        Schema::dropIfExists('referral_profiles');

        Schema::dropIfExists('vendor_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'wallet_balance', 'is_banned', 'phone_number', 'about_me', 'last_seen_at']);
        });
    }
};
