<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('vendor_affiliate_status')->default(0)->after('affiliate_discount_rate');
        });
    }

    public function down(): void
    {
        Schema::table('referral_profiles', function (Blueprint $table) {
            $table->dropColumn('vendor_affiliate_status');
        });
    }
};
