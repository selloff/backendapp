<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'vat_rate')) {
                $table->decimal('vat_rate', 8, 4)->nullable()->after('price_discounted');
            }
            if (! Schema::hasColumn('products', 'is_free_product')) {
                $table->boolean('is_free_product')->default(false)->after('vat_rate');
            }
            if (! Schema::hasColumn('products', 'delivery_time_option_id')) {
                $table->unsignedBigInteger('delivery_time_option_id')
                    ->nullable()
                    ->after('shipping_dimensions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'delivery_time_option_id')) {
                $table->dropColumn('delivery_time_option_id');
            }
            if (Schema::hasColumn('products', 'is_free_product')) {
                $table->dropColumn('is_free_product');
            }
            if (Schema::hasColumn('products', 'vat_rate')) {
                $table->dropColumn('vat_rate');
            }
        });
    }
};
