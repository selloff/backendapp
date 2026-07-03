<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->string('transaction_number')->nullable()->index();
                    $table->string('payment_method', 100)->nullable();
                    $table->string('payment_status', 50)->nullable();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->decimal('exchange_rate', 13, 6)->default(1);
                    $table->jsonb('metadata')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('wallet_deposits', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->string('payment_method', 100)->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->string('transaction_id')->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('wallet_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('type', 30);
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->decimal('balance_after', 13, 2)->default(0);
                    $table->string('description')->nullable();
                    $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('bank_transfer_requests', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('order_number')->nullable()->index();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('payment_note', 500)->nullable();
                    $table->string('receipt_path')->nullable();
                    $table->string('status', 20)->default('pending');
                    $table->string('ip_address', 50)->nullable();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('tax_rules', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->decimal('rate', 8, 4)->default(0);
                    $table->unsignedBigInteger('country_id')->nullable()->index();
                    $table->unsignedBigInteger('state_id')->nullable()->index();
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('membership_plans', function (Blueprint $table) {
                    $table->id();
                    $table->string('title');
                    $table->text('description')->nullable();
                    $table->decimal('price', 13, 2)->default(0);
                    $table->string('currency_code', 10)->nullable();
                    $table->unsignedInteger('duration_days')->default(30);
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('membership_transactions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans')->nullOnDelete();
                    $table->decimal('amount', 13, 2)->default(0);
                    $table->string('payment_method', 100)->nullable();
                    $table->string('status', 30)->default('pending');
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('user_membership_plans', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
                    $table->timestampTz('starts_at')->nullable();
                    $table->timestampTz('expires_at')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_membership_plans');

        Schema::dropIfExists('membership_transactions');

        Schema::dropIfExists('membership_plans');

        Schema::dropIfExists('tax_rules');

        Schema::dropIfExists('bank_transfer_requests');

        Schema::dropIfExists('wallet_transactions');

        Schema::dropIfExists('wallet_deposits');

        Schema::dropIfExists('payment_transactions');
    }
};
