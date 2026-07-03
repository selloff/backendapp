<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_rss_feeds')->default(false)->after('about_me');
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->boolean('is_fixed_vat')->default(false)->after('vat_rates_data');
            $table->decimal('fixed_vat_rate', 5, 2)->nullable()->after('is_fixed_vat');
            $table->jsonb('vat_rates_by_state')->nullable()->after('fixed_vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_fixed_vat', 'fixed_vat_rate', 'vat_rates_by_state']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_rss_feeds');
        });
    }
};
