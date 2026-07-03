<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_earnings', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->decimal('earned_amount', 13, 2)->default(0);
                    $table->decimal('commission_rate', 8, 4)->nullable();
                    $table->string('currency_code', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('payout_requests', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->jsonb('payout_info')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');

        Schema::dropIfExists('vendor_earnings');
    }
};
