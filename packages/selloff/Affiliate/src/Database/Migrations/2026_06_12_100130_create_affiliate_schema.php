<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_links', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('referrer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->unsignedBigInteger('language_id')->nullable()->index();
                    $table->string('link_short', 100)->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('affiliate_earnings', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('referrer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->decimal('commission_rate', 8, 4)->nullable();
                    $table->decimal('earned_amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_earnings');

        Schema::dropIfExists('affiliate_links');
    }
};
