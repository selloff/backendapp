<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->string('ref', 50)->nullable()->unique()->after('id');
            $table->string('buyer_agreement_token', 100)->nullable()->index()->after('status');
            $table->string('seller_agreement_token', 100)->nullable()->index()->after('buyer_agreement_token');
            $table->boolean('buyer_agreed')->default(false)->after('seller_agreement_token');
            $table->boolean('seller_agreed')->default(false)->after('buyer_agreed');
            $table->string('buyer_email')->nullable()->after('buyer_agreed');
            $table->string('seller_email')->nullable()->after('buyer_email');
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'ref',
                'buyer_agreement_token',
                'seller_agreement_token',
                'buyer_agreed',
                'seller_agreed',
                'buyer_email',
                'seller_email',
            ]);
        });
    }
};
