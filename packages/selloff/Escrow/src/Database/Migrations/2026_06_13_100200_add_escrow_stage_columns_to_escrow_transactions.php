<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->decimal('delivery_cost', 13, 2)->default(0)->after('seller_amount');
            $table->text('delivery_address')->nullable()->after('delivery_cost');
            $table->boolean('payment_link_sent')->default(false)->after('delivery_address');
            $table->boolean('payment_received')->default(false)->after('payment_link_sent');
            $table->boolean('seller_shipped_item')->default(false)->after('payment_received');
            $table->boolean('buyer_confirmed_item_delivery')->default(false)->after('seller_shipped_item');
            $table->boolean('seller_received_payment')->default(false)->after('buyer_confirmed_item_delivery');
            $table->boolean('transaction_complete')->default(false)->after('seller_received_payment');
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_cost',
                'delivery_address',
                'payment_link_sent',
                'payment_received',
                'seller_shipped_item',
                'buyer_confirmed_item_delivery',
                'seller_received_payment',
                'transaction_complete',
            ]);
        });
    }
};
