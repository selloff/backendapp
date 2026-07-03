<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('coupon_code')->index();
                    $table->unsignedSmallInteger('discount_rate')->nullable();
                    $table->unsignedInteger('coupon_count')->nullable();
                    $table->decimal('minimum_order_amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('usage_type', 20)->default('single');
                    $table->jsonb('category_ids')->nullable();
                    $table->timestampTz('expires_at')->nullable();
                    $table->boolean('is_public')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('coupon_products', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->unique(['coupon_id', 'product_id']);
                });
        
                Schema::create('coupon_usages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('coupon_code');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('promotion_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_transactions');

        Schema::dropIfExists('coupon_usages');

        Schema::dropIfExists('coupon_products');

        Schema::dropIfExists('coupons');
    }
};
