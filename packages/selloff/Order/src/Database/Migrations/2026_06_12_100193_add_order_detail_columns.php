<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('price_vat', 13, 2)->default(0)->after('price_subtotal');
            $table->string('coupon_code', 255)->nullable()->after('price_total');
            $table->decimal('coupon_discount', 13, 2)->default(0)->after('coupon_code');
            $table->unsignedSmallInteger('coupon_discount_rate')->default(0)->after('coupon_discount');
            $table->decimal('transaction_fee', 13, 2)->default(0)->after('affiliate_data');
            $table->decimal('transaction_fee_rate', 8, 4)->nullable()->after('transaction_fee');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('product_vat_rate', 8, 4)->nullable()->after('product_vat');
            $table->decimal('seller_shipping_cost', 13, 2)->default(0)->after('product_vat_rate');
            $table->string('shipping_method', 255)->nullable()->after('order_status');
            $table->string('shipping_tracking_number', 255)->nullable()->after('shipping_method');
            $table->string('shipping_tracking_url', 500)->nullable()->after('shipping_tracking_number');
            $table->boolean('is_approved')->default(true)->after('shipping_tracking_url');
            $table->unsignedBigInteger('product_image_id')->nullable()->after('is_approved');
            $table->jsonb('product_image_data')->nullable()->after('product_image_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_vat_rate',
                'seller_shipping_cost',
                'shipping_method',
                'shipping_tracking_number',
                'shipping_tracking_url',
                'is_approved',
                'product_image_id',
                'product_image_data',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'price_vat',
                'coupon_code',
                'coupon_discount',
                'coupon_discount_rate',
                'transaction_fee',
                'transaction_fee_rate',
            ]);
        });
    }
};
