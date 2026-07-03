<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_sales', function (Blueprint $table) {
            $table->string('product_title', 500)->nullable()->after('product_id');
            $table->decimal('price', 13, 2)->default(0)->after('purchase_code');
            $table->string('currency_code', 10)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('digital_sales', function (Blueprint $table) {
            $table->dropColumn(['product_title', 'price', 'currency_code']);
        });
    }
};
