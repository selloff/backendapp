<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('traffic')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('contact_views')->default(0);
            $table->timestampsTz();

            $table->unique(['product_id', 'metric_date']);
            $table->index(['vendor_id', 'metric_date']);
            $table->index(['vendor_id', 'product_id']);
        });

        Schema::create('product_listing_metric_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('visitor_key', 120);
            $table->timestampsTz();

            $table->unique(['product_id', 'metric_date', 'visitor_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_metric_visitors');
        Schema::dropIfExists('product_listing_daily_metrics');
    }
};
