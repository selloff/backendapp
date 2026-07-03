<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_zones') && ! Schema::hasColumn('shipping_zones', 'estimated_delivery')) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->text('estimated_delivery')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('delivery_time_options')) {
            Schema::table('delivery_time_options', function (Blueprint $table): void {
                if (! Schema::hasColumn('delivery_time_options', 'seller_id')) {
                    $table->foreignId('seller_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('delivery_time_options', 'label')) {
                    $table->string('label')->nullable()->after('seller_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_time_options')) {
            Schema::table('delivery_time_options', function (Blueprint $table): void {
                if (Schema::hasColumn('delivery_time_options', 'seller_id')) {
                    $table->dropConstrainedForeignId('seller_id');
                }
                if (Schema::hasColumn('delivery_time_options', 'label')) {
                    $table->dropColumn('label');
                }
            });
        }

        if (Schema::hasTable('shipping_zones') && Schema::hasColumn('shipping_zones', 'estimated_delivery')) {
            Schema::table('shipping_zones', function (Blueprint $table): void {
                $table->dropColumn('estimated_delivery');
            });
        }
    }
};
