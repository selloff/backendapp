<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('order_number')->nullable()->after('order_id');
            $table->jsonb('client_snapshot')->nullable()->after('currency_code');
            $table->jsonb('line_items')->nullable()->after('client_snapshot');
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
            $table->unsignedBigInteger('order_number')->nullable()->after('order_item_id');
            $table->boolean('is_completed')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn(['order_number', 'is_completed']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'client_snapshot', 'line_items']);
        });
    }
};
