<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('name');
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('shipping_zone_locations', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
                    $table->unsignedBigInteger('country_id')->nullable()->index();
                    $table->unsignedBigInteger('state_id')->nullable()->index();
                    $table->unsignedBigInteger('city_id')->nullable()->index();
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('shipping_methods', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
                    $table->string('name');
                    $table->decimal('flat_rate', 13, 2)->default(0);
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
        
                Schema::create('delivery_time_options', function (Blueprint $table) {
                    $table->id();
                    $table->string('option_key')->nullable();
                    $table->unsignedInteger('min_days')->default(0);
                    $table->unsignedInteger('max_days')->default(0);
                    $table->boolean('status')->default(true);
                    $table->unsignedBigInteger('legacy_id')->nullable()->index();
                    $table->timestampsTz();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_time_options');

        Schema::dropIfExists('shipping_methods');

        Schema::dropIfExists('shipping_zone_locations');

        Schema::dropIfExists('shipping_zones');
    }
};
