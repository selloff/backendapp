<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_listing_daily_metrics', function (Blueprint $table) {
            $table->unsignedInteger('impressions')->default(0)->after('contact_views');
            $table->unsignedInteger('chats')->default(0)->after('impressions');
        });

        Schema::table('vendor_listing_daily_metrics', function (Blueprint $table) {
            $table->unsignedInteger('impressions')->default(0)->after('contact_views');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listing_daily_metrics', function (Blueprint $table) {
            $table->dropColumn('impressions');
        });

        Schema::table('product_listing_daily_metrics', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'chats']);
        });
    }
};
