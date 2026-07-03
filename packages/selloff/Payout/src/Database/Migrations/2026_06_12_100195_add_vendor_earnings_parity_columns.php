<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
            $table->decimal('sale_amount', 13, 2)->default(0)->after('earned_amount');
            $table->decimal('vat_rate', 8, 4)->nullable()->after('sale_amount');
            $table->decimal('vat_amount', 13, 2)->default(0)->after('vat_rate');
            $table->decimal('commission_amount', 13, 2)->default(0)->after('commission_rate');
            $table->decimal('coupon_discount', 13, 2)->default(0)->after('commission_amount');
            $table->decimal('shipping_cost', 13, 2)->default(0)->after('coupon_discount');
            $table->boolean('is_refunded')->default(false)->after('shipping_cost');
            $table->jsonb('affiliate_data')->nullable()->after('is_refunded');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn([
                'sale_amount',
                'vat_rate',
                'vat_amount',
                'commission_amount',
                'coupon_discount',
                'shipping_cost',
                'is_refunded',
                'affiliate_data',
            ]);
        });
    }
};
