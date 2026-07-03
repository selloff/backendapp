<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->string('code', 10)->nullable()->index();
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                    $table->timestampsTz();
                });
        
                Schema::create('states', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                    $table->string('name');
                    $table->string('code', 20)->nullable();
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                    $table->index(['country_id', 'name']);
                });
        
                Schema::create('cities', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
                    $table->string('name');
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                    $table->index(['state_id', 'name']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');

        Schema::dropIfExists('states');

        Schema::dropIfExists('countries');
    }
};
