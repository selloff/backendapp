<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('payment_id', 100)->nullable()->after('description');
            $table->string('currency_code', 10)->nullable()->after('payment_id');
        });

        Schema::table('wallet_deposits', function (Blueprint $table) {
            $table->string('checkout_token', 36)->nullable()->after('transaction_id');
            $table->string('ip_address', 100)->nullable()->after('checkout_token');
        });

        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->string('payment_reference', 255)->nullable()->after('payment_method');
            $table->string('currency_code', 10)->nullable()->after('amount');
            $table->string('checkout_token', 36)->nullable()->after('status');
            $table->string('ip_address', 100)->nullable()->after('checkout_token');
            $table->jsonb('metadata')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('membership_transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'currency_code', 'checkout_token', 'ip_address', 'metadata']);
        });

        Schema::table('wallet_deposits', function (Blueprint $table) {
            $table->dropColumn(['checkout_token', 'ip_address']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_id', 'currency_code']);
        });
    }
};
