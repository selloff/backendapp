<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->decimal('commission_amount', 13, 2)->default(0);
                    $table->decimal('seller_amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('status', 50)->default('pending');
                    $table->jsonb('metadata')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_transactions');
    }
};
