<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('session_id', 128)->nullable()->index();
                    $table->string('currency_code', 10)->nullable();
                    $table->string('currency_code_base', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->jsonb('shipping_data')->nullable();
                    $table->decimal('shipping_cost', 13, 2)->default(0);
                    $table->jsonb('shipping_cost_data')->nullable();
                    $table->string('coupon_code', 50)->nullable();
                    $table->string('payment_method', 100)->nullable();
                    $table->unsignedBigInteger('country_id')->nullable()->index();
                    $table->unsignedBigInteger('state_id')->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('cart_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
                    $table->string('item_hash', 64)->nullable()->index();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('product_type', 20)->default('physical');
                    $table->string('listing_type', 20)->nullable();
                    $table->string('product_title', 500)->nullable();
                    $table->string('product_sku')->nullable();
                    $table->unsignedInteger('quantity')->default(1);
                    $table->decimal('unit_price', 13, 2)->default(0);
                    $table->decimal('unit_price_base', 13, 2)->default(0);
                    $table->decimal('total_price', 13, 2)->default(0);
                    $table->decimal('product_vat', 13, 2)->default(0);
                    $table->decimal('product_vat_rate', 8, 4)->nullable();
                    $table->unsignedBigInteger('product_image_id')->nullable();
                    $table->jsonb('product_image_data')->nullable();
                    $table->string('purchase_type', 50)->nullable();
                    $table->unsignedBigInteger('quote_request_id')->nullable();
                    $table->string('variant_hash', 64)->nullable();
                    $table->jsonb('extra_options')->nullable();
                    $table->jsonb('product_options_snapshot')->nullable();
                    $table->text('product_options_summary')->nullable();
                    $table->boolean('is_stock_available')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                    $table->index(['cart_id', 'product_id']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');

        Schema::dropIfExists('carts');
    }
};
