<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->nullable()->unique();
                $table->string('name', 100)->nullable();
                $table->string('symbol', 10)->nullable();
                $table->string('currency_format', 30)->default('us');
                $table->string('symbol_direction', 30)->default('left');
                $table->boolean('space_money_symbol')->default(false);
                $table->decimal('exchange_rate', 13, 6)->default(1);
                $table->boolean('status')->default(false);
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code', 10)->unique();
                $table->boolean('is_default')->default(false);
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('language_translations')) {
            Schema::create('language_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
                $table->string('label');
                $table->string('translation');
                $table->timestampsTz();
                $table->unique(['language_id', 'label']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('currencies');
    }
};
