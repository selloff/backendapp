<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event', 80);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('escrow_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escrow_transaction_id')->constrained('escrow_transactions')->cascadeOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 13, 2);
            $table->string('currency_code', 10)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->timestampTz('funded_at')->nullable()->after('transaction_complete');
            $table->timestampTz('shipped_at')->nullable()->after('funded_at');
            $table->timestampTz('accepted_at')->nullable()->after('shipped_at');
            $table->timestampTz('released_at')->nullable()->after('accepted_at');
            $table->timestampTz('release_scheduled_at')->nullable()->after('released_at');
            $table->string('payment_method', 50)->nullable()->after('release_scheduled_at');
            $table->string('payment_reference', 120)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'funded_at',
                'shipped_at',
                'accepted_at',
                'released_at',
                'release_scheduled_at',
                'payment_method',
                'payment_reference',
            ]);
        });

        Schema::dropIfExists('escrow_ledger_entries');
        Schema::dropIfExists('escrow_events');
    }
};
