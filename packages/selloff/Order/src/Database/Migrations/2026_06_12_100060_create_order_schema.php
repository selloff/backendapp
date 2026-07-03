<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('session_id', 128)->nullable()->index();
                    $table->uuid('checkout_token')->nullable()->unique();
                    $table->string('checkout_type', 20)->default('product');
                    $table->string('payment_method', 100)->nullable();
                    $table->decimal('subtotal', 13, 2)->nullable();
                    $table->decimal('shipping_cost', 13, 2)->default(0);
                    $table->decimal('grand_total', 13, 2)->nullable();
                    $table->decimal('grand_total_base', 13, 2)->nullable();
                    $table->jsonb('cart_totals_data')->nullable();
                    $table->string('currency_code', 10)->nullable();
                    $table->string('currency_code_base', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->jsonb('shipping_data')->nullable();
                    $table->jsonb('shipping_cost_data')->nullable();
                    $table->string('coupon_code', 50)->nullable();
                    $table->string('service_type', 30)->nullable();
                    $table->jsonb('service_data')->nullable();
                    $table->jsonb('service_tax_data')->nullable();
                    $table->boolean('has_physical_product')->default(false);
                    $table->boolean('has_digital_product')->default(false);
                    $table->string('transaction_number', 50)->nullable();
                    $table->string('status', 50)->default('active');
                    $table->string('payment_url', 1000)->nullable();
                    $table->timestampTz('expires_at')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('checkout_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->cascadeOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('product_type', 20)->default('physical');
                    $table->string('listing_type', 20)->nullable();
                    $table->string('product_title', 500)->nullable();
                    $table->string('product_sku')->nullable();
                    $table->unsignedInteger('quantity')->default(1);
                    $table->decimal('unit_price', 13, 2)->default(0);
                    $table->decimal('unit_price_base', 13, 2)->nullable();
                    $table->decimal('total_price', 13, 2)->default(0);
                    $table->decimal('product_vat', 13, 2)->default(0);
                    $table->decimal('product_vat_rate', 8, 4)->nullable();
                    $table->unsignedBigInteger('product_image_id')->nullable();
                    $table->jsonb('product_image_data')->nullable();
                    $table->unsignedBigInteger('quote_request_id')->nullable();
                    $table->jsonb('product_options_snapshot')->nullable();
                    $table->text('product_options_summary')->nullable();
                    $table->jsonb('extra_options')->nullable();
                    $table->decimal('product_commission_rate', 8, 4)->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('orders', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->unsignedBigInteger('order_number')->unique();
                    $table->decimal('price_subtotal', 13, 2)->default(0);
                    $table->decimal('price_shipping', 13, 2)->default(0);
                    $table->decimal('price_total', 13, 2)->default(0);
                    $table->decimal('price_total_base', 13, 2)->nullable();
                    $table->string('currency_code', 10)->nullable();
                    $table->string('currency_code_base', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->string('status', 50)->default('pending');
                    $table->string('payment_method', 100)->nullable();
                    $table->string('payment_status', 50)->nullable();
                    $table->string('transaction_id')->nullable();
                    $table->uuid('checkout_token')->nullable()->index();
                    $table->jsonb('shipping_snapshot')->nullable();
                    $table->jsonb('global_taxes_data')->nullable();
                    $table->jsonb('affiliate_data')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                    $table->timestampsTz();
                    $table->index(['buyer_id', 'status']);
                });
        
                Schema::create('order_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('product_type', 20)->default('physical');
                    $table->string('product_title', 500)->nullable();
                    $table->string('product_sku')->nullable();
                    $table->unsignedInteger('quantity')->default(1);
                    $table->decimal('unit_price', 13, 2)->default(0);
                    $table->decimal('total_price', 13, 2)->default(0);
                    $table->decimal('product_vat', 13, 2)->default(0);
                    $table->jsonb('product_options_snapshot')->nullable();
                    $table->text('product_options_summary')->nullable();
                    $table->decimal('commission_rate', 8, 4)->nullable();
                    $table->string('order_status', 50)->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('invoices', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('invoice_number')->unique();
                    $table->decimal('total_amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('refund_requests', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->text('description')->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('refund_messages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('refund_request_id')->constrained('refund_requests')->cascadeOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->text('message')->nullable();
                    $table->boolean('is_admin')->default(false);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('quote_requests', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->decimal('quoted_price', 13, 2)->nullable();
                    $table->unsignedInteger('quantity')->default(1);
                    $table->string('status', 30)->default('pending');
                    $table->text('message')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('digital_sales', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('license_key')->nullable();
                    $table->string('purchase_code')->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_sales');

        Schema::dropIfExists('quote_requests');

        Schema::dropIfExists('refund_messages');

        Schema::dropIfExists('refund_requests');

        Schema::dropIfExists('invoices');

        Schema::dropIfExists('order_items');

        Schema::dropIfExists('orders');

        Schema::dropIfExists('checkout_items');

        Schema::dropIfExists('checkout_sessions');
    }
};
