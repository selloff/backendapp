<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->foreignId('escrow_transaction_id')
                ->nullable()
                ->after('order_item_id')
                ->constrained('escrow_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_earnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escrow_transaction_id');
        });
    }
};
