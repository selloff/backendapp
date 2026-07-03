<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_transactions', function (Blueprint $table) {
            $table->string('payment_method', 100)->nullable()->after('product_id');
            $table->string('payment_reference', 255)->nullable()->after('payment_method');
            $table->string('checkout_token', 36)->nullable()->after('status');
            $table->string('ip_address', 100)->nullable()->after('checkout_token');
            $table->string('purchased_plan', 255)->nullable()->after('ip_address');
            $table->unsignedInteger('day_count')->nullable()->after('purchased_plan');
            $table->jsonb('metadata')->nullable()->after('day_count');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_reference',
                'checkout_token',
                'ip_address',
                'purchased_plan',
                'day_count',
                'metadata',
            ]);
        });
    }
};
