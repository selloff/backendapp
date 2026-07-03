<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('shop_opening_status')->default(0)->after('last_seen_at');
            $table->jsonb('vendor_documents')->nullable()->after('shop_opening_status');
            $table->timestampTz('shop_request_date')->nullable()->after('vendor_documents');
            $table->text('shop_opening_rejection_reason')->nullable()->after('shop_request_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'shop_opening_status',
                'vendor_documents',
                'shop_request_date',
                'shop_opening_rejection_reason',
            ]);
        });
    }
};
